<?php
namespace Drupal\delivery\Routing;

use Drupal\content_translation\Routing\ContentTranslationRouteSubscriber;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class DeliveryContentTranslationRouteSubscriber extends ContentTranslationRouteSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->contentTranslationManager->getSupportedEntityTypes() as $entity_type_id => $entity_type) {
      if ($route = $collection->get("entity.$entity_type_id.content_translation_overview")) {
        $route->setDefault('_controller', '\Drupal\delivery\Controller\DeliveryContentTranslationController::overview');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Should run after ContentTranslationRouteSubscriber. Therefore priority -220.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }


}
