<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\delivery\Entity\Delivery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to create a new delivery from all modified nodes and media entities.
 */
class DeliveryFromWorkspaceForm extends FormBase {

  /**
   * The entity type manager to create, load and save entities.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $workspaceStorage;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $deliveryStorage;

  /**
   * DeliveryFromWorkspaceForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->workspaceStorage = $entityTypeManager->getStorage('workspace');
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->deliveryStorage = $entityTypeManager->getStorage('delivery');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_from_workspace';
  }

  /**
   * Retrieve the forms page title.
   */
  public function title() {
    $source = $this->workspaceStorage->load($this->getRequest()->get('workspace'));
    return $this->t('Create delivery from %workspace', [
      '%workspace' => $source->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\workspaces\WorkspaceInterface $source */
    $source = $this->workspaceStorage->load($this->getRequest()->get('workspace'));
    if (!$source) {
      // TODO: Proper error handling.
      return;
    }

    /** @var \Drupal\workspaces\WorkspaceInterface $target */
    $target = $source->parent_workspace->entity;
    if (!$target) {
      // TODO: Proper error handling.
      return;
    }

    $form_state->set('source', $source);
    $form_state->set('target', $target);


    $form['label'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Label'),
      '#description' => $this->t('Choose a descriptive label for this delivery.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => $this->t('Description'),
      '#description' => $this->t('A more detailed description of this delivery.'),
    ];

    $form['pages'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Pages to be delivered'),
      'table' => views_embed_view('workspace_status_pages', 'embed', NULL, $source->id(), $target->id()),
    ];

    $form['media'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Media to be delivered'),
      'table' => views_embed_view('workspace_status_media', 'embed', NULL, $source->id(), $target->id()),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create delivery'),
    ];

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source = $form_state->get('source');
    $target = $form_state->get('target');

    $nodes = array_map(function($row) {
      return [
        'target_id' => $row->_entity->id(),
        'target_revision_id' => $row->source_revision,
      ];
    }, views_get_view_result('workspace_status_pages', 'embed', NULL,$source->id(), $target->id()));

    $media = array_map(function($row) {
      return [
        'target_id' => $row->_entity->id(),
        'target_revision_id' => $row->source_revision,
      ];
    }, views_get_view_result('workspace_status_media', 'embed', NULL, $source->id(), $target->id()));

    $values = [
      'label' => $form_state->getValue('label'),
      'description' => $form_state->getValue('description'),
      'source' => $source,
      'workspaces' => [$target],
      'nodes' => $nodes,
      'media' => $media,
    ];

    $delivery = Delivery::create($values);
    $delivery->save();
    $form_state->setRedirect('entity.delivery.canonical', [
      'delivery' => $delivery->id(),
    ]);
  }

}
