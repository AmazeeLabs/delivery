<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class DeliveryForwardForm
 *
 * @package Drupal\delivery\Form
 */
class DeliveryForwardForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $deliveryService;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $messenger;

  /**
   * DeliveryForwardForm constructor.
   *
   * @param \Drupal\delivery\DeliveryService $delivery_service
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(DeliveryService $delivery_service, MessengerInterface $messenger) {
    $this->deliveryService = $delivery_service;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('delivery.service'),
      $container->get('messenger')
    );
  }

  public function access(AccountInterface $account, DeliveryInterface $delivery) {
    $workspace = $this->deliveryService->getActiveWorkspace();
    $canForward = FALSE;
    foreach($delivery->workspaces as $item) {
      $canForward = $canForward || ($workspace && $item->target_id === $workspace->id());
    }
    $result = AccessResult::allowedIf($canForward);
    $result->addCacheableDependency($delivery);
    $result->addCacheContexts(['workspace']);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_forward_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryInterface $delivery = NULL) {
    if (!$delivery) {
      throw new NotFoundHttpException();
    }
    // @todo Make this warning more specific and accurate.
    if (!$can_forward = $this->deliveryService->canForwardDelivery($delivery)) {
      $this->messenger->addWarning($this->t('This delivery has conflicts or pending changes and cannot be forwarded.'));
    }
    $form_state->addBuildInfo('delivery', $delivery);
    $form['target_workspace_id'] = [
      '#type' => 'checkboxes',
      '#options' => $this->deliveryService->getTargetWorkspaces(),
      '#title' => $this->t('Target workspace'),
      '#required' => TRUE,
      '#weight' => 1,
      '#disabled' => !$can_forward,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Forward'),
      '#weight' => 2,
      '#disabled' => !$can_forward,
    ];

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if (!$this->deliveryService->canForwardDelivery($form_state->getBuildInfo()['delivery'])) {
      $form_state->setError($form['target_workspace_id'], $this->t('This delivery has conflicts or pending changes and cannot be forwarded.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $delivery = $form_state->getBuildInfo()['delivery'];
    $target_workspace_ids = $form_state->getValue('target_workspace_id');
    $source_workspace_id = $form_state->getValue('source_workspace_id');
    $forwarded = $this->deliveryService->forwardDelivery($delivery, $target_workspace_ids, $source_workspace_id);
    $this->messenger->addMessage($this->t('Delivery :delivery_title forwarded.', [':delivery_title' => $forwarded->label()]));
    $form_state->setRedirect('entity.delivery.canonical', ['delivery' => $forwarded->id()]);
  }

}
