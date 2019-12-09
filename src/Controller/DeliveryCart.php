<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DeliveryCart extends ControllerBase {

  /**
   * The private temp store.
   * @var PrivateTempStore
   */
  protected $userPrivateStore;

  /**
   * The workspace manager service.
   * @var WorkspaceManagerInterface
   */
  protected $workspaceManager;

  public function __construct(PrivateTempStoreFactory $tempStoreFactory, WorkspaceManagerInterface $workspaceManager) {
    $this->userPrivateStore = $tempStoreFactory->get('delivery');
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('workspaces.manager')
    );
  }

  /**
   * Adds the entity from the current request to the delivery cart.
   */
  public function addToCart(RouteMatchInterface $routeMatch, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    $entityDefinition = $this->entityTypeManager()->getDefinition($entity_type_id);
    $revisionIdField = $entityDefinition->getKey('revision');

    $cart = $this->userPrivateStore->get('delivery_cart');
    if (empty($cart)) {
      $cart = [];
    }
    $cart[$entity->getEntityTypeId()][$entity->id()] = [
      'entity_id' => $entity->id(),
      'revision_id' => $entity->{$revisionIdField}->getValue()[0]['value'],
      'workspace_id' => $this->workspaceManager->getActiveWorkspace()->id(),
    ];
    $this->userPrivateStore->set('delivery_cart', $cart);

    $this->messenger()->addStatus($this->t('@title has been added to the delivery cart.', ['@title' => $entity->label()]));
    $url = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.canonical', [$entity->getEntityTypeId() => $entity->id()])->toString();
    return new RedirectResponse($url);
  }

  /**
   * Access handler for the add to cart route.
   */
  public function addToCartAccess(RouteMatchInterface $routeMatch, AccountInterface $account, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    $cart = $this->userPrivateStore->get('delivery_cart');
    if (!empty($cart[$entity->getEntityTypeId()][$entity->id()])) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
  }

  /**
   * Removes the entity from the current request to the delivery cart.
   */
  public function removeFromCart(RouteMatchInterface $routeMatch, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    $cart = $this->userPrivateStore->get('delivery_cart');
    if (!empty($cart) && isset($cart[$entity->getEntityTypeId()][$entity->id()])) {
      unset($cart[$entity->getEntityTypeId()][$entity->id()]);
      // Check if there are any entries left for this entity type. If not, just
      // remove it.
      if (empty($cart[$entity->getEntityTypeId()])) {
        unset($cart[$entity->getEntityTypeId()]);
      }

      // If the cart is empty, we can just unset it from the private store.
      if (empty($cart)) {
        $this->userPrivateStore->delete('delivery_cart');
      }
      else {
        $this->userPrivateStore->set('delivery_cart', $cart);
      }
    }

    $this->messenger()->addStatus($this->t('@title has been removed from the delivery cart.', ['@title' => $entity->label()]));
    $url = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.canonical', [$entity->getEntityTypeId() => $entity->id()])->toString();
    return new RedirectResponse($url);
  }

  /**
   * Access handler for the remove from cart route.
   */
  public function removeFromCartAccess(RouteMatchInterface $routeMatch, AccountInterface $account, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    $cart = $this->userPrivateStore->get('delivery_cart');
    if (empty($cart[$entity->getEntityTypeId()][$entity->id()])) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
  }
}
