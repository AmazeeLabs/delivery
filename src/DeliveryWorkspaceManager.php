<?php

namespace Drupal\delivery;

use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManager;
use Drupal\workspaces\Entity\Workspace;

/**
 * Class DeliveryWorkspaceManager
 *
 * @package Drupal\delivery
 */
class DeliveryWorkspaceManager extends WorkspaceManager {

  /**
   * Switches the current workspace without any access checks.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *   The workspace to set as active.
   *
   * @return void
   *
   * @see WorkspaceManager::doSwitchWorkspace()
   */
  protected function unsafeDoSwitchWorkspace(WorkspaceInterface $workspace) {
    // If we are switching the workspace for a safe operation then we don't need
    // to check the access to the target workspace. Otherwise, we just fallback
    // to the parent implementation.
    $this->activeWorkspace = $workspace;

    // Clear the static entity cache for the supported entity types.
    $cache_tags_to_invalidate = array_map(function ($entity_type_id) {
      return 'entity.memory_cache:' . $entity_type_id;
    }, array_keys($this->getSupportedEntityTypes()));
    $this->entityMemoryCache->invalidateTags($cache_tags_to_invalidate);
  }

  /**
   * Emulates WorkspaceManager::executeInWorkspace() without access checks.
   *
   * @param string $workspace_id
   *   The ID of a workspace.
   * @param callable $function
   *   The callback to be executed.
   *
   * @return mixed
   *   The callable's return value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see WorkspaceManager::executeInWorkspace()
   */
  public function unsafeExecuteInWorkspace($workspace_id, callable $function) {
    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')
      ->load($workspace_id);

    if (!$workspace) {
      throw new \InvalidArgumentException('The ' . $workspace_id . ' workspace does not exist.');
    }

    $previous_active_workspace = $this->getActiveWorkspace();
    $this->unsafeDoSwitchWorkspace($workspace);
    $result = $function();
    $this->unsafeDoSwitchWorkspace($previous_active_workspace);

    return $result;
  }

}
