<?php

namespace Drupal\delivery\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class DeliveryRoutes implements ContainerInjectionInterface {

  /**
   * The workspace manager service.
   *
   * @var WorkspaceManagerInterface $workspaceManager
   */
  protected $workspaceManager;

  /**
   * DeliveryRoutes constructor.
   */
  public function __construct(WorkspaceManagerInterface $workspaceManager) {
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('workspaces.manager')
    );
  }

  /**
   * Returns the routes array.
   */
  public function routes() {
    // Add a toggle to cart route for every content type with workspaces
    // support.
    $routeCollection = new RouteCollection();
    foreach ($this->workspaceManager->getSupportedEntityTypes() as $entityType) {
      $addRoute = new Route(
        '/' . $entityType->id() . '/{' . $entityType->id() . '}/add_delivery_cart',
        [
          '_controller' => '\Drupal\delivery\Controller\DeliveryCart::addToCart',
          'entity_type_id' => $entityType->id(),
        ],
        [
          '_custom_access' => '\Drupal\delivery\Controller\DeliveryCart::addToCartAccess'
        ],
        [
          'parameters' => [
            $entityType->id() => [
              'type' => 'entity:' . $entityType->id(),
            ],
          ],
        ]
      );
      $removeRoute = new Route(
        '/' . $entityType->id() . '/{' . $entityType->id() . '}/remove_delivery_cart',
        [
          '_controller' => '\Drupal\delivery\Controller\DeliveryCart::removeFromCart',
          'entity_type_id' => $entityType->id(),
        ],
        [
          '_custom_access' => '\Drupal\delivery\Controller\DeliveryCart::removeFromCartAccess'
        ],
        [
          'parameters' => [
            $entityType->id() => [
              'type' => 'entity:' . $entityType->id(),
            ],
          ],
        ]
      );
      $routeCollection->add('entity.' . $entityType->id() . '.delivery_cart_add', $addRoute);
      $routeCollection->add('entity.' . $entityType->id() . '.delivery_cart_remove', $removeRoute);
    }
    return $routeCollection;
  }
}
