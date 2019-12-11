<?php

namespace Drupal\delivery\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\delivery\DeliveryCartService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Action(
 *   id = "delivery_add_to_cart_action",
 *   label = @Translation("Add to the delivery cart"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
class DeliveryAddToCartAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The delivery cart service.
   * @var DeliveryCartService
   */
  protected $deliveryCart;


  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DeliveryCartService $deliveryCart) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->deliveryCart = $deliveryCart;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('delivery.cart')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowedIfHasPermission($account, 'add any entity to the delivery cart');
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Executes the plugin.
   */
  public function execute(EntityInterface $entity = NULL) {
    $this->deliveryCart->addToCart($entity);
  }
}
