<?php

/**
 * @file
 * Views integrations.
 */

/**
 * Implements hook_views_data_alter().
 */
function delivery_views_data_alter(array &$data) {
  $data['node_revision']['workspace'] = [
    'title' => t('Workspace ID'),
    'field' => [
      'title' => t('Workspace ID'),
      'help' => t('Displays the workspace ID for a node revision.'),
      'id' => 'node_revision_workspace',
    ],
  ];
  $data['node_revision']['workspace_filter'] = [
    'title' => t('Workspace ID filter'),
    'filter' => [
      'title' => t('Workspace ID filter'),
      'help' => t('Provides a filter for node revisions for their workspace.'),
      'field' => 'workspace',
      'id' => 'node_revision_workspace_filter',
    ],
  ];
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

  // Set the revision argument id handler to 'numeric', since the 'vid'
  // handler is broken.
  $data['node_field_revision']['vid']['argument']['id'] = 'numeric';

  $workspace_manager =  \Drupal::service('workspaces.manager');
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_types = $entity_type_manager->getDefinitions();

  // Provider delivery status fields and filters for each workspace-enabled
  // entity type.
  foreach ($entity_types as $type => $entity_type) {
    if (!$workspace_manager->isEntityTypeSupported($entity_type)) {
      continue;
    }

    $entity_revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
    $t_args = [
      '@entity_type' => $entity_type->getLabel(),
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

    // Add a filter for showing only the latest revisions of an entity in current workspace.
    $data[$entity_type->getRevisionTable()]['latest_workspace_revision'] = [
      'title' => t('Is Latest Revision in Current Workspace'),
      'help' => t('Restrict the view to only revisions that are the latest revision of their entity.'),
      'filter' => ['id' => 'latest_workspace_revision'],
    ];
  }

}
