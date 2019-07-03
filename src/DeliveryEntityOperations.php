<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workspaces\EntityOperations;

/**
 * Modified entity operations to bypass workspaces integrity constraints.
 *
 * TODO: Create a core patch to loosen constraints without having to duplicate
 *       all this code.
 *
 * @package Drupal\delivery
 */
class DeliveryEntityOperations extends EntityOperations {

  public function entityPresave(EntityInterface $entity) {
    $entity_type = $entity->getEntityType();

    if (!$entity_type->isRevisionable()) {
      return;
    }

    // Only run if we are not dealing with an entity type provided by the
    // Workspaces module, an internal entity type or if we are in a non-default
    // workspace.
    if ($this->shouldSkipPreOperations($entity_type)) {
      return;
    }

    // Disallow any change to an unsupported entity when we are not in the
    // default workspace.
    // TODO: Modified. Find a way to not copy all of the rest.
    // if (!$this->workspaceManager->isEntityTypeSupported($entity_type)) {
    //   throw new \RuntimeException('This entity can only be saved in the default workspace.');
    // }

    /** @var \Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\EntityPublishedInterface $entity */
    if (!$entity->isNew() && !$entity->isSyncing()) {
      // Force a new revision if the entity is not replicating.
      $entity->setNewRevision(TRUE);

      // All entities in the non-default workspace are pending revisions,
      // regardless of their publishing status. This means that when creating
      // a published pending revision in a non-default workspace it will also be
      // a published pending revision in the default workspace, however, it will
      // become the default revision only when it is replicated to the default
      // workspace.
      // TODO: Modified
      // $entity->isDefaultRevision(FALSE);

      // Track the workspaces in which the new revision was saved.
      $field_name = $entity_type->getRevisionMetadataKey('workspace');
      $entity->{$field_name}->target_id = $this->workspaceManager->getActiveWorkspace()->id();
    }

    // When a new published entity is inserted in a non-default workspace, we
    // actually want two revisions to be saved:
    // - An unpublished default revision in the default ('live') workspace.
    // - A published pending revision in the current workspace.
    // TODO: Modified
//    if ($entity->isNew() && $entity->isPublished()) {
//      // Keep track of the publishing status in a dynamic property for
//      // ::entityInsert(), then unpublish the default revision.
//      // @todo Remove this dynamic property once we have an API for associating
//      //   temporary data with an entity: https://www.drupal.org/node/2896474.
//      $entity->_initialPublished = TRUE;
//      $entity->setUnpublished();
//    }
  }

  public function entityPredelete(EntityInterface $entity) {
  }

  public function entityFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
  }

}
