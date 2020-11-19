<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\delivery\DeliveryCartService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryEmptyCartConfirmForm extends ConfirmFormBase {

  protected $deliveryCart;

  /**
   * DeliveryEmptyCartConfirmForm constructor.
   *
   * @param DeliveryCartService $delivery_cart
   */
  public function __construct(DeliveryCartService $delivery_cart) {
    $this->deliveryCart = $delivery_cart;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('delivery.cart')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to empty the delivery cart?');
  }

  /**
   * {@inheritDoc}
   */
  public function getCancelUrl(){
    return Url::fromRoute('delivery.cart');
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'delivery_empty_cart_confirm_form';
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->deliveryCart->emptyCart();
    $form_state->setRedirect('delivery.cart');
  }
}
