<?php

namespace Drupal\delivery;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workspaces\EntityOperations;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modified entity operations to bypass workspaces integrity constraints.
 *
 * TODO: Create a core patch to loosen constraints without having to duplicate
 *       all this code.
 *
 * @package Drupal\delivery
 */
class DeliveryEntityOperations extends EntityOperations {

  /**
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    WorkspaceManagerInterface $workspace_manager,
    WorkspaceAssociationInterface $workspace_association,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator
  ) {
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    parent::__construct(
      $entity_type_manager,
      $workspace_manager,
      $workspace_association
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('workspaces.manager'),
      $container->get('workspaces.association'),
      $container->get('cache_tags.invalidator')
    );
  }

  public function entityInsert(EntityInterface $entity) {
    // We are not updating the menu tree definitions when a custom menu link
    // entity is saved as a pending revision, so we need to clear the system
    // menu cache manually.
    if ($entity->getEntityTypeId() === 'menu_link_content') {
      $cache_tags = Cache::buildTags('config:system.menu', [$entity->getMenuName()], '.');
      $this->cacheTagsInvalidator->invalidateTags($cache_tags);
    }

    return parent::entityInsert($entity);
  }


  /**
   * {@inheritdoc}
   */
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

    // We are not updating the menu tree definitions when a custom menu link
    // entity is saved as a pending revision, so we need to clear the system
    // menu cache manually.
    if ($entity->getEntityTypeId() === 'menu_link_content') {
      $cache_tags = Cache::buildTags('config:system.menu', [$entity->getMenuName()], '.');
      $this->cacheTagsInvalidator->invalidateTags($cache_tags);
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
      // $entity->isDefaultRevision(FALSE);

      // Track the workspaces in which the new revision was saved.
      $field_name = $entity_type->getRevisionMetadataKey('workspace');
      $entity->{$field_name}->target_id = $this->workspaceManager->getActiveWorkspace()->id();
    }

    // When a new published entity is inswerted in a non-default workspace, we
    // actually want two revisions to be saved:
    // - An unpublished default revision in the default ('live') workspace.
    // - A published pending revision in the current workspace.
    if ($entity->isNew() && $entity->isPublished()) {
      // Keep track of the publishing status in a dynamic property for
      // ::entityInsert(), then unpublish the default revision.
      // @todo Remove this dynamic property once we have an API for associating
      //   temporary data with an entity: https://www.drupal.org/node/2896474.
      $entity->setUnpublished();
    }

    if ($entity->isNew()) {
      $entity->_initialPublished = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityPredelete(EntityInterface $entity) {}

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, $form_id) {}

}
