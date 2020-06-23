<?php

/**
 * @file
 * Views integrations.
 */

use Drupal\search_api\Entity\Index;

/**
 * Implements hook_views_data_alter().
 */
function delivery_views_data_alter(array &$data) {
  $data['delivery']['delivery_status'] = [
    'title' => t('Delivery status'),
    'field' => [
      'title' => t('Delivery status'),
      'help' => t('Displays the status of a delivery.'),
      'id' => 'delivery_delivery_status',
    ],
  ];
  $data['delivery']['delivery_active_workspace_filter'] = [
    'title' => t('Delivery active workspace'),
    'filter' => [
      'title' => t('Delivery active workspace'),
      'help' => t('Filters deliveries based on the active workspace ID.'),
      'id' => 'delivery_active_workspace_filter',
    ],
  ];

  $workspace_manager =  \Drupal::service('workspaces.manager');
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_types = $entity_type_manager->getDefinitions();

  // Provider delivery status fields and filters for each workspace-enabled
  // entity type.
  foreach ($entity_types as $type => $entity_type) {

    $is_search_api_index = $entity_type->id() === 'search_api_index';

    if (!$is_search_api_index && !$workspace_manager->isEntityTypeSupported($entity_type)) {
      continue;
    }

    $entity_tables = [];
    $entity_revision_tables = [];

    // If search_api_index, get all workspace revision indexes and set views data indexes.
    if ($is_search_api_index) {
      /** @var \Drupal\search_api\IndexInterface $index */
      foreach (Index::loadMultiple() as $index) {
        try {
          $datasource_ids = $index->getDatasourceIds();
          // We don't want to apply the following views data alteration
          // to all search api indexes, we're only targeting workspace content indexes.
          $workspace_datasources = preg_grep('/^workspaces_entity:.*/', $datasource_ids);

          if (!empty($workspace_datasources)) {
            $entity_tables[] = 'search_api_index_' . $index->id();
            $entity_revision_tables[] = NULL;
          }

        }
        catch (\Exception $e) {
          $args = [
            '%index' => $index->label(),
          ];
          watchdog_exception('delivery', $e, '%type while computing delivery Views data for index %index: @message in %function (line %line of %file).', $args);
        }
      }
    }
    else {
      // Normal content entities.
      $entity_tables[] = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
      $entity_revision_tables[] = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
    }

    $t_args = [
      '@entity_type' => $entity_type->getLabel(),
    ];

    foreach ($entity_tables as $index => $entity_table) {
      // Add a filter to ensure the views listing joins the workspace_association
      // table.

      if (isset($entity_revision_tables[$index]) && $entity_revision_tables !== NULL) {
        $data[$entity_table]['current_workspace_filter'] = [
          'title' => t('Current workspace revisions'),
          'help' => t('Adjust the view to display only entities with a revision in the current workspace.'),
          'filter' => ['id' => 'current_workspace_filter'],
        ];

        $data[$entity_table]['delivery_workspace_revision'] = [
          'title' => t('Workspace revision'),
          'help' => t('Relate to the current revision of the same entity in another workspace.'),
          'relationship' => [
            'id' => 'delivery_workspace_revision',
            'label' => t('Workspace revision'),
            'base' => $entity_revision_tables[$index],
          ],
        ];

        $data[$entity_revision_tables[$index]]['entity_delivery_status'] = [
          'title' => t('Delivery status for @entity_type', $t_args),
          'help' => t('Operate on the current delivery status of @entity_type entities.', $t_args),
          'field' => [
            'id' => 'entity_delivery_status',
            'label' => t('Delivery status field'),
          ],
          'filter' => [
            'id' => 'entity_delivery_status',
            'label' => t('Delivery status filter'),
          ],
        ];

      }

      // @TODO Check whether we have search api field with property path of "workspace" if this is search api index.
      // Add filter for inherited/own version content.
      $data[$entity_table]['inherited_content_filter'] = [
        'title' => t('Inherited'),
        'help' => t('Operate on whether the revision is inherited from another workspace.'),
        'field' => [
          'id' => 'inherited_content_field',
          'label' => t('Inherited'),
        ],
        'filter' => [
          'id' => 'inherited_content_filter',
          'label' => t('Inherited'),
        ],
      ];
    }


  }
}
