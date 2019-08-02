<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Drupal\delivery\Entity\Delivery;
use Drupal\delivery\Entity\DeliveryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryItemPushForm extends ConfirmFormBase {

  public static $BATCH_THRESHOLD = 10;

  /**
   * @var \Drupal\delivery\DeliveryInterface
   *  The delivery object.
   */
  protected $delivery;

  /**
   * @var \Drupal\delivery\Entity\DeliveryItem
   */
  protected $deliveryItem;

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $sourceEntity;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *  The entity type manager service.
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *  The entity repository service.
   */
  protected $entityRepository;

  /**
   * @var \Drupal\delivery\DeliveryService
   */
  protected $deliveryService;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   *  The messenger service.
   */
  protected $messenger;

  /**
   * DeliveryPushConfirmFom constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * @param \Drupal\delivery\DeliveryService $deliveryService
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entity_repository,
    DeliveryService $deliveryService
  ) {
    $this->deliveryService = $deliveryService;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get('delivery.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to push the changes of the %title item?', [
      '%title' => $this->sourceEntity->label()
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getCancelUrl() {
    return $this->delivery->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_push_changes';
  }

  public function access(AccountInterface $account, Delivery $delivery, DeliveryItem $delivery_item) {
    if (isset($delivery->resolution->value)) {
      return AccessResult::forbidden();
    }
    $sourceEntity = $this->entityTypeManager
      ->getStorage($delivery_item->getTargetType())
      ->loadRevision($delivery_item->getSourceRevision());
    return $sourceEntity->access('update', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryInterface $delivery = NULL, $delivery_item = NULL) {
    $this->delivery = $delivery;
    $this->deliveryItem = $delivery_item;
    $this->sourceEntity = $this->entityTypeManager
      ->getStorage($this->deliveryItem->getTargetType())
      ->loadRevision($this->deliveryItem->getSourceRevision());

    $form = parent::buildForm($form, $form_state);
    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->deliveryService->acceptDeliveryItem($this->deliveryItem);
    $this->messenger->addStatus($this->t('The changes have been imported.'));
  }

  /**
   *
   */
  public function finishPushChanges($success, $results) {
    if ($success) {
    }
    else {
      $this->messenger->addError($this->t('An error occurred trying to push the changes.'), 'error');
    }
  }
}
