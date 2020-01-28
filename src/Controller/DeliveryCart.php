<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\delivery\DeliveryCartService;
use Drupal\workspaces\Entity\Workspace;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DeliveryCart extends ControllerBase {

  /**
   * The delivery cart serivice.
   * @var DeliveryCartService
   */
  protected $deliveryCart;

  public function __construct(DeliveryCartService $deliveryCart) {
    $this->deliveryCart = $deliveryCart;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('delivery.cart')
    );
  }

  /**
   * Adds the entity from the current request to the delivery cart.
   */
  public function addToCart(RouteMatchInterface $routeMatch, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    $this->deliveryCart->addToCart($entity);
    $this->messenger()->addStatus($this->t('@title has been added to the delivery <a href=":cart_link">cart</a>.', ['@title' => $entity->label(), ':cart_link' => Url::fromRoute('delivery.cart')->toString()]));
    $url = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.canonical', [$entity->getEntityTypeId() => $entity->id()])->toString();
    return new RedirectResponse($url);
  }

  /**
   * Access handler for the add to cart route.
   */
  public function addToCartAccess(RouteMatchInterface $routeMatch, AccountInterface $account, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    if ($this->deliveryCart->entityExistsInCart($entity)) {
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
    $this->deliveryCart->removeFromCart($entity);
    $this->messenger()->addStatus($this->t('@title has been removed from the delivery <a href=":cart_link">cart</a>.', ['@title' => $entity->label(), ':cart_link' => Url::fromRoute('delivery.cart')->toString()]));
    $url = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.canonical', [$entity->getEntityTypeId() => $entity->id()])->toString();
    return new RedirectResponse($url);
  }

  /**
   * Access handler for the remove from cart route.
   */
  public function removeFromCartAccess(RouteMatchInterface $routeMatch, AccountInterface $account, $entity_type_id) {
    /* @var EntityInterface $entity */
    $entity = $routeMatch->getParameter($entity_type_id);
    if (!$this->deliveryCart->entityExistsInCart($entity)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
  }

  /**
   * Returns an overview of the current cart.
   */
  public function overview() {
    $cart = $this->deliveryCart->getCart();
    $items = [];
    if (!empty($cart)) {
      foreach ($cart as $entity_type_id => $entity_ids) {
        $entityStorage = $this->entityTypeManager()->getStorage($entity_type_id);
        foreach ($entity_ids as $entity_id_data) {
          // @todo: Temporary fix for avoiding a timeout when having a lot of
          // entries in the cart, until we get a proper pagination.
          if (count($items) >= 100) {
            $items[] = $this->t('... and more');
            break 2;
          }
          $sourceWorkspace = Workspace::load($entity_id_data['workspace_id']);
          $entity = $entityStorage->loadRevision($entity_id_data['revision_id']);

          $items[] = $this->t('@entity_type: %entity_label (Workspace: @workspace) - <a href=":delivery_cart_remove">Remove</a>', [
            '@entity_type' => $entityStorage->getEntityType()->getLabel(),
            '%entity_label' => $entity->label(),
            '@workspace' => $sourceWorkspace->label(),
            ':delivery_cart_remove' => Url::fromRoute('entity.' . $entity_type_id . '.delivery_cart_remove', [$entity_type_id => $entity->id()], ['query' => \Drupal::destination()->getAsArray()])->toString(),
          ]);
        }
      }
    }
    if (!empty($items)) {
      $build['cart'] = [
        '#theme' => 'item_list',
        '#title' => t('Cart overview'),
        '#items' => $items,
      ];
    } else {
      $build['cart'] = [
        '#markup' => $this->t('You have no items in the delivery cart. To add content, just navigate to the view page of a content and click on the <em>Add to the delivery cart</em> tab.'),
      ];
    }
    return $build;
  }
}
