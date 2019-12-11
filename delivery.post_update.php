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
  $database = \Drupal::database();
  // Drop the menu tree table so it gets recreated with the new workspace field.
  $database->schema()->dropTable('menu_tree');
  // Mark all menu_link_content items to be rediscovered.
  if ($database->schema()->tableExists('menu_link_content_data')) {
    $database->update('menu_link_content_data')->fields([
      'rediscover' => TRUE,
    ])->execute();
  }
  $database->schema()->dropTable('menu_tree');
  // Rebuild the menu tree.
  \Drupal::service('plugin.manager.menu.link')->rebuild();
}

function delivery_post_update_deleted_field() {
  $entityTypeManager = \Drupal::entityTypeManager();
  $updateManager = \Drupal::entityDefinitionUpdateManager();


  foreach ($entityTypeManager->getDefinitions() as $entityType) {
    $baseFields = delivery_entity_base_field_info($entityType);
    if (array_key_exists('deleted', $baseFields)) {
      $revision_metadata_keys = $entityType->get('revision_metadata_keys');
      $revision_metadata_keys['deleted'] = 'deleted';
      $entityType->set('revision_metadata_keys', $revision_metadata_keys);
      $updateManager->updateEntityType($entityType);
      $updateManager->installFieldStorageDefinition('deleted', $entityType->id(), 'delivery', $baseFields['deleted']);
      \Drupal::database()->update($entityType->getRevisionTable())->fields(['deleted' => 0])->execute();
    }
  }
}
