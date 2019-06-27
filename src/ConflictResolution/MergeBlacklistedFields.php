<?php

namespace Drupal\delivery\ConflictResolution;

use Drupal\Core\Conflict\ConflictResolution\MergeStrategyBase;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;

/**
 * Automatically resolve conflicts in blacklisted fields.
 *
 * @package Drupal\delivery\ConflictResolution
 */
class MergeBlacklistedFields extends MergeStrategyBase {

  static public $MERGE_DIRECTION_TARGET = 'target';
  static public $MERGE_DIRECTION_SOURCE = 'source';
  static public $MERGE_DIRECTION_BASE = 'base';

  /**
   * Return stategy id.
   *
   * @return string
   *   Strategy id.
   */
  public function getMergeStrategyId(): string {
    return 'conflict_resolution.merge_blacklisted_fields';
  }

  /**
   * Resolve conflict.
   *
   * @param \Drupal\Core\Conflict\Event\EntityConflictResolutionEvent $event
   *   Resolution event.
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    $local_entity = $event->getLocalEntity();
    $remote_entity = $event->getRemoteEntity();
    $base_entity = $event->getBaseEntity();
    $result_entity = $event->getResultEntity();

    $conflicts = array_keys($event->getConflicts());
    $fieldConfig = \Drupal::entityManager()->getStorage('field_config');

    if ($conflicts) {
      foreach ($conflicts as $property) {
        $fieldConflictConfig = $fieldConfig->load($base_entity->getEntityTypeId() . '.' . $base_entity->bundle() . '.' . $property);
        if ($fieldConflictConfig && $fieldConflictConfig->getThirdPartySetting('conflict', 'blacklisted')) {
          if ($direction = $fieldConflictConfig->getThirdPartySetting('conflict', 'merge_direction')) {
            switch ($direction) {
              case static::$MERGE_DIRECTION_SOURCE:
                $result_entity->set($property, $remote_entity->get($property)->getValue());
                break;

              case static::$MERGE_DIRECTION_TARGET:
                $result_entity->set($property, $local_entity->get($property)->getValue());
                break;

              case static::$MERGE_DIRECTION_BASE:
                $result_entity->set($property, $base_entity->get($property)->getValue());
                break;

              default:
                break;
            }
            $event->removeConflict($property);
          }
          else {
            $result_entity->set($property, $remote_entity->get($property)->getValue());
            $event->removeConflict($property);
          }
        }
      }
    }
  }

}
