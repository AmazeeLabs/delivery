<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Helper service.
 * TODO: Move to delivery module.
 */
class WorkspaceAssigment {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Cached mapping between users and assigned workspaces.
   * @var array
   */
  protected $userWorkspaces;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Return workspaces ids.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserWorkspaces(AccountInterface $account) {
    if (!isset($this->userWorkspaces[$account->id()])) {
      $assignedWorkspaces = $this->entityTypeManager
        ->getStorage('user')
        ->load($account->id())
        ->get('assigned_workspaces')
        ->referencedEntities();

      $workspaces = [];
      foreach ($assignedWorkspaces as $workspace) {
        $workspaces[] = $workspace->id();
      }
      $result = $workspaces;
      foreach ($workspaces as $workspaceId) {
        $result = array_merge($result, $this->getWorkspaceChildren($workspaceId));
      }

      $this->userWorkspaces[$account->id()] = $result;
    }
    return $this->userWorkspaces[$account->id()];
  }

  /**
   * Get workspace children.
   */
  protected function getWorkspaceChildren($workspaceId) {
    $query = $this->entityTypeManager->getStorage('workspace')->getQuery();
    $query->condition('parent', $workspaceId);
    $result = $query->execute();
    $workspaces = $result;
    foreach ($workspaces as $id) {
      if ($id !== $workspaceId) {
        $result = array_merge($result, $this->getWorkspaceChildren($id));
      }
    }
    return $result;
  }

}
