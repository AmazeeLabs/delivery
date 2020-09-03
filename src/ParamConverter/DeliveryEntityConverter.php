<?php

namespace Drupal\delivery\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\workspaces\WorkspaceInterface;

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
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = parent::convert($value, $definition, $name, $defaults);
    // Return early if we don't have an entity.
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }
    // Return the plain old entity if we don't actually have a workspace.
    if (!$workspacesManager->getActiveWorkspace() instanceof WorkspaceInterface) {
      return $entity;
    }
    // Can this entity belong in this workspace?
    if (!$workspacesManager->isEntityTypeSupported($entity->getEntityType())) {
      return NULL;
    }
    // Does the entity have a workspace?
    if (!($entity->workspace && $entity->workspace->target_id)) {
      return NULL;
    }
    // Has this entity been deleted?
    if ($entity->deleted->value !== '0') {
      return NULL;
    }
    return $entity;
  }

}
