<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\revision_tree\RevisionTreeQuery;

/**
 * Revision index helper service.
 */
class RevisionIndex {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Workspaces manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * RevisionIndex constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   *   Workspace manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, WorkspaceManagerInterface $workspaceManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * Update entity index.
   */
  public function updateIndex($ids = [], $type = '', $default = FALSE, $filterWorkspace = NULL) {
    $connection = \Drupal::database();

    $indexTable = $default ? 'revision_tree_index_default' : 'revision_tree_index';

    $deleteQuery = $connection->delete($indexTable);
    if (!empty($ids)) {
      $deleteQuery->condition('entity_id', $ids, 'IN');
      $deleteQuery->condition('entity_type', $type);
    }
    if (!empty($filterWorkspace) && !empty($type)) {
      $deleteQuery->condition('workspace', $filterWorkspace);
      $deleteQuery->condition('entity_type', $type);
    }
    $deleteQuery->execute();

    $revisionTreeQuery = new RevisionTreeQuery($connection);

    if (!empty($filterWorkspace)) {
      $workspacesIds = [$filterWorkspace];
    }
    else {
      $query = $this->entityTypeManager->getStorage('workspace')->getQuery();
      $workspacesIds = $query->execute();
    }

    $workspaces = $this->entityTypeManager->getStorage('workspace')->loadMultiple(array_values($workspacesIds));

    $contexts = [];
    foreach ($workspaces as $workspace) {
      $contexts[$workspace->id()] = $this->getWorkspaceHierarchy($workspace);
    }

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($this->workspaceManager->isEntityTypeSupported($entity_type)
        && (empty($type) || $entity_type_id == $type)) {

        $updateQuery = $connection->insert($indexTable)
          ->fields([
            'entity_type',
            'entity_id',
            'revision_id',
            'workspace',
          ]);
        $hasValues = FALSE;

        foreach ($workspaces as $workspace) {
          $context = $contexts[$workspace->id()];

          $query = $revisionTreeQuery->getActiveLeaves($entity_type, ['workspace' => $context]);
          $query->addField('base', 'workspace');
          if (!empty($ids)) {
            $query->condition('base.' . $entity_type->getKey('id'), $ids, 'IN');
          }
          if ($default) {
            $query->condition('base.revision_default', 1);
          }
          $result = $query->execute()->fetchAllAssoc('entity_id');
          $filteredResult = [];
          foreach ($result as $key => $revision_active) {
            if (!in_array($revision_active->workspace, $context)) {
              continue;
            }
            $filteredResult[$key] = (array) $revision_active;
            $filteredResult[$key]['entity_type'] = $entity_type_id;
            $filteredResult[$key]['workspace'] = $workspace->id();
            $updateQuery->values($filteredResult[$key]);
            $hasValues = TRUE;
          }
        }
        if ($hasValues) {
          $updateQuery->execute();
        }
      }
    }
    // TODO: message.
  }

  /**
   * Return hierarchy for given workspace.
   *
   * The live workspace is omitted as parent workspace and only indexed within
   * its own scope. This means it will never inherit content to subspaces.
   */
  protected function getWorkspaceHierarchy(WorkspaceInterface $workspace) {
    if ($workspace->id() === 'live') {
      return ['live', NULL];
    }
    $context = [$workspace->id()];
    while ($workspace = $workspace->parent_workspace->entity) {
      if ($workspace->id() !== 'live') {
        $context[] = $workspace->id();
      }
    }
    return $context;
  }

}
