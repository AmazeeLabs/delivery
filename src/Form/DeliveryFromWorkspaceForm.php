<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\delivery\Entity\Delivery;
use Drupal\delivery\Entity\DeliveryItem;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * DeliveryFromWorkspaceForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The module's logger channel.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManager $entityTypeManager, Connection $database, LoggerChannelInterface $loggerChannel) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->workspaceStorage = $entityTypeManager->getStorage('workspace');
    $this->deliveryStorage = $entityTypeManager->getStorage('delivery');
    $this->loggerChannel = $loggerChannel;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('logger.factory')->get('delivery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_from_workspace';
  }

  /**
   * Retrieve the forms page title.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function title() {
    $source = $this->workspaceStorage->load($this->getRequest()->get('workspace'));
    $entity_type = $this->getRequest()->query->get('entity_type');

    if (!$entity_type) {
      return $this->t('Create a delivery from %workspace', [
        '%workspace' => $source->label(),
      ]);
    }

    return $this->t('Create a delivery of %type entities from %workspace', [
      '%workspace' => $source->label(),
      '%type' => $this->entityTypeManager->getDefinition($entity_type)->getLabel(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    /** @var \Drupal\workspaces\WorkspaceInterface $source */
    $source = $this->workspaceStorage->load($request->get('workspace'));
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
    $entity_type = $request->query->get('entity_type');
    $bundle = $request->query->get('bundle');
    $label = '';

    $form['options_elements'] = [];
    if ($request->query->get('hide_options_elements')) {
      $form['options_elements']['#type'] = 'details';
      $form['options_elements']['#title'] = 'Options';

      if (!$entity_type) {
        $label = (string) $this->t('A delivery from @workspace', [
          '@workspace' => $source->label(),
        ]);
      } else {
        $label = (string) $this->t('A delivery of @type entities from @workspace', [
          '@workspace' => $source->label(),
          '@type' => $this->entityTypeManager->getDefinition($entity_type)->getLabel(),
        ]);
      }
    }

    $form['options_elements']['label'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Label'),
      '#description' => $this->t('Choose a descriptive label for this delivery.'),
      '#default_value' => $label,
    ];

    $form['options_elements']['description'] = [
      '#type' => 'textarea',
      '#required' => FALSE,
      '#title' => $this->t('Description'),
      '#description' => $this->t('A more detailed description of this delivery.'),
    ];

    $form['options_elements']['items'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Delivery items'),
      '#description' => $this->t('Choose which content items should be delivered.'),
      '#options' => [],
    ];

    $modifications = $this->getModifiedEntities($source, $target, $entity_type);

    // Group the items by entity type to load all in a single query.
    $revisionsByEntityType = [];
    foreach ($modifications as $item) {
      $revisionsByEntityType[$item->target_entity_type][] = $item->source_revision;
    }

    foreach ($revisionsByEntityType as $entityTypeId => $revisions) {
      try {
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage($entityTypeId);
        $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
        $entityTypeLabel = (string) $entityType->getLabel();

        foreach ($storage->loadMultipleRevisions($revisions) as $entity) {
          if ($bundle && $entity->bundle() != $bundle) {
            continue;
          }

          $key = implode(':', [
            $entity->getEntityTypeId(),
            $entity->id(),
            $entity->getRevisionId(),
          ]);
          $form['options_elements']['items']['#default_value'][$key] = $key;
          $form['options_elements']['items']['#options'][$key] = $entity->label() . " ($entityTypeLabel, {$entity->bundle()})";
        }
      } catch (\Exception $exception) {
        $this->loggerChannel->error($exception->getMessage());
      }
    }

    if (!empty($form['options_elements']['items']['#options'])) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create delivery'),
      ];
    } else {
      unset($form['options_elements']);

      $empty_text = '';
      $replacements = [
        '@type' => $entity_type,
        '@bundle' => $bundle,
      ];
      if ($entity_type && $bundle) {
        $empty_text = 'There are no @type entities of type @bundle to deliver right now.';
      } elseif ($entity_type) {
        $empty_text = 'There are no @type entities to deliver right now.';
      } else {
        $empty_text = 'There are no entities to deliver right now.';
      }
      $form['empty_text'] = [
        '#markup' => $this->t($empty_text, $replacements),
      ];
    }

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  protected function getModifiedEntities(WorkspaceInterface $source, WorkspaceInterface $target, $entity_type) {
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

    if ($entity_type) {
      $query->condition('source.target_entity_type_id', $entity_type);
    }

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
