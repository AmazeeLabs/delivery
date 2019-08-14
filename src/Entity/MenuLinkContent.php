<?php

namespace Drupal\delivery\Entity;

use Drupal\Core\Entity\EntityStorageInterface;

class MenuLinkContent extends \Drupal\menu_link_content\Entity\MenuLinkContent {

  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Make sure to associate before running postSave so workspace associations
    // are up to date before the menu tree rebuild.
    \Drupal::service('workspaces.association')->trackEntity($this, $this->workspace->entity ?: \Drupal::service('workspaces.manager')->activeWorkspace());
    return parent::postSave($storage, $update);
  }

}
