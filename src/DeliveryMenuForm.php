<?php

namespace Drupal\delivery;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\menu_link_content\MenuLinkContentStorageInterface;
use Drupal\menu_ui\MenuForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryMenuForm extends MenuForm {

  protected $deliveryCart;

  /**
   * {@inheritDoc}
   */
  public function __construct(MenuLinkManagerInterface $menu_link_manager, MenuLinkTreeInterface $menu_tree, LinkGeneratorInterface $link_generator, MenuLinkContentStorageInterface $menu_link_content_storage, DeliveryCartService $delivery_cart) {
    parent::__construct($menu_link_manager, $menu_tree, $link_generator, $menu_link_content_storage);
    $this->deliveryCart = $delivery_cart;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.link_tree'),
      $container->get('link_generator'),
      $container->get('entity_type.manager')->getStorage('menu_link_content'),
      $container->get('delivery.cart')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // We want to have the same button at the top of the menu tree and at the
    // bottom. The top one we just add it here. The bottom one we add it in the
    // action buttons area (see the ::actions method).
    $form['add_to_delivery_cart_top'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add all items to the delivery cart'),
      '#submit' => ['::addToDeliveryCart'],
      '#weight' => 5,
    ];
    $form['links']['#weight'] = 10;
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['add_to_delivery_cart'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add all items to the delivery cart'),
      '#submit' => ['::addToDeliveryCart'],
    ];
    return $actions;
  }

  public function addToDeliveryCart(array &$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    foreach ($form_state->getValue('links') as $link) {
      $uuid = str_replace('menu_link_content:', '', $link['id']);
      $entities = $storage->loadByProperties(['uuid' => $uuid]);
      $entity = reset($entities);
      $this->deliveryCart->addToCart($entity);
    }
    $this->messenger()->addStatus($this->t('The menu items have been added to the delivery <a href=":cart_link">cart</a>.', [':cart_link' => Url::fromRoute('delivery.cart')->toString()]));
  }
}
