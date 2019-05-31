<?php

namespace Drupal\delivery;

use Drupal\views\EntityViewsData;

/**
 * Views integration for the delivery entities.
 */
class DeliveryViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // The target and source workspaces filters should show the available
    // workspaces as a list.
    $data['delivery__workspaces']['workspace_filter_list'] = [
      'title' => t('Target workspaces (list)'),
      'filter' => [
        'title' => t('Target workspaces (list)'),
        'help' => t('Available workspaces filters shown as a list.'),
        'field' => 'workspaces_target_id',
        'id' => 'delivery_workspaces_list',
      ],
    ];

    $data['delivery']['workspace_source_list'] = [
      'title' => t('Source workspaces (list)'),
      'filter' => [
        'title' => t('Source workspaces (list)'),
        'help' => t('Available workspaces filters shown as a list.'),
        'field' => 'source',
        'id' => 'delivery_workspaces_list',
      ],
    ];

    return $data;
  }

}
