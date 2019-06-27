<?php

namespace Drupal\delivery;

use Drupal\views\EntityViewsData;

/**
 * Views integration for the delivery entities.
 */
class DeliveryItemViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['delivery_item']['delivery_item_status'] = array(
      'title' => t('Delivery item status'),
      'field' => array(
        'title' => t('Delivery item status'),
        'help' => t('The status of the delivery item.'),
        'field' => 'result_revision',
        'id' => 'delivery_item_status',
      ),
    );
    $data['delivery_item']['delivery_item_label'] = array(
      'title' => t('Delivery item label'),
      'field' => array(
        'title' => t('Delivery item label'),
        'help' => t('The label of the delivery item.'),
        'field' => 'source_revision',
        'id' => 'delivery_item_label',
      ),
    );
    $data['delivery_item']['relevant_delivery_items'] = array(
      'title' => t('Relevant delivery items'),
      'filter' => array(
        'title' => t('Relevant delivery items'),
        'help' => t('Show only delivery items relevant for the active workspace.'),
        'field' => 'source_workspace',
        'id' => 'relevant_delivery_items',
      ),
    );
    return $data;
  }
}
