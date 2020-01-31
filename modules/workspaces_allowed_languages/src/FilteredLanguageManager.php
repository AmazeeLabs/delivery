<?php

namespace Drupal\workspaces_allowed_languages;

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

  /**
   * {@inheritDoc}
   */
  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE, $ignore_allowed_languages = FALSE) {
    if (!empty($ignore_allowed_languages)) {
      return parent::getLanguages($flags);
    }
    $workspace = $this->getWorkspaceManager()->getActiveWorkspace();

    if ($workspace->primary_language->count() === 0) {
      return parent::getLanguages($flags);
    }

    $languages = [$workspace->primary_language->value];
    foreach ($workspace->secondary_languages as $item) {
      $languages[] = $item->value;
    }

    $l = parent::getLanguages($flags);
    $result = array_filter($l, function (LanguageInterface $lang) use ($languages) {
      return in_array($lang->getId(), $languages);
    });
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function isMultilingual() {
    return count($this->getLanguages(LanguageInterface::STATE_CONFIGURABLE, TRUE)) > 1;
  }

}
