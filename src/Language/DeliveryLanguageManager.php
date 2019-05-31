<?php

namespace Drupal\delivery\Language;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\ConfigurableLanguageManager;

class DeliveryLanguageManager extends ConfigurableLanguageManager {

  protected $loadingWorkspace = FALSE;
  protected $workspaceDefaultLanguage;
  protected $workspaceLanguages = [];

  /**
   * @return \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected function getWorkspacesManager() {
    return \Drupal::service('workspaces.manager');
  }

  public function getDefaultLanguage() {
    if ($this->loadingWorkspace) {
      return parent::getDefaultLanguage();
    }

    // If a config override is set, cache using that language's ID.
    if ($override_language = $this->getConfigOverrideLanguage()) {
      $static_cache_id = $override_language->getId();
    }
    else {
      $static_cache_id = $this->getCurrentLanguage()->getId();
    }

    if (!isset($this->workspaceDefaultLanguage)) {
      $this->loadingWorkspace = TRUE;
      $workspace = $this->getWorkspacesManager()->getActiveWorkspace();
      $this->loadingWorkspace = FALSE;
      if (isset($workspace->primary_language) && $default = $workspace->primary_language->value) {
        $this->workspaceDefaultLanguage = $this->languages[$static_cache_id][LanguageInterface::STATE_CONFIGURABLE][$default];
      }
      else {
        $this->workspaceDefaultLanguage = parent::getDefaultLanguage();
      }
    }

    return $this->workspaceDefaultLanguage;
  }

  public function getLanguages($flags = LanguageInterface::STATE_CONFIGURABLE) {
    if ($this->loadingWorkspace) {
      return parent::getLanguages($flags);
    }

    // If a config override is set, cache using that language's ID.
    if ($override_language = $this->getConfigOverrideLanguage()) {
      $static_cache_id = $override_language->getId();
    }
    else {
      $static_cache_id = $this->getCurrentLanguage()->getId();
    }

    if (!isset($this->workspaceLanguages[$static_cache_id])) {
      $this->workspaceLanguages[$static_cache_id] = [];
    }

    if (!isset($this->workspaceLanguages[$static_cache_id][$flags])) {
      $this->loadingWorkspace = TRUE;
      $workspace = $this->getWorkspacesManager()->getActiveWorkspace();
      $this->loadingWorkspace = FALSE;

      $whiteList = [];
      foreach ($workspaceLanguage = $workspace->primary_language as $item) {
        $whiteList[] = $item->value;
      }

      foreach ($workspaceLanguage = $workspace->secondary_languages as $item) {
        $whiteList[] = $item->value;
      }

      $languages = parent::getLanguages($flags);

      if ($whiteList) {
        $this->workspaceLanguages[$static_cache_id][$flags] = array_filter($languages, function (LanguageInterface $lang) use ($whiteList) {
          return in_array($lang->getId(), $whiteList);
        });
      }
      else {
        $this->workspaceLanguages[$static_cache_id][$flags] = $languages;
      }
    }

    return $this->workspaceLanguages[$static_cache_id][$flags];
  }

}
