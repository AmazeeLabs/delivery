<?php

use Drupal\workspaces\Entity\Workspace;

function delivery_post_update_auto_push_field() {
  $entityTypeManager = \Drupal::entityTypeManager();
  $updateManager = \Drupal::entityDefinitionUpdateManager();

  $entityType = $entityTypeManager->getDefinition('workspace');
  $baseFields = delivery_entity_base_field_info($entityType);

  $updateManager->installFieldStorageDefinition('auto_push', 'workspace', 'delivery', $baseFields['auto_push']);
}
