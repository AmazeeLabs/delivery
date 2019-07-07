<?php

namespace Drupal\delivery\ConflictDiscovery;

use Drupal\Core\Conflict\Event\EntityConflictEvents;
use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryBase;

/**
 * Blacklisted fields discovery.
 */
class BlacklistedFieldsDiscovery extends ConflictDiscoveryBase {

  /**
   * {@inheritdoc}
   */
  public function discoverConflictsContentEntity(EntityConflictDiscoveryEvent $event) {
    $conflicts = array_keys($event->getConflicts());
    $baseEntity = $event->getBaseEntity();
    $fieldConfig = \Drupal::entityManager()->getStorage('field_config');

    if ($conflicts) {
      foreach ($conflicts as $property) {
        $fieldConflictConfig = $fieldConfig->load($baseEntity->getEntityTypeId() . '.' . $baseEntity->bundle() . '.' . $property);
        if ($fieldConflictConfig && $fieldConflictConfig->getThirdPartySetting('delivery', 'blacklisted')) {
          $event->removeConflict($property);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityConflictEvents::ENTITY_CONFLICT_DISCOVERY][] = ['discoverConflicts', -100];
    return $events;
  }

}
