<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\delivery\Entity\Delivery;
use Drupal\delivery\Entity\DeliveryItem;
use Drupal\workspaces\WorkspaceInterface;
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
  protected $deliveryStorage;

  /**
   * @var
   */
  protected $database;

  /**
   * DeliveryFromWorkspaceForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entityTypeManager, Connection $database) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->workspaceStorage = $entityTypeManager->getStorage('workspace');
    $this->deliveryStorage = $entityTypeManager->getStorage('delivery');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'), $container->get('database'));
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
    $target = $source->parent->entity;
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

    $form['items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Delivery items'),
      '#description' => $this->t('Choose which content items should be delivered.'),
      '#options' => [],
    ];

    $modifications = $this->getModifiedEntities($source, $target);
    foreach ($modifications as $item) {
      $entity = $this->entityTypeManager->getStorage($item->target_entity_type)->loadRevision($item->source_revision);
      $key = implode(':', [
        $item->target_entity_type,
        $item->target_entity_id,
        $item->source_revision,
      ]);
      $form['items']['#default_value'][$key] = $key;
      $form['items']['#options'][$key] = $entity ? $entity->label() : $this->t('Corresponding content not found');
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create delivery'),
    ];

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  protected function getModifiedEntities(WorkspaceInterface $source, WorkspaceInterface $target) {
    $query = $this->database->select('workspace_association', 'source');

    $query->addField('source', 'target_entity_id', 'target_entity_id');
    $query->addField('source', 'target_entity_type_id', 'target_entity_type');
    $query->addField('source', 'target_entity_revision_id', 'source_revision');
    $query->addField('target', 'target_entity_revision_id', 'target_revision');

    $query->leftJoin('workspace_association', 'target',
      'source.target_entity_id = target.target_entity_id and source.target_entity_type_id = target.target_entity_type_id and target.workspace = :target',
      [':target' => $target->id()]
    );

    $query->where('source.workspace = :source and (source.target_entity_revision_id != target.target_entity_revision_id or target.target_entity_revision_id is null)', [':source' => $source->id()]);
    return $query->execute()->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source = $form_state->get('source');
    $target = $form_state->get('target');

    $values = [
      'label' => $form_state->getValue('label'),
      'description' => $form_state->getValue('description'),
      'source' => $source,
      'workspaces' => [$target],
      'items' => [],
    ];
    $items = array_filter($form_state->getValue('items'));

    foreach ($items as $row) {
      list($entity_type, $entity_id, $revision_id) = explode(':', $row);
      $item = DeliveryItem::create([
        'source_workspace' => $source->id(),
        'target_workspace' => $target->id(),
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'source_revision' => $revision_id,
      ]);
      $item->save();
      $values['items'][] = $item;
    }

    $delivery = Delivery::create($values);
    $delivery->save();
    $form_state->setRedirect('entity.delivery.canonical', [
      'delivery' => $delivery->id(),
    ]);
  }

}
