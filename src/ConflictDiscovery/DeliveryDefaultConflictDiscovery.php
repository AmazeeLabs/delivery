<?php

namespace Drupal\delivery\ConflictDiscovery;

use Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryBase;
use Drupal\Core\Conflict\ConflictTypes;
use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\delivery\Entity\DeliveryItem;

/**
 * Decorates the delivery.conflict.discovery.default service.
 */
class DeliveryDefaultConflictDiscovery extends ConflictDiscoveryBase {

  protected $conflictDiscoveryInner;

  /**
   * DeliveryDefaultConflictDiscovery constructor.
   * @param ConflictDiscoveryBase $conflictDiscoveryInner
   */
  public function __construct(ConflictDiscoveryBase $conflictDiscoveryInner) {
    $this->conflictDiscoveryInner = $conflictDiscoveryInner;
  }

  /**
   * {@inheritDoc}
   */
  public function discoverConflictsContentEntity(EntityConflictDiscoveryEvent $event) {
    $this->conflictDiscoveryInner->discoverConflictsContentEntity($event);
    $status_check = $event->getContextParameter('status_check', FALSE);
    // If we only do a status check of a delivery item, then we don't want to
    // alter the conflicts.
    if ($status_check) {
      return;
    }

    // For new entities, we just mark all the fields as conflicted, so that we
    // can see them all in the preview.
    $delivery_item_status = $event->getContextParameter('delivery_item_status', '');
    if (!empty($delivery_item_status) && $delivery_item_status === DeliveryItem::STATUS_NEW) {
      $local_entity = $event->getLocalEntity();

      // The revision metadata fields are updated constantly and they will always
      // cause conflicts, therefore we skip them here.
      // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
      $skip_fields = array_flip($local_entity->getEntityType()->getRevisionMetadataKeys());
      foreach ($local_entity->getFields() as $field_name => $field_items_local) {
        if (isset($skip_fields[$field_name])) {
          continue;
        }

        $field_definition = $field_items_local->getFieldDefinition();
        // There could be no conflicts on read only fields.
        if ($field_definition->isReadOnly()) {
          continue;
        }
        $event->addConflict($field_name, ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE);
      }
    }

    // In order to force the conflict resolution UI to appear even for conflicts
    // which can be resolved automatically, we mark all the remote only
    // conflicts as local and remote.
    foreach ($event->getConflicts() as $property => $conflict_type) {
      if ($conflict_type === ConflictTypes::CONFLICT_TYPE_REMOTE) {
        // We only have to add a new conflict using the same property name
        // because the conflicts array is an associative array keyed by the
        // property name.
        $event->addConflict($property, ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE);
      }
    }
  }
}
