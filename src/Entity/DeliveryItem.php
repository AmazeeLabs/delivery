<?php

namespace Drupal\delivery\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\delivery\DeliveryItemInterface;
use Drupal\delivery\Field\DeliveryItemStatus;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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

  /**
   * No resolution, no revision on target.
   */
  const STATUS_NEW = 'new';

  /**
   * Resolution SOURCE and the resolution id equals current id.
   */
  const STATUS_IDENTICAL = 'identical';

  /**
   * Resolution id does not equal current id.
   */
  const STATUS_MODIFIED_BY_TARGET = 'modified-by-target';

  /**
   * No resolution, no conflicts.
   */
  const STATUS_MODIFIED_BY_SOURCE = 'modified-by-source';

  /**
   * No resolution, conflicts are found.
   */
  const STATUS_CONFLICT = 'conflict';

  /**
   * No resolution, conflicts are found.
   */
  const STATUS_CONFLICT_AUTO = 'conflict-auto';

  /**
   * Deleted on both sides.
   */
  const STATUS_DELETED = 'deleted';

  /**
   * Unresolved, deleted on source side.
   */
  const STATUS_DELETED_BY_SOURCE = 'deleted-by-source';

  /**
   * Unresolved, deleted on target.
   */
  const STATUS_RESTORED_BY_SOURCE = 'restored-by-source';

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

  public function getStatusLabel(WorkspaceInterface $source, WorkspaceInterface $target, $status) {
    return [
      static::STATUS_IDENTICAL => t('Identical'),
      static::STATUS_NEW => t('Added by :source', [':source' => $source->label()]),
      static::STATUS_MODIFIED_BY_SOURCE => t('Modified by :source', [':source' => $source->label()]),
      static::STATUS_MODIFIED_BY_TARGET => t('Modified by :target', [':target' => $target->label()]),
      static::STATUS_CONFLICT => t('Conflict'),
      static::STATUS_CONFLICT_AUTO => t('Conflict (automatic)'),
      static::STATUS_DELETED => t('Deleted'),
      static::STATUS_DELETED_BY_SOURCE => t('Deleted by :source', [':source' => $source->label()]),
      static::STATUS_RESTORED_BY_SOURCE => t('Restored by :target', [':target' => $target->label()]),
    ][$status];
  }

  /**
   * Cached status.
   *
   * @var string
   */
  protected $status;
  protected function getEntityTypeManager() {
    return \Drupal::entityTypeManager();
  }

  /**
   *
   */
  public function getStatus() {
    if (!isset($this->status)) {
      $this->status = $this->calculateStatus();
    }
    $workspaceStorage = \Drupal::entityTypeManager()->getStorage('workspace');
    /** @var \Drupal\workspaces\WorkspaceInterface $sourceWorkspace */
    $sourceWorkspace = $workspaceStorage->load($this->getSourceWorkspace());
    /** @var \Drupal\workspaces\WorkspaceInterface $targetWorkspace */
    $targetWorkspace = $workspaceStorage->load($this->getTargetWorkspace());
    return [
      'status' => $this->status,
      'label' => $this->getStatusLabel($sourceWorkspace, $targetWorkspace, $this->status),
    ];
  }

  protected function calculateStatus() {
    $workspaceStorage = \Drupal::entityTypeManager()->getStorage('workspace');
    /** @var \Drupal\workspaces\WorkspaceInterface $sourceWorkspace */
    $sourceWorkspace = $workspaceStorage->load($this->getSourceWorkspace());
    /** @var \Drupal\workspaces\WorkspaceInterface $targetWorkspace */
    $targetWorkspace = $workspaceStorage->load($this->getTargetWorkspace());
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($this->getTargetType());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $sourceRevision */
    $sourceRevision = $storage->loadRevision($this->getSourceRevision());
    $resolution = $this->getResolution();

    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager */
    $workspaceManager = \Drupal::service('workspaces.manager');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $currentTargetRevision */
    $currentTargetRevision = $workspaceManager->executeInWorkspace(
      $this->getTargetWorkspace(),
      function () use ($sourceRevision) {
        /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
        $storage = \Drupal::entityTypeManager()->getStorage($sourceRevision->getEntityTypeId());
        $query = $storage->getQuery();
        $query->condition($sourceRevision->getEntityType()->getKey('id'), $sourceRevision->id());
        $query->addTag('workspace_sensitive');
        $result = array_keys($query->execute() ?? []);
        if ($result) {
          return $storage->loadRevision(reset($result));
        }
        return NULL;
      }
    );

    if (!$currentTargetRevision && $sourceRevision->deleted->value) {
      return static::STATUS_DELETED;
    }

    if (!$currentTargetRevision) {
      return static::STATUS_NEW;
    }

    if ($currentTargetRevision->deleted->value && $sourceRevision->deleted->value) {
      return static::STATUS_DELETED;
    }

    if ($resolution) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $targetRevision */
      $targetRevision = $storage->loadRevision($this->getResultRevision());
      if ($currentTargetRevision->getRevisionId() !== $targetRevision->getRevisionId()) {
        return static::STATUS_MODIFIED_BY_TARGET;
      }
      if (intval($resolution) === static::RESOLUTION_SOURCE) {
        return static::STATUS_IDENTICAL;
      }
      return static::STATUS_MODIFIED_BY_TARGET;
    }
    else {
      if ($sourceRevision->deleted->value) {
        return static::STATUS_DELETED_BY_SOURCE;
      }
      if ($currentTargetRevision->deleted->value) {
        return static::STATUS_RESTORED_BY_SOURCE;
      }
      /** @var \Drupal\revision_tree\EntityRevisionTreeHandlerInterface $revisionTreeHandler */
      $revisionTreeHandler = \Drupal::entityTypeManager()->getHandler($this->getTargetType(), 'revision_tree');
      $parentEntityRevision = $revisionTreeHandler->getLowestCommonAncestorId(
        $this->getSourceRevision(),
        $currentTargetRevision->getRevisionId(),
        $this->getTargetId()
      );
        /** @var \Drupal\Core\Entity\ContentEntityInterface $parentRevision */
      $parentRevision = $storage->loadRevision($parentEntityRevision);

      $targetPrimaryLanguage = $targetWorkspace->primary_language->value ?: \Drupal::languageManager()->getDefaultLanguage()->getId();
      $targetLanguages = [$targetPrimaryLanguage];
      foreach ($targetWorkspace->secondary_languages as $secondaryLanguage) {
        $targetLanguages[] = $secondaryLanguage->value;
      }
      $hadConflicts = FALSE;
      /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflictResolver */
      $conflictResolver = \Drupal::service('conflict.resolver.manager');

      if ($sourceRevision->isTranslatable()) {
        foreach ($currentTargetRevision->getTranslationLanguages() as $language) {
          $languageId = $language->getId();

          $sourceTranslation = $this->getTranslationForEntity(
            $sourceRevision,
            $languageId
          );
          $resultTranslation = $this->getTranslationForEntity(
            $currentTargetRevision,
            $languageId
          );
          $parentTranslation = $this->getTranslationForEntity(
            $parentRevision,
            $languageId
          );
          $targetTranslation = $this->getTranslationForEntity(
            $currentTargetRevision,
            $languageId
          );

          $context = new ParameterBag();
          $context->set('supported_languages', $targetLanguages);
          $context->set('status_check', TRUE);

          $conflicts = $conflictResolver->resolveConflicts(
            $targetTranslation,
            $sourceTranslation,
            $parentTranslation,
            $resultTranslation,
            $context
          );

          $hadConflicts = $hadConflicts || count($conflicts) > 0;
        }
      }
      if ($hadConflicts) {
        return static::STATUS_CONFLICT;
      }
      return static::STATUS_MODIFIED_BY_SOURCE;
    }
  }

  protected function getTranslationForEntity(TranslatableInterface $entity, $langcode) {
    return $entity->hasTranslation($langcode)
      ? $entity->getTranslation($langcode)
      : $entity->addTranslation($langcode);
  }
}
