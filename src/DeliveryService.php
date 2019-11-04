<?php

namespace Drupal\delivery;

use Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\delivery\Entity\DeliveryItem;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\delivery\Plugin\views\traits\EntityDeliveryStatusTrait;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Class DeliveryService
 *
 * @package Drupal\delivery
 */
class DeliveryService {

  use EntityDeliveryStatusTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface
   */
  protected $conflictResolverManager;

  /**
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * DeliveryService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   * @param \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflictResolverManager
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspaceAssociation
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    WorkspaceManagerInterface $workspaceManager,
    EntityRepositoryInterface $entityRepository,
    ConflictResolverManagerInterface $conflictResolverManager,
    WorkspaceAssociationInterface $workspaceAssociation
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspaceManager;
    $this->entityRepository = $entityRepository;
    $this->conflictResolverManager = $conflictResolverManager;
    $this->workspaceAssociation = $workspaceAssociation;
  }

  /**
   * Forwards a delivery using a delivery entity and a workspace target ID.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   * @param $target_ids
   * @param int $source_id
   *
   * @return \Drupal\delivery\DeliveryInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function forwardDelivery(DeliveryInterface $delivery, $target_ids, $source_id = 0) {
    $forwarded = $delivery->createDuplicate();
    // Re-set the title.
    $title = $delivery->label();
    $forwarded->set('label', 'FWD: ' . $title);

    // Re-set the source workspace.
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();
    $forwarded->source = ['target_id' => $currentWorkspace->id()];

    // Re-set the target workspace.
    $forwarded->workspaces = array_values(array_map(function ($id) {
      return ['target_id' => $id];
    }, array_filter($target_ids)));

    $forwarded->items = [];

    foreach ($delivery->items as $item) {
      /** @var DeliveryItem $deliveryItem */
      $deliveryItem = $item->entity;
      // Skip delivery items that don't target the current workspace.
      if ($deliveryItem->getTargetWorkspace() !== $currentWorkspace->id()) {
        continue;
      }
      foreach (array_filter($target_ids) as $target) {
        $forwarded->items[] = DeliveryItem::create([
          'source_workspace' => $deliveryItem->getTargetWorkspace(),
          'target_workspace' => $target,
          'entity_type' => $deliveryItem->getTargetType(),
          'entity_id' => $deliveryItem->getTargetId(),
          'source_revision' => $deliveryItem->getResultRevision(),
        ]);
      }
    }

    $forwarded->save();
    return $forwarded;
  }

  /**
   * Returns an array of possible target workspaces, keyed by workspace IDs.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTargetWorkspaces() {
    $list = [];
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();

    $workspaces = $this->entityTypeManager->getStorage('workspace')
      ->loadMultiple();

    foreach ($workspaces as $workspace) {
      $list[$workspace->id()] = $workspace->label();
    }
    return $list;
  }

  /**
   * Checks if there are conflicts.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return bool
   *
   * @todo Properly implement this once the workspace index work is complete.
   */
  public function deliveryHasConflicts(DeliveryInterface $delivery) {
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();

    $pages = [0];
    foreach ($delivery->nodes as $item) {
      $pages[] = $item->target_revision_id;
    }

    $media = [0];
    foreach ($delivery->media as $item) {
      $media[] = $item->target_revision_id;
    }

    $pages = views_get_view_result('workspace_status_pages', 'delivery_status', implode('+', $pages), $delivery->source->target_id, $currentWorkspace->id());
    $media = views_get_view_result('workspace_status_media', 'delivery_status', implode('+', $media), $delivery->source->target_id, $currentWorkspace->id());

    $pages = array_filter($pages, function ($row) {
      return $row->entity_delivery_status === EntityDeliveryStatusTrait::$CONFLICT;
    });

    $media = array_filter($media, function ($row) {
      return $row->entity_delivery_status === EntityDeliveryStatusTrait::$CONFLICT;
    });

    return $pages || $media;
  }

  /**
   * Checks if a delivery has pending changes.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return bool
   *
   * @todo Properly implement this once the workspace index work is complete.
   */
  public function deliveryHasPendingChanges(DeliveryInterface $delivery) {
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();
    $delivered = TRUE;

    foreach ($delivery->items as $item) {
      if ($item->entity->target_workspace->value === $currentWorkspace->id()) {
        $delivered = $delivered && $item->entity->resolution->value;
      }
    }
    return !$delivered;
  }

  /**
   * Checks if the delivery has any kind of changes (conflict, pending changes,
   * outdated content).
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   * @param \Drupal\workspaces\WorkspaceInterface $targetWorkspace | NULL
   *
   * @return bool.
   *
   * @todo Properly implement this once the workspace index work is complete.
   */
  public function deliveryHasChanges(DeliveryInterface $delivery, WorkspaceInterface $targetWorkspace = NULL) {
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();

    $pages = [0];
    foreach ($delivery->nodes as $item) {
      $pages[] = $item->target_revision_id;
    }

    $media = [0];
    foreach ($delivery->media as $item) {
      $media[] = $item->target_revision_id;
    }

    $pages = views_get_view_result('workspace_status_pages', 'delivery_status', implode('+', $pages), $delivery->source->target_id, !empty($targetWorkspace) ? $targetWorkspace->id() : $currentWorkspace->id());
    $media = views_get_view_result('workspace_status_media', 'delivery_status', implode('+', $media), $delivery->source->target_id, !empty($targetWorkspace) ? $targetWorkspace->id() : $currentWorkspace->id());

    $pages = array_filter($pages, function ($row) {
      return $row->entity_delivery_status !== EntityDeliveryStatusTrait::$NOT_APPLICABLE && $row->entity_delivery_status !== EntityDeliveryStatusTrait::$IDENTICAL;
    });

    $media = array_filter($media, function ($row) {
      return $row->entity_delivery_status !== EntityDeliveryStatusTrait::$NOT_APPLICABLE && $row->entity_delivery_status !== EntityDeliveryStatusTrait::$IDENTICAL;
    });

    return $pages || $media;
  }

  /**
   * Returns an array of modified entity IDs.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getModifiedEntities(DeliveryInterface $delivery) {
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();

    $pages = [0];
    foreach ($delivery->nodes as $item) {
      $pages[] = $item->target_revision_id;
    }

    $media = [0];
    foreach ($delivery->media as $item) {
      $media[] = $item->target_revision_id;
    }

    $pages = views_get_view_result('workspace_status_pages', 'delivery_status', implode('+', $pages), $delivery->source->target_id, $currentWorkspace->id());
    $media = views_get_view_result('workspace_status_media', 'delivery_status', implode('+', $media), $delivery->source->target_id, $currentWorkspace->id());

    $pages = array_filter($pages, function ($row) {
      return $row->entity_delivery_status === EntityDeliveryStatusTrait::$MODIFIED;
    });

    $media = array_filter($media, function ($row) {
      return $row->entity_delivery_status === EntityDeliveryStatusTrait::$MODIFIED;
    });

    $entities = [
      'node' => [],
      'media' => [],
    ];
    foreach ($pages as $page) {
      $entities['node'][$page->_entity->id()] = $page->_entity->id();
    }
    foreach ($media as $medium) {
      $entities['media'][$medium->_entity->id()] = $medium->_entity->id();
    }
    return $entities;
  }

  /**
   * Returns true if a delivery can be safely forwarded, otherwise false.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return bool
   */
  public function canForwardDelivery(DeliveryInterface $delivery) {
    $targets = $this->getDeliveryTargets($delivery);
    if (!in_array($this->workspaceManager->getActiveWorkspace()
      ->id(), $targets)) {
      return FALSE;
    }

    if ($this->deliveryHasPendingChanges($delivery)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get an array of node IDs and node revision IDs.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getNodeIDsFromDelivery(DeliveryInterface $delivery) {
    $nodes = $delivery->get('nodes');
    if (!$nodes instanceof EntityReferenceRevisionsFieldItemList) {
      return [];
    }
    return $nodes->getValue() ?: [];
  }

  /**
   * Get an array of media IDs and media revision IDs.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getMediaIDsFromDelivery(DeliveryInterface $delivery) {
    $media = $delivery->get('media');
    if (!$media instanceof EntityReferenceRevisionsFieldItemList) {
      return [];
    }
    return $media->getValue() ?: [];
  }

  /**
   * Pulls all updates from a delivery into the current workspace.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function pullChangesFromDeliveryToWorkspace(DeliveryInterface $delivery, WorkspaceInterface $workspace) {
    $skipped = 0;
    foreach ($delivery->items as $item) {
      /** @var DeliveryItem $deliveryItem */
      $deliveryItem = $item->entity;
      if (isset($deliveryItem->resolution->value)) {
        continue;
      }
      if ($deliveryItem->getTargetWorkspace() !== $workspace->id()) {
        continue;
      }

      if ($this->deliverItemHasConflicts($deliveryItem)) {
        $skipped++;
        continue;
      }

      $this->acceptDeliveryItem($deliveryItem);
    }
    return $skipped;
  }

  /**
   * @param $entityType
   *
   * @return \Drupal\Core\Entity\ContentEntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getContentEntityStorage($entityType) {
    return $this->entityTypeManager->getStorage($entityType);
  }

  public function deliverItemHasConflicts($deliveryItem) {

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($deliveryItem->getTargetType());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $sourceEntity */
    $sourceEntity = $storage->loadRevision($deliveryItem->getSourceRevision());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $targetEntity */
    $targetEntity = $storage->loadRevision($this->getActiveRevision($deliveryItem));

    /** @var \Drupal\revision_tree\EntityRevisionTreeHandlerInterface $revisionTreeHandler */
    $revisionTreeHandler = $this->entityTypeManager->getHandler($sourceEntity->getEntityTypeId(), 'revision_tree');
    $parentEntityRevision = $revisionTreeHandler
      ->getLowestCommonAncestorId($sourceEntity->getRevisionId(), $targetEntity->getRevisionId(), $deliveryItem->getTargetId());

    // If the target is a ascendant, there are no conflicts.
    if ($parentEntityRevision === $targetEntity->getRevisionId()) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $parentEntity */
    $parentEntity = $storage->loadRevision($parentEntityRevision);

    // If there is no common ancestor it means that the entity has not been
    // modified in any parent workspace, so no conflict possible.
    if (!$parentEntity) {
      return FALSE;
    }

    $hasConflicts = FALSE;

    if ($sourceEntity->isTranslatable()) {
      foreach ($sourceEntity->getTranslationLanguages() as $language) {
        $languageId = $language->getId();
        if (!$targetEntity->hasTranslation($languageId)) {
          continue;
        }
        if (!$parentEntity->hasTranslation($languageId)) {
          continue;
        }

        $sourceTranslation = $sourceEntity->getTranslation($languageId);
        $parentTranslation = $parentEntity->getTranslation($languageId);
        $targetTranslation = $targetEntity->getTranslation($languageId);

        $conflicts = $this->conflictResolverManager->getConflicts(
          $targetTranslation,
          $sourceTranslation,
          $parentTranslation
        );
        $hasConflicts = $hasConflicts || count($conflicts) > 0;
      }
    }
    return $hasConflicts;
  }

  /**
   * Force push a delivery item.
   *
   * Resolve a merge conflict preferring the source version of an entity.
   *
   * @param \Drupal\delivery\Entity\DeliveryItem $deliveryItem
   *   The delivery item to force push.
   */
  public function acceptDeliveryItem(DeliveryItem $deliveryItem, $state = 'draft') {
    $entityType = $this->entityTypeManager->getDefinition($deliveryItem->getTargetType());
    $storage = $this->getContentEntityStorage($deliveryItem->getTargetType());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $source */
    $source = $storage->loadRevision($deliveryItem->getSourceRevision());

    $activeRevisionId = $this->getActiveRevision($deliveryItem);

    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionMergeParentField = $entityType->getRevisionMetadataKey('revision_merge_parent');
    $revisionField = $entityType->getKey('revision');

    // Pretend that the source revision is a default revision, so languages
    // are not merged
    $is_default = $source->isDefaultRevision();
    $source->isDefaultRevision(TRUE);
    /** @var ContentEntityInterface $result */
    $result = $storage->createRevision($source);
    $source->isDefaultRevision($is_default);

    $result->{$revisionMergeParentField}->target_revision_id = $deliveryItem->getSourceRevision();
    $result->{$revisionParentField}->target_revision_id = $activeRevisionId;
    $result->workspace = $deliveryItem->getTargetWorkspace();
    $result->setSyncing(TRUE);

    if ($result->hasField('moderation_state')) {
      $result->set('moderation_state', $state);
    }

    $this->workspaceManager->executeInWorkspace($deliveryItem->getTargetWorkspace(), function () use ($result) {
      $result->save();
    });

    $deliveryItem->resolution = DeliveryItem::RESOLUTION_SOURCE;
    $deliveryItem->result_revision = $result->{$revisionField};
    $deliveryItem->save();
  }

  /**
   * Force decline the delivery item.
   *
   * Resolve a merge conflict preferring the target version of an entity.
   *
   * @param \Drupal\delivery\Entity\DeliveryItem $deliveryItem
   */
  public function declineDeliveryItem(DeliveryItem $deliveryItem) {
    $entityType = $this->entityTypeManager->getDefinition($deliveryItem->getTargetType());
    $storage = $this->getContentEntityStorage($deliveryItem->getTargetType());

    $activeRevisionId = $this->getActiveRevision($deliveryItem);

    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionMergeParentField = $entityType->getRevisionMetadataKey('revision_merge_parent');
    $revisionField = $entityType->getKey('revision');

    $source = $storage->loadRevision($deliveryItem->getSourceRevision());

    // Pretend that the source revision is a default revision, so languages
    // are not merged
    $is_default = $source->isDefaultRevision();
    $source->isDefaultRevision(TRUE);
    /** @var ContentEntityInterface $result */
    $result = $storage->createRevision($source);
    $source->isDefaultRevision($is_default);

    $result->{$revisionMergeParentField}->target_revision_id = $deliveryItem->getSourceRevision();
    $result->{$revisionParentField}->target_revision_id = $activeRevisionId;
    $result->workspace = $deliveryItem->getTargetWorkspace();
    $result->setSyncing(TRUE);
    $this->workspaceManager->executeInWorkspace($deliveryItem->getTargetWorkspace(), function () use ($result) {
      $result->save();
    });

    $deliveryItem->resolution = DeliveryItem::RESOLUTION_TARGET;
    $deliveryItem->result_revision = $result->{$revisionField};
    $deliveryItem->save();
  }

  public function mergeDeliveryItem(DeliveryItem $deliveryItem, ContentEntityInterface $result) {
    $entityType = $this->entityTypeManager->getDefinition($deliveryItem->getTargetType());
    $storage = $this->getContentEntityStorage($deliveryItem->getTargetType());

    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionMergeParentField = $entityType->getRevisionMetadataKey('revision_merge_parent');
    $revisionField = $entityType->getKey('revision');

    $target = $this->getActiveRevision($deliveryItem);

    $source = $storage->loadRevision($deliveryItem->getSourceRevision());

    // Pretend that the source revision is a default revision, so languages
    // are not merged
    $is_default = $source->isDefaultRevision();
    $source->isDefaultRevision(TRUE);
    /** @var ContentEntityInterface $result */
    $result = $storage->createRevision($source);
    $source->isDefaultRevision($is_default);

    $result->{$revisionMergeParentField}->target_revision_id = $deliveryItem->getSourceRevision();
    $result->{$revisionParentField}->target_revision_id = $target;
    $result->workspace = $deliveryItem->getTargetWorkspace();
    $result->setSyncing(TRUE);
    $this->workspaceManager->executeInWorkspace($deliveryItem->getTargetWorkspace(), function () use ($result) {
      $result->save();
    });

    // TODO: Properly detect left/right/merge/identical states.
    $deliveryItem->resolution = DeliveryItem::RESOLUTION_MERGE;
    $deliveryItem->result_revision = $result->{$revisionField};
    $deliveryItem->save();
  }

  public function getActiveRevision(DeliveryItem $deliveryItem) {
    $storage = $this->getContentEntityStorage($deliveryItem->getTargetType());
    $targets = $this->workspaceAssociation->getTrackedEntities($deliveryItem->getTargetWorkspace(), $deliveryItem->getTargetType(), [$deliveryItem->getTargetId()]);

    // If the entity is not yet tracked at all, just use the highest live revision.
    if (empty($targets)) {
      $live_revisions = array_keys($storage->getQuery()->allRevisions()
        ->notExists('workspace')
        ->condition($storage->getEntityType()->getKey('id'), $deliveryItem->getTargetId())->execute());
      return array_pop($live_revisions);
    }
    else {
      $targets = array_keys($targets[$deliveryItem->getTargetType()]);
      $target = array_pop($targets);
    }
    return $target;
  }

  protected function getWorkspaceHierarchy($workspaceId) {
    $workspace = $this->getContentEntityStorage('workspace')->load($workspaceId);
    $context = [$workspace->id()];
    while ($workspace = $workspace->parent->entity) {
      $context[] = $workspace->id();
    }
    $context[] = NULL;
    return $context;
  }

  /**
   * Returns the current workspace ids
   * along with descent ids
   *
   * @return array
   */
  public function getCurrentWorkspaceIdWithDescendentIds() {
    $current_workspace_id = $this->getActiveWorkspace()->id();
    // Retrieve descendents of current workspace id
    $descendent_ids = $this->getWorkspaceDescendentIds($current_workspace_id);
    // Return current workspace id along with descendent ids
    return array_merge([$current_workspace_id], $descendent_ids);
  }

  /**
   * Get the descendent workspace ids for a given workspace id
   * @param $workspaceId
   *
   * @return array
   */
  public function getWorkspaceDescendentIds($workspaceId) {
    /** @var \Drupal\workspaces\WorkspaceStorage $workspace_storage */
    $workspace_storage = $this->entityTypeManager->getStorage('workspace');
    // Get the workspace hierarchy
    $workspace_tree = $workspace_storage->loadTree();
    // Return descendents of given workspace id
    return (isset($workspace_tree[$workspaceId])) ? $workspace_tree[$workspaceId]->_descendants : [];
  }

  /**
   * Helper method to return the current active workspace.
   *
   * @return \Drupal\workspaces\WorkspaceInterface
   */
  public function getActiveWorkspace() {
    return $this->workspaceManager->getActiveWorkspace();
  }

  /**
   * Returns an array of workspace IDs referenced by a given delivery.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getTargetWorkspacesFromDelivery(DeliveryInterface $delivery) {
    $workspaces = $delivery->get('workspaces');
    if (!$workspaces instanceof EntityReferenceFieldItemList) {
      return [];
    }
    $targets = $workspaces->getValue();
    return array_column($targets, 'target_id');
  }

  /**
   * Get the delivery target workspace IDs.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getDeliveryTargets(DeliveryInterface $delivery) {
    $targets = [];
    foreach ($delivery->workspaces as $item) {
      $targets[] = $item->target_id;
    }
    return $targets;
  }

  /**
   * Returns true if a delivery can be pulled into the active workspace.
   *
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return bool
   */
  public function canPullDelivery(DeliveryInterface $delivery) {
    // Ensure we're not trying to pull changes to an inappropriate workspace.
    $targets = $this->getTargetWorkspacesFromDelivery($delivery);
    if (!in_array($this->getActiveWorkspace()->id(), $targets)) {
      return FALSE;
    }
    // Ensure there are pending changes.
    if (!$this->deliveryHasPendingChanges($delivery)) {
      return FALSE;
    }
    // Ensure no conflicts.
    if ($this->deliveryHasConflicts($delivery)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns an array containing an entity, conflict and update count.
   *
   * @param \Drupal\workspaces\WorkspaceInterface $workspace
   * @param \Drupal\delivery\DeliveryInterface $delivery
   *
   * @return array
   */
  public function getDeliveryDataByWorkspace(WorkspaceInterface $workspace, DeliveryInterface $delivery) {
    $entities = 0;
    $entities += $delivery->get('nodes')->count();
    $entities += $delivery->get('media')->count();
    $updates = 0;
    $conflicts = 0;

    $pages = [0];
    foreach ($delivery->nodes as $item) {
      $pages[] = $item->target_revision_id;
    }

    $pages = views_get_view_result('workspace_status_pages', 'embed', implode('+', $pages), $delivery->source->target_id, $workspace->id());
    foreach ($pages as $page) {
      $status = $page->status_value;
      if ($status == static::$CONFLICT) {
        $conflicts++;
      }
      if ($status == static::$MODIFIED) {
        $updates++;
      }
    }

    $media = [0];
    foreach ($delivery->media as $item) {
      $media[] = $item->target_revision_id;
    }

    $media = views_get_view_result('workspace_status_media', 'embed', implode('+', $media), $delivery->source->target_id, $workspace->id());
    foreach ($media as $medium) {
      $status = $medium->status_value;
      if ($status == static::$CONFLICT) {
        $conflicts++;
      }
      if ($status == static::$MODIFIED) {
        $updates++;
      }
    }

    return [
      'entities' => $entities,
      'conflicts' => $conflicts,
      'updates' => $updates,
    ];
  }

  /**
   * Returns true if the entity passed belongs the the current / active workspace
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return bool
   */
  public function getEntityInheritedFlag(ContentEntityInterface $entity) {
    $entity_workspace = $entity->get('workspace')->target_id;
    $current_workspace = $this->workspaceManager->getActiveWorkspace()->id();

    return $entity_workspace === $current_workspace;
  }
}
