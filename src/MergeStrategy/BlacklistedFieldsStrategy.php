<?php

namespace Drupal\delivery\MergeStrategy;

use Drupal\conflict\ConflictResolution\MergeStrategyBase;
use Drupal\conflict\Event\EntityConflictResolutionEvent;

/**
 * Blacklisted fields discovery.
 */
class BlacklistedFieldsStrategy extends MergeStrategyBase {

  /**
   * {@inheritdoc}
   */
  public function getMergeStrategyId(): string {
    return 'blacklisted-fields';
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    $conflicts = array_keys($event->getConflicts());
    $baseEntity = $event->getBaseEntity();
    $fieldConfig = \Drupal::entityManager()->getStorage('field_config');
    $resultEntity = $event->getResultEntity();
    $localEntity = $event->getLocalEntity();

    if ($conflicts) {
      foreach ($conflicts as $property) {
        $fieldConflictConfig = $fieldConfig->load($baseEntity->getEntityTypeId() . '.' . $baseEntity->bundle() . '.' . $property);
        if ($fieldConflictConfig && $fieldConflictConfig->getThirdPartySetting('delivery', 'blacklisted')) {
          $event->removeConflict($property);
          $resultEntity->{$property} = $localEntity->{$property};
        }
      }
    }
  }
}
