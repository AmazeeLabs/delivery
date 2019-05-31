<?php

namespace Drupal\delivery\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\delivery\DeliveryItemInterface;

/**
 * @ContentEntityType(
 *   id = "delivery_item",
 *   label = @Translation("Delivery item"),
 *   handlers={
 *     "views_data" = "Drupal\delivery\DeliveryItemViewsData"
 *   },
 *   base_table = "delivery_item",
 *   internal = true,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   }
 * )
 */
class DeliveryItem extends ContentEntityBase implements DeliveryItemInterface {
  /**
   * Resolution status identical.
   *
   * There was no need for a conflict resolution, because the revisions where
   * identical in the first place.
   */
  const RESOLUTION_IDENTICAL = 0;

  /**
   * Resolution status source.
   *
   * The result document was identical to the left hand document.
   */
  const RESOLUTION_SOURCE = 1;

  /**
   * Resolution status target.
   *
   * The result document was identical to the right hand document.
   */
  const RESOLUTION_TARGET = 2;

  /**
   * Resolution status merge.
   *
   * The result differs from both left and right hand document.
   */
  const RESOLUTION_MERGE = 3;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['source_workspace'] = BaseFieldDefinition::create('string')
      ->setLabel('Source workspace')
      ->setDescription('The source workspace for this delivery item.')
      ->setRequired(TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['target_workspace'] = BaseFieldDefinition::create('string')
      ->setLabel('Target workspace')
      ->setDescription('The target workspace for this delivery item.')
      ->setRequired(TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel('Entity type')
      ->setDescription('The entity type id of the deliverable entity.')
      ->setRequired(TRUE)
      ->setSetting('max_length', EntityTypeInterface::BUNDLE_MAX_LENGTH);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel('Entity ID')
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['source_revision'] = BaseFieldDefinition::create('integer')
      ->setLabel('Source revision ID')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['result_revision'] = BaseFieldDefinition::create('integer')
      ->setLabel('Resulting revision ID')
      ->setSetting('unsigned', TRUE);

    $fields['resolution'] = BaseFieldDefinition::create('integer')
      ->setLabel('Resolution type')
      ->setSetting('unsigned', TRUE);

    return $fields;
  }

  public function getTargetId() {
    return $this->entity_id->value;
  }

  public function getTargetType() {
    return $this->entity_type->value;
  }

  public function getSourceWorkspace() {
    return $this->source_workspace->value;
  }

  public function getTargetWorkspace() {
    return $this->target_workspace->value;
  }

  public function getSourceRevision() {
    return $this->source_revision->value;
  }

  public function getResultRevision() {
    return $this->result_revision->value;
  }

  public function getResolution() {
    return $this->resolution->value;
  }
}
