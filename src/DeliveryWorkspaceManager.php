<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\workspaces\WorkspaceManager;

/**
 * Override of the default workspaces manager.
 *
 * Replacement of the default workspaces manager that skips all pre-operations
 * to allow saving all entities from all workspaces. This is a deliberate opt
 * out of a well thought through security mechanism discussed in this issue:
 * https://www.drupal.org/project/drupal/issues/2975334
 */
class DeliveryWorkspaceManager extends WorkspaceManager {

  /**
   * {@inheritdoc}
   */
  public function shouldSkipPreOperations(EntityTypeInterface $entity_type) {
    return TRUE;
  }

}

