<?php

namespace Drupal\delivery;

use Drupal\menu_link_content\MenuLinkContentStorage as OriginalMenuLinkContentStorage;

class MenuLinkContentStorage extends OriginalMenuLinkContentStorage {

  /**
   * We assume that there are never pending revisions since we are able to
   * resolve the conflict later.
   *
   * @return array|int[]
   */
  public function getMenuLinkIdsWithPendingRevisions() {
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager */
    $workspace_manager = \Drupal::service('workspaces.manager');
    if (!$workspace_manager->getActiveWorkspace()) {
      return parent::getMenuLinkIdsWithPendingRevisions();
    }
    return [];
  }

}
