<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
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
   * DeliveryService constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspaceManager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspaceManager;
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

    // Get the merge revisions of all node revisions.
    $nodeRevisions = [];
    foreach ($delivery->nodes as $item) {
      $nodeRevisions[] = $item->target_revision_id;
    }

    $forwarded->nodes = [];
    if ($nodeRevisions) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $nodeType */
      $nodeType = $this->entityTypeManager->getDefinition('node');
      /** @var \Drupal\Core\Entity\Query\Sql\Query $nodes */
      $nodeQuery = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->allRevisions();
      $nodeQuery->condition($nodeType->getRevisionMetadataKey('revision_parent') . '.merge_target_id', $nodeRevisions, 'IN');
      $nodeResult = $nodeQuery->execute();


      foreach ($nodeResult as $revisionId => $entityId) {
        $forwarded->nodes[] = [
          'target_id' => $entityId,
          'target_revision_id' => $revisionId,
        ];
      }
    }

    // Get the merge revisions of all media revisions.
    $mediaRevisions = [];
    foreach ($delivery->media as $item) {
      $mediaRevisions[] = $item->target_revision_id;
    }

    $forwarded->media = [];
    if ($mediaRevisions) {
      /** @var \Drupal\Core\Entity\ContentEntityTypeInterface $mediaType */
      $mediaType = $this->entityTypeManager->getDefinition('media');
      /** @var \Drupal\Core\Entity\Query\Sql\Query $nodes */
      $mediaQuery = $this->entityTypeManager->getStorage('media')
        ->getQuery()
        ->allRevisions();
      $mediaQuery->condition($mediaType->getRevisionMetadataKey('revision_parent') . '.merge_target_id', $mediaRevisions, 'IN');
      $mediaResult = $mediaQuery->execute();

      foreach ($mediaResult as $revisionId => $entityId) {
        $forwarded->media[] = [
          'target_id' => $entityId,
          'target_revision_id' => $revisionId,
        ];
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

    return $pages || $media;
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

    if ($this->deliveryHasConflicts($delivery)) {
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
    // Return an array of revisions.
    $revisions = [];
    // Get IDs.
    $entities = [
      'node' => $this->getNodeIDsFromDelivery($delivery),
      'media' => $this->getMediaIDsFromDelivery($delivery),
    ];
    // Only pull the modified entities.
    $modified = $this->getModifiedEntities($delivery);
    $entities['node'] = array_filter($entities['node'], function ($entity) use ($modified) {
      return in_array($entity['target_id'], $modified['node']);
    });
    $entities['media'] = array_filter($entities['media'], function ($entity) use ($modified) {
      return in_array($entity['target_id'], $modified['media']);
    });
    // Iterate through the entity data and create revisions.
    foreach ($entities as $entity_type => $entity_data) {
      foreach ($entity_data as $entity_datum) {
        $selected_revision_id = $entity_datum['target_revision_id'];
        // Create a new revision based on the selected revision.
        $storage = $this->entityTypeManager->getStorage($entity_type);
        $selected_revision = $storage->loadRevision($selected_revision_id);
        if (!$selected_revision) {
          continue;
        }
        $new_revision = $storage->createRevision($selected_revision);
        // Get the old revision ID.
        $entity = $storage->load($entity_datum['target_id']);
        $old_revision = $entity->getRevisionId();
        // Set the workspace to the one we are merging to.
        $new_revision->workspace = $this->workspaceManager->getActiveWorkspace()
          ->id();
        // When merging revisions, we set the cloned revision as parent and
        // the previous revision as merge parent.
        $new_revision->revision_parent->target_id = $old_revision;
        $new_revision->revision_parent->merge_target_id = $selected_revision_id;
        $new_revision->setNewRevision(TRUE);
        // Save the new revision and redirect to its page.
        $new_revision->save();
        $revisions[] = $new_revision;
      }
    }
    // Make sure the array can be empty.
    $new_revisions = array_filter($revisions);
    return $new_revisions;
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

}
