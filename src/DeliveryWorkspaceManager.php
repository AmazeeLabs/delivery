<?php

namespace Drupal\delivery;

use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManager;

class DeliveryWorkspaceManager extends WorkspaceManager {

  /**
   * {@inheritDoc}
   */
  protected function doSwitchWorkspace($workspace, $safe = FALSE) {
    // If we are switching the workspace for a safe operation then we dont' need
    // to check the access to the target workspace. Otherwise, we just fallback
    // to the parent implementation.
    if ($safe) {
      $this->activeWorkspace = $workspace ?: FALSE;

      // Clear the static entity cache for the supported entity types.
      $cache_tags_to_invalidate = array_map(function ($entity_type_id) {
        return 'entity.memory_cache:' . $entity_type_id;
      }, array_keys($this->getSupportedEntityTypes()));
      $this->entityMemoryCache->invalidateTags($cache_tags_to_invalidate);

      // Clear the static cache for path aliases. We can't inject the path alias
      // manager service because it would create a circular dependency.
      \Drupal::service('path_alias.manager')->cacheClear();
    }
    else {
      parent::doSwitchWorkspace($workspace);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function executeInWorkspace($workspace_id, callable $function, $safe = FALSE) {
    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($workspace_id);

    if (!$workspace) {
      throw new \InvalidArgumentException('The ' . $workspace_id . ' workspace does not exist.');
    }

    $previous_active_workspace = $this->getActiveWorkspace();
    $this->doSwitchWorkspace($workspace, $safe);
    $result = $function();
    $this->doSwitchWorkspace($previous_active_workspace, $safe);

    return $result;
  }
}
