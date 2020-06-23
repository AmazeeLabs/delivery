<?php

namespace Drupal\delivery;

use Drupal;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageManager;
use Drupal\workspaces\WorkspaceManagerInterface;

class FilteredLanguageManager extends ConfigurableLanguageManager {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @return WorkspaceManagerInterface
   */
  protected function getWorkspaceManager() {
    return Drupal::service('workspaces.manager');
  }

  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE) {
    $workspace = $this->getWorkspaceManager()->getActiveWorkspace();

    if (!empty($workspace->primary_language) && $workspace->primary_language->count() === 0) {
      return parent::getLanguages($flags);
    }

    $languages = [];
    if (!empty($workspace->primary_language)) {
      $languages = [$workspace->primary_language->value];
    }
    if (!empty($workspace->secondary_languages)) {
      foreach ($workspace->secondary_languages as $item) {
        $languages[] = $item->value;
      }
    }

    $l = parent::getLanguages($flags);
    $result = array_filter($l, function (LanguageInterface $lang) use ($languages) {
      return in_array($lang->getId(), $languages);
    });
    return $result;
  }

}
