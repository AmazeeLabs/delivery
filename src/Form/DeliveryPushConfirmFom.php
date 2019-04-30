<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\revision_tree\Entity\RevisionTreeEntityRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryPushConfirmFom extends ConfirmFormBase {

  public static $BATCH_THRESHOLD = 10;

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
   * @var \Drupal\revision_tree\Entity\RevisionTreeEntityRepositoryInterface $entityRepository
   *  The entity repository service.
   */
  protected $entityRepository;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   *  The messenger service.
   */
  protected $messenger;

  /**
   * DeliveryPushConfirmFom constructor.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, RevisionTreeEntityRepositoryInterface $entity_repository) {
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
      $container->get('entity.repository')
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
      'operations' => [
        [[$this, 'pushChangesBatch'], [['entity_type' => 'node', 'field_name' => 'nodes']]],
        [[$this, 'pushChangesBatch'], [['entity_type' => 'media', 'field_name' => 'media']]],
      ],
      'finished' => [$this, 'finishPushChanges'],
      'title' => $this->t('Push changes'),
      'progress_message' => $this->t('Pushing changes ...'),
      'error_message' => $this->t('Error pushing changes.'),
    ];
    $form_state->setRedirectUrl($this->getCancelUrl());
    batch_set($batch);
  }

  /**
   *
   */
  public function pushChangesBatch($data, &$context) {
    if (empty($context['sandbox']['max'])) {
      $context['sandbox']['max'] = count($this->delivery->get($data['field_name'])->getValue());
      $entity_type = $this->entityTypeManager->getDefinition($data['entity_type']);
      $context['message'] = $this->t('Pushing changes for %entity_type entities ...', ['%entity_type' => $entity_type->getLabel()]);
      $context['finished'] = 0;
      $context['sandbox']['progress'] = 0;
    }

    $storage = $this->entityTypeManager->getStorage($data['entity_type']);
    $field_values = $this->delivery->get($data['field_name'])->getValue();
    $index = 0;
    while ($context['sandbox']['progress'] < $context['sandbox']['max'] && $index < $this::$BATCH_THRESHOLD) {
      // Load the entity revision and push it to all the target workspaces.
      if (!empty($field_values[$context['sandbox']['progress']]['target_revision_id'])) {
        $entity = $storage->loadRevision($field_values[$context['sandbox']['progress']]['target_revision_id']);
        $workspaces = $this->delivery->get('workspaces')->referencedEntities();
        /* @var \Drupal\workspaces\WorkspaceInterface $workspace */
        foreach ($workspaces as $workspace) {
          $target_entity = $this->entityRepository->getActive($data['entity_type'], $field_values[$context['sandbox']['progress']]['target_id'], ['workspace' => [$workspace->id()]]);

          // When pushing the changes we just want to use the source revision,
          // no matter if there are conflicts or not.
          $new_revision = $storage->createRevision($entity);
          $new_revision->workspace = $workspace->id();
          $new_revision->revision_parent->target_id = $target_entity->getRevisionId();
          $new_revision->revision_parent->merge_target_id = $entity->getRevisionId();
          $new_revision->save();

          // Only increment the index if we actually performed an operation.
          $index++;
        }
      }
      $context['sandbox']['progress']++;
    }
    // This is the case when there are no entities to push.
    if (empty($context['sandbox']['max'])) {
      $context['finished'] = 1;
    }
    else {
      $context['finished'] = min($context['sandbox']['progress'] / $context['sandbox']['max'], 1);
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
