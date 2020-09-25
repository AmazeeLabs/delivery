<?php

namespace Drupal\delivery\ConflictResolution;

use Drupal\Core\Conflict\ConflictResolution\MergeStrategyBase;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class MergeBlacklistedFields
 *
 * This class doesn't actually do any conflict resolution. Instead it checks
 * for any fields marked as blacklisted and removes them from the conflicts
 * array so that other strategies cannot attempt to merge them.
 *
 * @package Drupal\delivery\ConflictResolution
 */
class MergeBlacklistedFields extends MergeStrategyBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fieldConfig;

  /**
   * @var string Merge direction target.
   */
  static public $MERGE_DIRECTION_TARGET = 'target';

  /**
   * @var string Merge direction source.
   */
  static public $MERGE_DIRECTION_SOURCE = 'source';

  /**
   * @var string Merge direction base.
   */
  static public $MERGE_DIRECTION_BASE = 'base';

  /**
   * MergeBlacklistedFields constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldConfig = $this->entityTypeManager->getStorage('field_config');
  }

  /**
   * Return strategy ID.
   *
   * @return string
   *   Strategy ID.
   */
  public function getMergeStrategyId(): string {
    return 'conflict_resolution.merge_blacklisted_fields';
  }

  /**
   * Removes any conflicts marked as black listed.
   *
   * @param \Drupal\conflict\Event\EntityConflictResolutionEvent $event
   *   Resolution event.
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    $localEntity = $event->getLocalEntity();
    $remoteEntity = $event->getRemoteEntity();
    $baseEntity = $event->getBaseEntity();
    $resultEntity = $event->getResultEntity();

    $conflicts = array_keys($event->getConflicts());
    if (!$conflicts) {
      return;
    }
    foreach ($conflicts as $property) {
      $fieldConflictConfig = $this->getFieldConflictConfig($baseEntity, $property);
      if (!$fieldConflictConfig) {
        continue;
      }
      if (!$fieldConflictConfig->getThirdPartySetting('delivery', 'blacklisted')) {
        continue;
      }
      if ($direction = $fieldConflictConfig->getThirdPartySetting('delivery', 'merge_direction')) {
        switch ($direction) {
          case static::$MERGE_DIRECTION_SOURCE:
            $resultEntity->set($property, $remoteEntity->get($property)
              ->getValue());
            break;

          case static::$MERGE_DIRECTION_TARGET:
            $resultEntity->set($property, $localEntity->get($property)
              ->getValue());
            break;

          case static::$MERGE_DIRECTION_BASE:
            $resultEntity->set($property, $baseEntity->get($property)
              ->getValue());
            break;

          default:
            break;
        }
        $event->removeConflict($property);
      }
      else {
        $resultEntity->set($property, $remoteEntity->get($property)
          ->getValue());
        $event->removeConflict($property);
      }
    }
  }

  /**
   * Returns the field config for the conflicted property.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $property
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  protected function getFieldConflictConfig(EntityInterface $entity, $property) {
    return $this->fieldConfig->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $property);
  }

}
