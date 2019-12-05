<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DeliveryCart extends ControllerBase {

  public function addToCart(RouteMatchInterface $routeMatch, $entity_type_id = NULL) {
    $entity = $routeMatch->getParameter($entity_type_id);
    drupal_set_message('Add: ' . $entity->getEntityTypeId() . ': ' . $entity->id());
    return new RedirectResponse('/' . $entity->getEntityTypeId() . '/'  . $entity->id());
  }

  public function addToCartAccess(AccountInterface $account, $entity_type_id = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
  }

  public function removeFromCart(RouteMatchInterface $routeMatch, $entity_type_id = NULL) {
    $entity = $routeMatch->getParameter($entity_type_id);
    drupal_set_message('Remove: ' . $entity->getEntityTypeId() . ': ' . $entity->id());
    return new RedirectResponse('/' . $entity->getEntityTypeId() . '/'  . $entity->id());
  }

  public function removeFromCartAccess(AccountInterface $account, $entity_type_id = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
  }
}
