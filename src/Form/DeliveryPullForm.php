<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DeliveryForwardForm.
 *
 * @package Drupal\delivery\Form
 */
class DeliveryPullForm extends ConfirmFormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $deliveryService;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $messenger;

  /**
   * @var \Drupal\delivery\DeliveryInterface
   */
  protected $delivery;

  /**
   * @var \Drupal\workspaces\WorkspaceInterface
   */
  protected $workspace;

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
      $canForward = $canForward || $item->target_id === $workspace->id();
    }
    $result = AccessResult::allowedIf($canForward);
    $result->addCacheableDependency($delivery);
    $result->addCacheContexts(['workspace']);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryInterface $delivery = NULL) {
    $this->delivery = $delivery;
    $this->workspace = $this->deliveryService->getActiveWorkspace();
    $form_state->set('workspace_safe', TRUE);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Ensure we're not trying to pull changes to an inappropriate workspace.
    $targets = $this->deliveryService->getTargetWorkspacesFromDelivery($this->delivery);
    if (!in_array($this->workspace->id(), $targets)) {
      $form_state->setError($form['actions']['submit'], $this->t("The chosen workspace is not one of the delivery's target workspaces."));
    }
    // Ensure there are pending changes.
    if (!$this->deliveryService->deliveryHasPendingChanges($this->delivery)) {
      $form_state->setError($form['actions']['submit'], $this->t('This delivery has no pending updates to pull.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $skipped = $this->deliveryService->pullChangesFromDeliveryToWorkspace($this->delivery, $this->workspace);
      if ($skipped === 0) {
        $this->messenger->addStatus($this->t('Delivery updates pulled successfully.'));
      }
      else {
        $this->messenger->addWarning($this->t('%number items have been skipped due to conflicts. Please resolve them manually.', [
          '%number' => $skipped,
        ]));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('Something went wrong when pulling the updates.'));
    }
    // Redirect to the original delivery.
    $form_state->setRedirect('entity.delivery.canonical', ['delivery' => $this->delivery->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'confirm_delivery_pull_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // Go back to the delivery canonical.
    return new Url('entity.delivery.canonical', ['delivery' => $this->delivery->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Do you want to pull changes from delivery %id into the current workspace?', ['%id' => $this->delivery->id()]);
  }

}
