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
    // Make sure to never attempt to load the latest revision
    // even though content moderation might tell us to.
    if (array_key_exists('load_latest_revision', $definition)) {
      unset($definition['load_latest_revision']);
    }

    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspacesManager */
    $workspacesManager = \Drupal::service('workspaces.manager');
    /** @var \Drupal\Core\Entity\EntityInterface $result */
    $result = parent::convert($value, $definition, $name, $defaults);

   if (
      $result &&
      $workspacesManager->isEntityTypeSupported($result->getEntityType()) &&
      (
        !($result->workspace && $result->workspace->target_id)
        || $result->deleted->value !== '0'
      )
    ) {
      return NULL;
    }

    return $result;
  }

}
