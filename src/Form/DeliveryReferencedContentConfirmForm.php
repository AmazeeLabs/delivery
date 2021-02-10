<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\delivery\DeliveryCartReferencedContent;
use Drupal\delivery\DeliveryCartService;
use Drupal\workspaces\Entity\Workspace;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryReferencedContentConfirmForm extends ConfirmFormBase {

  protected $deliveryCart;

  protected $cart = [];

  /**
   * DeliveryEmptyCartConfirmForm constructor.
   *
   * @param DeliveryCartService $delivery_cart
   */
  public function __construct(DeliveryCartService $delivery_cart) {
    $this->deliveryCart = $delivery_cart;
    $this->cart = $this->getCartItems(100);
  }

  /**
   * Helper method to get x amount of items out of the cart
   *
   * @param int $limit
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCartItems(int $limit = 0) {
    $cart = [];

    if ($cartItems = $this->deliveryCart->getCart()) {
      foreach ($cartItems as $entity_type_id => $entityIds) {
        $entityStorage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
        foreach ($entityIds as $entityIdData) {
          if ($limit > 0 && count($cart) >= $limit) {
            break 2;
          }
          $sourceWorkspace = Workspace::load($entityIdData['workspace_id']);
          $entity = $entityStorage->loadRevision($entityIdData['revision_id']);
          $data = [
            'entity_type' => $entityStorage->getEntityType()->getLabel(),
            'entity_label' => $entity->label(),
            'workspace' => $sourceWorkspace->label(),
            'entity' => $entity,
          ];
          $cart[] = $data;
        }
      }
    }

    return $cart;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('delivery.cart')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Find referenced content');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->cart ? $this->t('Cancel') : $this->t('Back');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if(!$this->deliveryCart){
      return $this->t('Nothing has been found in that cart.');
    }

    $items = [];
    foreach ($this->cart as $entity){
      $items[] = $this->t('@entity_type: %entity_label (Workspace: @workspace)', [
        '@entity_type' => $entity['entity_type'],
        '%entity_label' => $entity['entity_label'],
        '@workspace' => $entity['workspace'],
      ]);
    }

    return $this->t('Will be searching the following to find any referenced content:') . '<br/><br/>'. implode("<br/>", $items);
  }

  /**
   * {@inheritDoc}
   */
  public function getQuestion() {
    return $this->t('Automatically find content for the following?');
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
    return 'delivery_referenced_content_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    if(!$this->cart){
      unset($form['actions']['submit']);
    }

    return $form;
  }

  /**
   * Submit on confirm.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'operations' => [],
      'title' => $this->t('Finding referenced content'),
      'progress_message' => $this->t('Finding referenced content @current of @total.'),
      'error_message' => $this->t('Error Finding referenced content.'),
    ];

    // gets all the cart items
    $cart = $this->getCartItems();

    foreach ($cart as $item) {
      $batch['operations'][] = [
        [$this, 'findReferencedContent'], [$item['entity']]
      ];
    }
    $form_state->setRedirect('delivery.cart');
    batch_set($batch);
  }

  /**
   * Batch runner.
   *
   * @param $entity
   * @param $context
   */
  public function findReferencedContent($entity, &$context) {
    DeliveryCartReferencedContent::addMenuItems($entity);
    DeliveryCartReferencedContent::addBlocksFromLayoutBuilder($entity);
    DeliveryCartReferencedContent::referenceContentHook($entity);
  }

}
