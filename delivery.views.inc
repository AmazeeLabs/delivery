<?php

/**
 * @file
 * Views integrations.
 */

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
    if (!$workspace_manager->isEntityTypeSupported($entity_type)) {
      continue;
    }

    $entity_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $entity_revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
    $t_args = [
      '@entity_type' => $entity_type->getLabel(),
    ];

    // Add a filter to ensure the views listing joins the workspace_association
    // table.
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
        'base' => $entity_revision_table,
      ],
    ];

    $data[$entity_revision_table]['entity_delivery_status'] = [
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

    // Add filter for inherited/own version content
    $data[$entity_table]['inherited_content_filter'] = [
      'title' => t('Inherited flag'),
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
