<?php


/**
 * Add the auto-push property to workspaces.
 */
function delivery_post_update_auto_push_field() {
  $entityTypeManager = \Drupal::entityTypeManager();
  $updateManager = \Drupal::entityDefinitionUpdateManager();

  $entityType = $entityTypeManager->getDefinition('workspace');
  $baseFields = delivery_entity_base_field_info($entityType);

  $updateManager->installFieldStorageDefinition('auto_push', 'workspace', 'delivery', $baseFields['auto_push']);
}

/**
 * Make tree relevant menu item properties revisionable.
 */
function delivery_post_update_revisionable_menu_tree(&$sandbox) {
  $updateManager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $lastInstalledSchemaRepository */
  $lastInstalledSchemaRepository = \Drupal::service('entity.last_installed_schema.repository');

  $fields = $lastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions('menu_link_content');

  $revisionableFields = [
    'weight',
    'expanded',
    'enabled',
    'parent',
  ];

  foreach ($revisionableFields as $revisionableField) {
    $fields[$revisionableField]->setRevisionable(TRUE);
  }

  $updateManager->updateFieldableEntityType($updateManager->getEntityType('menu_link_content'), $fields, $sandbox);
}

/**
 * Make the menu tree index workspace sensitive.
 */
function delivery_post_update_add_menu_tree_per_workspace() {
  // Drop the menu tree table so it gets recreated with the new workspace field.
  \Drupal::database()->schema()->dropTable('menu_tree');
  // Rebuild the menu tree.
  \Drupal::service('plugin.manager.menu.link')->rebuild();
}
