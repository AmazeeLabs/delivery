<?php

namespace Drupal\delivery\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
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

}
