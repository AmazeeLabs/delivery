<?php

namespace Drupal\delivery\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Drupal\delivery\Entity\Delivery;
use Drupal\delivery\Entity\DeliveryItem;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class DeliveryItemResolveForm
 *
 * @package Drupal\delivery\Form
 */
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
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\delivery\Entity\DeliveryItem
   */
  protected $deliveryItem;

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $sourceEntity;

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $targetEntity;

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $parentEntity;

  /**
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $resultEntity;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * DeliveryPushConfirmFom constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * @param \Drupal\delivery\DeliveryService $deliveryService
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface $conflictResolverManager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    EntityRepositoryInterface $entity_repository,
    DeliveryService $deliveryService,
    RendererInterface $renderer,
    ConflictResolverManagerInterface $conflictResolverManager,
    WorkspaceManagerInterface $workspaceManager,
    LanguageManagerInterface $languageManager
  ) {
    $this->deliveryService = $deliveryService;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->entityRepository = $entity_repository;
    $this->renderer = $renderer;
    $this->conflictResolverManager = $conflictResolverManager;
    $this->workspaceManager = $workspaceManager;
    $this->languageManager = $languageManager;
  }

  public function access(AccountInterface $account, DeliveryItem $delivery_item) {
    if (isset($delivery_item->resolution->value)) {
      return AccessResult::forbidden();
    }
    // TODO: Restrict access to conflict resolution based on target permnissions.
    return AccessResult::allowed();
//    $this->sourceEntity = $this->entityTypeManager
//      ->getStorage($delivery_item->getTargetType())
//      ->loadRevision($delivery_item->getSourceRevision());
//    return $this->sourceEntity->access('edit', $account, TRUE);
  }

  public function title(DeliveryItem $delivery_item) {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($delivery_item->getTargetType());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $this->sourceEntity */
    $sourceEntity = $storage->loadRevision($delivery_item->getSourceRevision());
    return $this->t('Resolve conflict. <a href="@href" target="_blank">@label</a>', [
      '@href' => $sourceEntity->toUrl()->toString(),
      '@label' => $sourceEntity->label(),
    ]);
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
      $container->get('conflict_resolver.manager'),
      $container->get('workspaces.manager'),
      $container->get('language_manager')
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
  public function buildForm(array $form, FormStateInterface $form_state, DeliveryItem $delivery_item = NULL) {
    $form['#attached']['library'][] = 'delivery/conflict-resolution';
    $this->deliveryItem = $delivery_item;
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($delivery_item->getTargetType());
    /** @var \Drupal\Core\Entity\ContentEntityInterface $this->sourceEntity */
    $this->sourceEntity = $storage->loadRevision($delivery_item->getSourceRevision());
    $this->sourceEntity = $this->sourceEntity;
    /** @var \Drupal\Core\Entity\ContentEntityInterface $this->targetEntity */
    $this->targetEntity = $storage->loadRevision($this->deliveryService->getActiveRevision($delivery_item));

    $this->resultEntity = $storage->createRevision($this->sourceEntity);

    $entityType = $this->entityTypeManager->getDefinition($this->deliveryItem->getTargetType());
    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionMergeParentField = $entityType->getRevisionMetadataKey('revision_merge_parent');
    $revisionField = $entityType->getKey('revision');

    $this->resultEntity->{$revisionMergeParentField}->target_revision_id = $this->deliveryItem->getSourceRevision();
    $this->resultEntity->{$revisionParentField}->target_revision_id = $this->targetEntity->{$revisionField}->value;
    $this->resultEntity->workspace = $this->deliveryItem->getTargetWorkspace();

    /** @var \Drupal\revision_tree\EntityRevisionTreeHandlerInterface $revisionTreeHandler */
    $revisionTreeHandler = $this->entityTypeManager->getHandler($this->sourceEntity->getEntityTypeId(), 'revision_tree');
    $parentEntityRevision = $revisionTreeHandler->getLowestCommonAncestorId($this->sourceEntity->getRevisionId(), $this->targetEntity->getRevisionId(), $delivery_item->getTargetId());

    /** @var \Drupal\Core\Entity\ContentEntityInterface $parentEntity */
    $parentEntity = $storage->loadRevision($parentEntityRevision);
    $this->parentEntity = $parentEntity;

    $targetWorkspace = $this->entityTypeManager->getStorage('workspace')
      ->load($delivery_item->getTargetWorkspace());
    $sourceWorkspace = $this->entityTypeManager->getStorage('workspace')
      ->load($delivery_item->getSourceWorkspace());

    $targetPrimaryLanguage = (!empty($targetWorkspace->primary_language) && $targetWorkspace->primary_language->value) ?: $this->languageManager->getDefaultLanguage()->getId();
    $targetLanguages = [$targetPrimaryLanguage];
    if (!empty($targetWorkspace->secondary_languages)) {
      foreach ($targetWorkspace->secondary_languages as $secondaryLanguage) {
        $targetLanguages[] = $secondaryLanguage->value;
      }
    }

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

        $context = new ParameterBag();
        $context->set('supported_languages', $targetLanguages);

        $conflicts = $this->conflictResolverManager->resolveConflicts(
          $targetTranslation,
          $sourceTranslation,
          $parentTranslation,
          $resultTranslation,
          $context
        );

        $hadConflicts = $hadConflicts || count($conflicts) > 0;

        // If the current language is not the target workspace primary language,
        // ignore all conflicts in non-translatable fields.
        if ($languageId !== $targetPrimaryLanguage) {
          foreach (array_keys($conflicts) as $prop) {
            if(!$sourceTranslation->get($prop)->getFieldDefinition()->isTranslatable()) {
              unset($conflicts[$prop]);
            }
          }
        }

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
              // Add a language attribute to each editor widget.
              foreach ($formElement['widget'] as $key => $widget) {
                if (!is_numeric($key)) {
                  continue;
                }
                if (empty($formElement['widget'][$key]['html']['#attributes'])) {
                  $formElement['widget'][$key]['html']['#attributes'] = [];
                }
                $formElement['widget'][$key]['html']['#attributes']['data-lang'] = $languageId;
              }
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

    $args = [
      ':source' => $sourceWorkspace->label(),
      ':target' => $targetWorkspace->label(),
      ':title' => $parentEntity->label(),
    ];

    $buttons = [
      DeliveryItem::STATUS_NEW => $this->t('Add to :target', $args),
      DeliveryItem::STATUS_MODIFIED_BY_SOURCE => $this->t('Apply changes to :target', $args),
      DeliveryItem::STATUS_CONFLICT => $this->t('Conflict'),
      DeliveryItem::STATUS_CONFLICT_AUTO => $this->t('Apply changes to :target', $args),
      DeliveryItem::STATUS_DELETED => $this->t('Mark as resolved'),
      DeliveryItem::STATUS_DELETED_BY_SOURCE => $this->t('Delete from :target', $args),
      DeliveryItem::STATUS_RESTORED_BY_SOURCE => $this->t('Restore to :target', $args),
    ];

    $messages = [
      DeliveryItem::STATUS_NEW => $this->t(':source added ":title". Also add it to :target?', $args),
      DeliveryItem::STATUS_MODIFIED_BY_SOURCE => $this->t('":title" was modified in :source. Apply changes to :target?', $args),
      DeliveryItem::STATUS_CONFLICT_AUTO => $this->t('The conflict in ":title" could be resolved automatically. Apply changes to :target?', $args),
      DeliveryItem::STATUS_DELETED => $this->t('":title" has been deleted from both :source and :target. Mark this as resolved?', $args),
      DeliveryItem::STATUS_DELETED_BY_SOURCE => $this->t('":title" was deleted from :source. Also delete it from :target?', $args),
      DeliveryItem::STATUS_RESTORED_BY_SOURCE => $this->t(':source has restored ":title". Also restore it to :target?', $args),
    ];

    if (!$hadConflicts) {
      $form['message'] = [
        '#markup' => '<p><em>' . $messages[$delivery_item->getStatus()['status']] . "</em></p>",
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $hadConflicts ? $this->t('Resolve conflicts') : $buttons[$delivery_item->getStatus()['status']],
      '#button_type' => 'primary',
    ];

    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $formDisplay = EntityFormDisplay::collectRenderDisplay($this->resultEntity, 'merge');
    /** @var \Drupal\Core\Field\WidgetPluginManager $widgetPluginManager */
    $widgetPluginManager = \Drupal::service('plugin.manager.field.widget');
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');

    $targetWorkspace = $this->entityTypeManager->getStorage('workspace')
      ->load($this->deliveryItem->getTargetWorkspace());

    $targetLanguages = [];
    if (!empty($targetWorkspace->primary_language)) {
      $targetPrimaryLanguage = $targetWorkspace->primary_language->value;
      $targetLanguages = [$targetPrimaryLanguage];
    }
    if (!empty($targetWorkspace->secondary_languages)) {
      foreach ($targetWorkspace->secondary_languages as $secondaryLanguage) {
        $targetLanguages[] = $secondaryLanguage->value;
      }
    }

    // Copy resolutions of non-translatable fields from primary language to
    // other languages.
    $allResolutions = $form_state->getValue('languages');
    foreach ($allResolutions[$targetPrimaryLanguage] as $key => $value) {
      if ($this->resultEntity->get($key)->getFieldDefinition()->isTranslatable()) {
        continue;
      }
      foreach (array_keys($allResolutions) as $lang) {
        if ($lang !== $targetPrimaryLanguage) {
          $allResolutions[$lang][$key] = $value;
        }
      }
    }

    // Copy manual merges of non-translatable fields from primary language to
    // other languages.
    $allCustomValues = $form_state->getValue('custom');
    foreach ($allCustomValues[$targetPrimaryLanguage] as $key => $value) {
      if ($this->resultEntity->get($key)->getFieldDefinition()->isTranslatable()) {
        continue;
      }
      foreach (array_keys($allCustomValues) as $lang) {
        if ($lang !== $targetPrimaryLanguage) {
          $allCustomValues[$lang][$key] = $value;
        }
      }
    }

    foreach ($this->resultEntity->getTranslationLanguages() as $language) {
      $context = new ParameterBag();
      $context->set('supported_languages', $targetLanguages);
      $context->set('resolution_form_result', $allResolutions[$language->getId()]);
      $customValues = $allCustomValues[$language->getId()];

      foreach ($customValues as $field => $input) {
        $component = $formDisplay->getComponent($field);
        $entityType = $this->sourceEntity->getEntityType();
        $bundle = $this->sourceEntity->bundle();
        $definitions = $entityFieldManager->getFieldDefinitions($entityType->id(), $bundle);
        /** @var \Drupal\Core\Field\WidgetInterface $widget */
        $widget = $widgetPluginManager->getInstance([
          'field_definition' => $definitions[$field],
          'form_mode' => 'merge',
          // No need to prepare, defaults have been merged in setComponent().
          'prepare' => FALSE,
          'configuration' => $component,
        ]);
        $customValues[$field] = $widget->massageFormValues($input, $form, $form_state);
      }
      $context->set('resolution_custom_values', $customValues);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $resultTranslation */
      $resultTranslation = $this->getTranslation($this->resultEntity, $language->getId());
      $this->conflictResolverManager->resolveConflicts(
        $this->getTranslation($this->targetEntity, $language->getId()),
        $this->getTranslation($this->sourceEntity, $language->getId()),
        $this->getTranslation($this->parentEntity, $language->getId()),
        $resultTranslation,
        $context
      );
      if (in_array($language->getId(), $targetLanguages)) {
        $violations = $resultTranslation->validate();
        foreach ($violations as $violation) {
          // Ignore all violations that can not be resolved in this form.
          if (isset($form[$language->getId()][$violation->getPropertyPath()])) {
            $form_state->setError($form, $violation->getMessage());
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->workspaceManager->executeInWorkspace($this->deliveryItem->getTargetWorkspace(), function () use ($form, $form_state) {
      $this->resultEntity->setSyncing(TRUE);
      $this->resultEntity->save();
      $this->deliveryItem->result_revision = $this->resultEntity->getRevisionId();
    });
    // TODO: actually compare entities.
    $this->deliveryItem->resolution = DeliveryItem::RESOLUTION_MERGE;
    $this->deliveryItem->save();

    $this->messenger->addStatus($this->t('The changes have been imported.'));
  }
}
