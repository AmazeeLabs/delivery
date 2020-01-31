<?php

namespace Drupal\workspaces_allowed_languages\Controller;

class ContentTranslationController extends DynamicContentTranslationController {

  /**
   * {@inheritDoc}
   */
  protected function languageManager() {
    if (!$this->languageManager) {
      $this->languageManager = \Drupal::service('workspaces_allowed_languages.filtered_language_manager');
    }
    return $this->languageManager;
  }
}
