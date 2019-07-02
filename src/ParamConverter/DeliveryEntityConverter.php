<?php

namespace Drupal\delivery\ParamConverter;

use Drupal\Core\ParamConverter\EntityConverter;

/**
 * Customized entity converter.
 *
 * Blocks direct to entities that are not relevant for the current workspace.
 *
 * @package Drupal\delivery\ParamConverter
 */
class DeliveryEntityConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspacesManager */
    $workspacesManager = \Drupal::service('workspaces.manager');
    /** @var \Drupal\Core\Entity\EntityInterface $result */
    $result = parent::convert($value, $definition, $name, $defaults);

    if (
      $result &&
      $workspacesManager->isEntityTypeSupported($result->getEntityType()) &&
      !($result->workspace && $result->workspace->target_id)
    ) {
      return NULL;
    }

    return $result;
  }

}
