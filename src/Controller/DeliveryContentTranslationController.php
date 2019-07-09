<?php

namespace Drupal\delivery\Controller;

use Drupal;
use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\Core\Routing\RouteMatchInterface;

class DeliveryContentTranslationController extends ContentTranslationController {

  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = parent::overview(
      $route_match,
      $entity_type_id
    );
    return $build;
  }

  /**
   * Returns the language manager service.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  protected function languageManager() {
    if (!$this->languageManager) {
      $this->languageManager = Drupal::service('delivery.filtered_language_manager');
    }
    return $this->languageManager;
  }

}
