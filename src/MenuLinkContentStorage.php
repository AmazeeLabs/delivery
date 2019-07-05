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
    return [];
  }

}
