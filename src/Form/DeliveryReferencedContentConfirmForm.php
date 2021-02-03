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

  protected $cartEntities = [];

  /**
   * DeliveryEmptyCartConfirmForm constructor.
   *
   * @param DeliveryCartService $delivery_cart
   */
  public function __construct(DeliveryCartService $delivery_cart) {
    $type = 'node';
    $this->deliveryCart = $delivery_cart;
    if($cartItems = $this->deliveryCart->getCart()) {
      $entityIds = $cartItems[$type] ?? [];
      $entityStorage = \Drupal::entityTypeManager()->getStorage($type);
      foreach ($entityIds as $entityIdData) {
        $sourceWorkspace = Workspace::load($entityIdData['workspace_id']);
        $entity = $entityStorage->loadRevision($entityIdData['revision_id']);
        $data = [
          'entity_type' => $entityStorage->getEntityType()->getLabel(),
          'entity_label' => $entity->label(),
          'workspace' => $sourceWorkspace->label(),
          'entity' => $entity
        ];
        $this->cart[] = $data;
      }
    }
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
    if(!$this->cart){
      return $this->t('Nothing has been found in that cart.');
    }

    $limit = 100;
    $items = [];
    foreach ($this->cart as $entity){
      if (count($items) >= $limit) {
        $items[] = $this->t('... and more');
        break;
      }
      $items[] = $this->t('@entity_type: %entity_label (Workspace: @workspace)', [
        '@entity_type' => $entity['entity_type'],
        '%entity_label' => $entity['entity_label'],
        '@workspace' => $entity['workspace'],]);
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

    foreach ($this->cart as $item) {
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
    DeliveryCartReferencedContent::addMediaItems($entity);
  }

}
