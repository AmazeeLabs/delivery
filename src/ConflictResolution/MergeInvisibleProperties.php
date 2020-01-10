<?php

namespace Drupal\delivery\ConflictResolution;

use Drupal\Core\Conflict\ConflictResolution\MergeStrategyBase;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Automatically resolve conflicts in invisible fields.
 *
 * Invisible is defined by not being configured in a "merge" view mode.
 *
 * @package Drupal\delivery\ConflictResolution
 */
class MergeInvisibleProperties extends MergeStrategyBase {

  public function getMergeStrategyId(): string {
    return 'conflict_resolution.merge_invisible_fields';
  }

  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    $local_entity = $event->getLocalEntity();
    $remote_entity = $event->getRemoteEntity();
    $result_entity = $event->getResultEntity();

    $automerge = array_keys($event->getConflicts());

    // Enforce the moderation state to jump back to "draft" in the new workspace
    // and make sure it's not registered as a conflict any more.
    if ($result_entity->hasField('moderation_state')) {
      $result_entity->set('moderation_state', 'draft');
      if (in_array('moderation_state', $automerge)) {
        $event->removeConflict('moderation_state');
        $automerge = array_filter($automerge, function ($conflict) {
          return $conflict !== 'moderation_state';
        });
      }
    }

    // For now, the layout builder conflicts will be removed by default, so the
    // 'left' version will be used.
    if (in_array('layout_builder__layout', $automerge)) {
      $event->removeConflict('layout_builder__layout');
      $automerge = array_filter($automerge, function ($conflict) {
        return $conflict !== 'layout_builder__layout';
      });
    }

    // If the current language is in the list of languages supported by the
    // target workspace, remove these fields from the automerge list. Else
    // just merge from left to right.
    $supportedLanguages = $event->getContextParameter('supported_languages');
    if (in_array($result_entity->language()->getId(), $supportedLanguages) && $local_entity instanceof FieldableEntityInterface) {
      $viewDisplay = EntityViewDisplay::collectRenderDisplay($local_entity, 'merge');
      $formDisplay = EntityFormDisplay::collectRenderDisplay($local_entity, 'merge');
      $resolvable = array_merge(array_keys($viewDisplay->getComponents()), array_keys($formDisplay->getComponents()));
      $automerge = array_diff($automerge, $resolvable);
    }

    foreach ($automerge as $property) {
      $result_entity->set($property, $remote_entity->get($property)->getValue());
      $event->removeConflict($property);
    }

    if ($input = $event->getContextParameter('resolution_form_result')) {
      $custom = $event->getContextParameter('resolution_custom_values');
      foreach ($input as $property => $selection) {
        if ($selection === '__source__') {
          $result_entity->set($property, $remote_entity->get($property)->getValue());
          $event->removeConflict($property);
        }

        if ($selection === '__target__') {
          $result_entity->set($property, $local_entity->get($property)->getValue());
          $event->removeConflict($property);
        }

        if ($selection === '__custom__' && isset($custom[$property])) {
          $result_entity->set($property, $custom[$property]);
          $event->removeConflict($property);
        }
      }
    }
  }

}
