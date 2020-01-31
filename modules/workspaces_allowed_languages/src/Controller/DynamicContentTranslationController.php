<?php

namespace Drupal\workspaces_allowed_languages\Controller;

if(class_exists('Drupal\delivery\Controller\DeliveryContentTranslationController')) {
  class DynamicContentTranslationController extends \Drupal\delivery\Controller\DeliveryContentTranslationController {}
} else {
  class DynamicContentTranslationController extends \Drupal\content_translation\Controller\ContentTranslationController {}
}
