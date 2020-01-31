<?php


namespace Drupal\workspaces_allowed_languages\Routing;

use Drupal\content_translation\Routing\ContentTranslationRouteSubscriber as ContentTranslationRouteSubscriberOriginal;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

class ContentTranslationRouteSubscriber extends ContentTranslationRouteSubscriberOriginal {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if ($route = $collection->get("entity.$entity_type_id.content_translation_overview")) {
        $route->setDefault('_controller', '\Drupal\workspaces_allowed_languages\Controller\ContentTranslationController::overview');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Should run after ContentTranslationRouteSubscriber and, if the class
    // exists, DeliveryContentTranslationRouteSubscriber. Therefore priority
    // -250.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -250];
    return $events;
  }

}
