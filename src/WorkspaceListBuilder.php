<?php

namespace Drupal\delivery;

use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceListBuilder as OriginalWorkspaceListBuilder;

/**
 * Local override of workspaces entity list builder to replace the deploy button.
 */
class WorkspaceListBuilder extends OriginalWorkspaceListBuilder {

  /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    if (!\Drupal::currentUser()->hasPermission('administer workspaces')) {
      $workspaces = $this->getUserWorkspaces();
      $query->condition($this->entityType->getKey('id'), $workspaces, 'IN');
    }
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function offCanvasRender(array &$build) {
    parent::offCanvasRender($build);
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    $build['active_workspace']['actions']['deploy']['#access'] = FALSE;
    $userWorkspaces = $this->getUserWorkspaces();

    // Show delivery button only in case it's allowed.
    if ($active_workspace->id() == 'live' ||
      (!\Drupal::currentUser()->hasPermission('add delivery to assigned workspaces') ||
      !in_array($active_workspace->id(), $userWorkspaces))) {
      if (!\Drupal::currentUser()->hasPermission('add delivery to any workspaces')) {
        return;
      }
    }
    $build['active_workspace']['actions']['deliver'] = [
      '#type' => 'link',
      '#title' => t('Deliver content'),
      '#url' => Url::fromRoute('delivery.workspace_delivery_controller', ['workspace' => $active_workspace->id()]),
      '#attributes' => [
        'class' => ['button', 'active-workspace__button'],
      ],
    ];
  }

  /**
   * Return workspaces ids.
   */
  protected function getUserWorkspaces() {
    $account = \Drupal::currentUser();
    $assignedWorkspaces = \Drupal::entityManager()->getStorage('user')->load($account->id())->get('field_assigned_workspaces')->referencedEntities();
    $workspaces = [];
    foreach ($assignedWorkspaces as $workspace) {
      $workspaces[] = $workspace->id();
    }
    $result = $workspaces;
    foreach ($workspaces as $workspaceId) {
      $result = array_merge($result, $this->getWorkspaceChildren($workspaceId));
    }
    return $result;
  }

  /**
   * Get workspace children.
   */
  protected function getWorkspaceChildren($id) {
    $query = \Drupal::service('entity_type.manager')->getStorage('workspace')->getQuery();
    $query->condition('parent_workspace', $id);
    $result = $query->execute();
    return $result;
  }

}
