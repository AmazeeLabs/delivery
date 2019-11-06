<?php

namespace Drupal\delivery;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_usage\EntityUsage;
use Drupal\workspaces\WorkspaceInterface;

/**
 * Customization of the EntityUsage service, adding workspaces support.
 */
class DeliveryEntityUsage extends EntityUsage {
  /**
   * Returns the current workspace id along with descendent ids
   *
   * @return array
   */
  public function getCurrentWorkspaceIdWithDescendentIds() {
    $activeWorkspace = \Drupal::service('workspaces.manager')->getActiveWorkspace();
    if ($activeWorkspace->auto_push->value && $activeWorkspace->parent->entity) {
      $activeWorkspace = $activeWorkspace->parent->entity;
    }
    $current_workspace_id = $activeWorkspace->id();
    // Retrieve descendents of current workspace id
    $descendent_ids = $this->getWorkspaceDescendentIds($current_workspace_id);
    // Return current workspace id along with descendent ids
    return array_merge([$current_workspace_id], $descendent_ids);
  }

  /**
   * Returns the descendent workspace ids for a given workspace id
   * @param $workspaceId
   *
   * @return array
   */
  public function getWorkspaceDescendentIds($workspaceId) {
    /** @var \Drupal\workspaces\WorkspaceStorage $workspace_storage */
    $workspace_storage = \Drupal::entityTypeManager()->getStorage('workspace');
    // Get the workspace hierarchy
    $workspace_tree = $workspace_storage->loadTree();
    // Return descendents of given workspace id
    return (isset($workspace_tree[$workspaceId])) ? $workspace_tree[$workspaceId]->_descendants : [];
  }

  /**
   * {@inheritdoc}
   */
  public function listSources(EntityInterface $target_entity, $nest_results = TRUE) {
    /** @var \Drupal\workspaces\WorkspaceStorage $workspace_storage */
    $workspace_storage = \Drupal::entityTypeManager()->getStorage('workspace');
    // Entities can have string IDs. We support that by using different columns
    // on each case.
    $target_id_column = $this->isInt($target_entity->id()) ? 'target_id' : 'target_id_string';
    $query = $this->connection->select($this->tableName, 'e')
      ->fields('e', [
        'source_id',
        'source_id_string',
        'source_type',
        'source_langcode',
        'source_vid',
        'method',
        'field_name',
        'count',
      ]);

    $query
      ->condition($target_id_column, $target_entity->id())
      ->condition('target_type', $target_entity->getEntityTypeId())
      ->condition('count', 0, '>')
      ->orderBy('source_type')
      ->orderBy('source_id', 'DESC')
      ->orderBy('source_vid', 'DESC')
      ->orderBy('source_langcode');

    $affectedWorkspaces = $this->getCurrentWorkspaceIdWithDescendentIds();
    $current_workspace_id = \Drupal::service('workspaces.manager')->getActiveWorkspace()->id();
    $workspaces = array_map(function (WorkspaceInterface $workspace) {
      return $workspace->id();
    }, array_filter($workspace_storage->loadMultiple($affectedWorkspaces), function (WorkspaceInterface $workspace) {
      return $workspace->status->value;
    }));

    $workspaces[] = $current_workspace_id;
    if ($target_entity instanceof ContentEntityInterface) {
      $query->innerJoin('workspace_association', 'sources', 'e.source_type = sources.target_entity_type_id AND e.source_vid = sources.target_entity_revision_id');
      $query->condition('sources.workspace', $workspaces, 'IN');
      $query->addField('sources', 'workspace', 'workspace');

      $query->innerJoin('workspace_association', 'targets', 'e.target_type = targets.target_entity_type_id AND e.target_id = targets.target_entity_id AND sources.workspace = targets.workspace');
      $query->condition('targets.target_entity_revision_id', $target_entity->getRevisionId());
    }
    $result = $query->execute();

    $references = [];
    foreach ($result as $usage) {
      $references[$usage->source_type][] = [
        'source_langcode' => $usage->source_langcode,
        'source_vid' => $usage->source_vid,
        'workspace' => $usage->workspace,
        'method' => $usage->method,
        'field_name' => $usage->field_name,
        'count' => $usage->count,
      ];
    }

    return $references;
  }
}
