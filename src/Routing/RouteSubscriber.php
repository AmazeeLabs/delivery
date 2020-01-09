<?php

namespace Drupal\delivery\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\delivery\Controller\DeliveryListUsageController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 *
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config) {
    $this->entityTypeManager = $entity_manager;
    $this->config = $config;
  }

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

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      $route = $collection->get("entity.$entity_type_id.entity_usage");
      if ($route) {
        $route->setDefault(
          '_controller',
          DeliveryListUsageController::class . '::listUsageLocalTask'
        );
      }
    }

    // Alter the workspace activate route to add some more access checks.
    if ($route = $collection->get('entity.workspace.activate_form')) {
      $route->addRequirements(['_assigned_workspace_check' => 'TRUE']);
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
