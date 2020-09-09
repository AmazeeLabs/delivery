<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Drupal\delivery\Entity\DeliveryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryPushForm extends ConfirmFormBase {

  public static $BATCH_THRESHOLD = 2;

  /**
   * @var \Drupal\delivery\DeliveryInterface
   *  The delivery object.
   */
  protected $delivery;

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
    return $this->t('Are you sure you want to push the changes of the %title delivery?', ['%title' => $this->delivery->label()]);
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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryInterface $delivery = NULL) {
    $this->delivery = $delivery;

    $form = parent::buildForm($form, $form_state);
    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'operations' => [],
      'finished' => [$this, 'finishPushChanges'],
      'title' => $this->t('Push changes'),
      'progress_message' => $this->t('Pushing changes @current of @total.'),
      'error_message' => $this->t('Error pushing changes.'),
    ];

    $item_ids = [];
    foreach ($this->delivery->items as $item) {
      $item_ids[] = $item->target_id;
    }

    $batch['operations'][] = [
      [$this, 'pushDeliveryItem'], [$item_ids]
    ];

    $form_state->setRedirectUrl($this->getCancelUrl());
    batch_set($batch);
  }

  /**
   *
   */
  public function pushDeliveryItem($item_ids, &$context) {
    if (empty($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
    }
    $items = array_slice($item_ids, $context['sandbox']['progress'], self::$BATCH_THRESHOLD);
    if (!empty($items)) {
      foreach ($items as $item_id) {
        $deliveryItem = DeliveryItem::load($item_id);
        if (isset($deliveryItem->resolution->value)) {
          $context['sandbox']['progress']++;
          continue;
        }
        $this->deliveryService->acceptDeliveryItem($deliveryItem, 'published');
        $context['sandbox']['progress']++;
      }
    } else {
      $context['finished'] = 1;
    }

    if ($context['sandbox']['progress'] < count($item_ids)) {
      $context['finished'] = $context['sandbox']['progress'] / count($item_ids);
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   *
   */
  public function finishPushChanges($success, $results) {
    if ($success) {
      $this->messenger->addStatus($this->t('The changes have been pushed.'));
    }
    else {
      $this->messenger->addError($this->t('An error occurred trying to push the changes.'), 'error');
    }
  }
}
