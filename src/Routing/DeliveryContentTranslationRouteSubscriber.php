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

      if ($entity_type->hasLinkTemplate('drupal:content-translation-add')) {
        $route = $collection->get("entity.$entity_type_id.content_translation_add");
        $route->setRequirement('_entity_access', str_replace('view', 'update', $route->getRequirement('_entity_access')));
      }

      if ($entity_type->hasLinkTemplate('drupal:content-translation-edit')) {
        $route = $collection->get("entity.$entity_type_id.content_translation_edit");
        $route->addRequirements(['_entity_access' => $entity_type_id . '.update']);
      }

      if ($entity_type->hasLinkTemplate('drupal:content-translation-delete')) {
        $route = $collection->get("entity.$entity_type_id.content_translation_delete");
        $route->addRequirements(['_entity_access' => $entity_type_id . '.update']);
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
