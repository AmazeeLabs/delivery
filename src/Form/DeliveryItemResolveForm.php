<?php

namespace Drupal\delivery\Form;

use Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Drupal\delivery\Entity\Delivery;
use Drupal\delivery\Entity\DeliveryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class DeliveryItemResolveForm extends FormBase {

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
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @var \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface
   */
  protected $conflictResolverManager;

  /**
   * @var \Drupal\delivery\Entity\DeliveryItem
   */
  protected $deliveryItem;

  protected $sourceEntity;
  protected $targetEntity;
  protected $parentEntity;
  protected $resultEntity;

  /**
   * DeliveryPushConfirmFom constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * @param \Drupal\delivery\DeliveryService $deliveryService
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface $conflictResolverManager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entity_repository,
    DeliveryService $deliveryService,
    RendererInterface $renderer,
    ConflictResolverManagerInterface $conflictResolverManager
  ) {
    $this->deliveryService = $deliveryService;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
    $this->conflictResolverManager = $conflictResolverManager;
  }

  public function access(AccountInterface $account, Delivery $delivery, DeliveryItem $delivery_item) {
    if (isset($delivery->resolution->value)) {
      return AccessResult::forbidden();
    }
    // TODO: Restrict access to conflict resolution based on target permnissions.
    return AccessResult::allowed();
//    $this->sourceEntity = $this->entityTypeManager
//      ->getStorage($delivery_item->getTargetType())
//      ->loadRevision($delivery_item->getSourceRevision());
//    return $this->sourceEntity->access('edit', $account, TRUE);
  }

  public function title($delivery, $delivery_item) {
    // TODO: Implement a proper title.
    return $this->t('Resolve conflict');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('entity.repository'),
      $container->get('delivery.service'),
      $container->get('renderer'),
      $container->get('conflict_resolver.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_push_changes';
  }

  protected function getTranslation(TranslatableInterface $entity, $langcode) {
    return $entity->hasTranslation($langcode)
      ? $entity->getTranslation($langcode)
      : $entity->addTranslation($langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryInterface $delivery = NULL, DeliveryItem $delivery_item = NULL) {
    $form['#attached']['library'][] = 'delivery/conflict-resolution';
    $this->deliveryItem = $delivery_item;
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($delivery_item->getTargetType());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $this->sourceEntity */
    $this->sourceEntity = $storage->loadRevision($delivery_item->getSourceRevision());
    $this->sourceEntity = $this->sourceEntity;
    /** @var \Drupal\Core\Entity\ContentEntityInterface $this->targetEntity */
    $this->targetEntity = $this->deliveryService->getActiveRevision($delivery_item);
    $this->targetEntity = $this->targetEntity;

    $this->resultEntity = $storage->createRevision($this->targetEntity);

    $entityType = $this->entityTypeManager->getDefinition($this->deliveryItem->getTargetType());
    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionField = $entityType->getKey('revision');

    $this->resultEntity->{$revisionParentField}->merge_target_id = $this->deliveryItem->getSourceRevision();
    $this->resultEntity->{$revisionParentField}->target_id = $this->targetEntity->{$revisionField}->value;
    $this->resultEntity->workspace = $this->deliveryItem->getTargetWorkspace();

    /** @var \Drupal\revision_tree\RevisionTreeHandlerInterface $revisionTreeHandler */
    $revisionTreeHandler = $this->entityTypeManager->getHandler($this->sourceEntity->getEntityTypeId(), 'revision_tree');
    $parentEntityRevision = $revisionTreeHandler->getLowestCommonAncestor($this->sourceEntity, $this->sourceEntity->getRevisionId(), $this->targetEntity->getRevisionId());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $parentEntity */
    $parentEntity = $storage->loadRevision($parentEntityRevision);
    $this->parentEntity = $parentEntity;

    $targetWorkspace = $this->entityTypeManager->getStorage('workspace')
      ->load($delivery_item->getTargetWorkspace());
    $sourceWorkspace = $this->entityTypeManager->getStorage('workspace')
      ->load($delivery_item->getSourceWorkspace());

    $viewDisplay = EntityViewDisplay::collectRenderDisplay($this->sourceEntity, 'merge');
    $formDisplay = EntityFormDisplay::collectRenderDisplay($this->sourceEntity, 'merge');

    $form['languages'] = array(
      '#type' => 'vertical_tabs',
    );

    $hadConflicts = FALSE;

    if ($this->sourceEntity->isTranslatable()) {
      foreach ($this->sourceEntity->getTranslationLanguages() as $language) {
        $languageId = $language->getId();

        $sourceTranslation = $this->getTranslation($this->sourceEntity, $languageId);
        $resultTranslation = $this->getTranslation($this->resultEntity, $languageId);
        $parentTranslation = $this->getTranslation($this->parentEntity, $languageId);
        $targetTranslation = $this->getTranslation($this->targetEntity, $languageId);


        $conflicts = $this->conflictResolverManager->resolveConflicts(
          $targetTranslation,
          $sourceTranslation,
          $parentTranslation,
          $resultTranslation
        );

        $hadConflicts = $hadConflicts || count($conflicts) > 0;


        if ($conflicts) {
          $sourceBuild = $viewDisplay->build($sourceTranslation);
          $targetBuild = $viewDisplay->build($targetTranslation);
          $customForm = [];
          $formDisplay->buildForm($resultTranslation, $customForm, $form_state);

          $form[$languageId] = [
            '#type' => 'details',
            '#title' => $language->getName(),
            '#group' => 'languages',
          ];

          foreach (array_keys($conflicts) as $property) {
            if (!($viewDisplay->getComponent($property) || $formDisplay->getComponent($property))) {
              continue;
            }

            $form[$languageId][$property] = [
              '#type' => 'details',
              '#attributes' => [
                'class' => ['delivery-merge-property'],
              ],
              '#open' => TRUE,
              '#title' => $this->sourceEntity->get($property)->getFieldDefinition()->getLabel(),
              'selection' => [
                '#prefix' => '<div class="delivery-merge-options">',
                '#suffix' => '</div>',
                '#required' => TRUE,
                '#parents' => ['languages', $languageId, $property],
                '#options' => [],
                '#default_value' => '__source__',
              ],
              'preview' => [],
            ];

            if ($viewDisplay->getComponent($property)) {
              $form[$languageId][$property]['selection']['#type'] = 'radios';
              $form[$languageId][$property]['selection']['#options'] = [
                '__source__' => $sourceWorkspace->label(),
                '__target__' => $targetWorkspace->label(),
              ];
              $form[$languageId][$property]['preview'] = [
                'source' => [
                  '#prefix' => '<div class="delivery-merge-source">',
                  '#suffix' => '</div>',
                  'build' => $sourceBuild[$property]
                ],
                'target' => [
                  '#prefix' => '<div class="delivery-merge-target">',
                  '#suffix' => '</div>',
                  'build' => $targetBuild[$property]
                ],
              ];
              if ($formDisplay->getComponent($property)) {
                $formElement = $customForm[$property];
                $formElement['widget']['#parents'] = ['custom', $languageId, $property];
                $form[$languageId][$property]['selection']['#options']['__custom__'] = $this->t('Custom');
                $form[$languageId][$property]['preview']['custom'] = [
                  '#prefix' => '<div class="delivery-merge-custom">',
                  '#suffix' => '</div>',
                  'build' => $formElement,
                ];
              }
            }
            else if ($formDisplay->getComponent($property)) {
              $formElement = $customForm[$property];
              $formElement['widget']['#parents'] = ['custom', $languageId, $property];
              $form[$languageId][$property]['selection']['#type'] = 'value';
              $form[$languageId][$property]['selection']['#value'] = '__custom__';
              $form[$languageId][$property]['preview'] = [
                'custom' => [
                  '#prefix' => '<div class="delivery-merge-custom">',
                  '#suffix' => '</div>',
                  'build' => $formElement,
                ]
              ];
            }
          }
        }
        else {
          // TODO: Display preview of the automatic merge.
        }
      }
    }

    if (!$hadConflicts) {
      $form['message'] = [
        '#markup' => '<p><em>' . $this->t('All conflicts could be solved automatically. Do you want to proceed?') . "</em></p>",
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $hadConflicts ? $this->t('Resolve conflicts') : $this->t('Deliver content'),
      '#button_type' => 'primary',
    ];

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->resultEntity->save();
    foreach ($this->resultEntity->getTranslationLanguages() as $language) {
      $context = new ParameterBag();
      $context->set('resolution_form_result', $form_state->getValue('languages')[$language->getId()]);
      $context->set('resolution_custom_values', $form_state->getValue('custom')[$language->getId()]);
      $resultTranslation = $this->getTranslation($this->resultEntity, $language->getId());
      $this->conflictResolverManager->resolveConflicts(
        $this->getTranslation($this->targetEntity, $language->getId()),
        $this->getTranslation($this->sourceEntity, $language->getId()),
        $this->getTranslation($this->parentEntity, $language->getId()),
        $resultTranslation,
        $context
      );
      $resultTranslation->save();
    }
    $this->deliveryItem->result_revision = $this->resultEntity->getRevisionId();
    // TODO: actually compare entities.
    $this->deliveryItem->resolution = DeliveryItem::RESOLUTION_MERGE;
    $this->deliveryItem->save();
    $this->messenger->addStatus($this->t('The changes have been imported.'));
  }
}
