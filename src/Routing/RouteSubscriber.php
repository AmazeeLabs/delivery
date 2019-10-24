<?php

namespace Drupal\delivery\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 *
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change the NodeController for all the routes that use it. We have to
    // change the logic of the revision overview page when having the revision
    // tree enabled.
    foreach ($collection as $item) {
      $controller = $item->getDefault('_controller');
      if (strpos($controller, '\Drupal\node\Controller\NodeController') === 0) {
        $controller = str_replace('\Drupal\node\Controller\NodeController', '\Drupal\delivery\Controller\NodeController', $controller);
        $item->setDefault('_controller', $controller);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    // Should run after AdminRouteSubscriber so the routes can inherit admin
    // status of the edit routes on entities. Therefore priority -210.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -215];
    return $events;
  }

}
