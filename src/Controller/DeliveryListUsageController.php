<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity_usage\Controller\LocalTaskUsageController;

/**
 * Customization of the ListUsageController, adding workspaces support.
 */
class DeliveryListUsageController extends LocalTaskUsageController {
  public function getTitleLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return parent::getTitle($entity->getEntityTypeId(), $entity->id());
  }

  public function listUsageLocalTask(RouteMatchInterface $route_match) {
    $entity = $this->getEntityFromRouteMatch($route_match);
    return $this->listUsagePage($entity->getEntityTypeId(), $entity->id());
  }

  public function listUsagePage($entity_type, $entity_id) {
    $all_rows = $this->getRows($entity_type, $entity_id);
    if (empty($all_rows)) {
      return [
        '#markup' => $this->t('There are no recorded usages for entity of type: @type with id: @id', ['@type' => $entity_type, '@id' => $entity_id]),
      ];
    }

    $header = [
      $this->t('Entity'),
      $this->t('Workspace'),
      $this->t('Type'),
      $this->t('Language'),
      $this->t('Status'),
    ];

    $total = count($all_rows);
    $page = pager_default_initialize($total, $this->itemsPerPage);
    $page_rows = $this->getPageRows($page, $this->itemsPerPage, $entity_type, $entity_id);
    $build[] = [
      '#theme' => 'table',
      '#rows' => $page_rows,
      '#header' => $header,
    ];

    $build[] = [
      '#type' => 'pager',
    ];
    return $build;
  }

  protected function getRows($entity_type, $entity_id) {
    if (!empty($this->allRows)) {
      return $this->allRows;
      // @todo Cache this based on the target entity, invalidating the cached
      // results every time records are added/removed to the same target entity.
    }
    $rows = [];
    /** @var  \Drupal\Core\Entity\ContentEntityStorageInterface $workspaceStorage */
    $workspaceStorage = $this->entityTypeManager->getStorage('workspace');
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      return $rows;
    }
    $entity_types = $this->entityTypeManager->getDefinitions();
    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $all_usages = $this->entityUsage->listSources($entity);
    foreach ($all_usages as $source_type => $ids) {
      $type_storage = $this->entityTypeManager->getStorage($source_type);
      foreach ($ids as $record) {
        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $type_storage->loadRevision($record['source_vid']);
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        $default_langcode = $source_entity->language()->getId();
        $link = $this->getSourceEntityLink($source_entity);
        // If the label is empty it means this usage shouldn't be shown
        // on the UI, just skip this row.
        if (empty($link)) {
          continue;
        }
        $published = $this->getSourceEntityStatus($source_entity);
        $field_label = isset($field_definitions[$records[0]['field_name']]) ? $field_definitions[$record['field_name']]->getLabel() : $this->t('Unknown');
        $rows[] = [
          $link,
          $workspaceStorage->load($record['workspace'])->label(),
          $entity_types[$source_type]->getLabel(),
          $languages[$default_langcode]->getName(),
          $published,
        ];
      }
    }

    $this->allRows = $rows;
    return $this->allRows;
  }
}
