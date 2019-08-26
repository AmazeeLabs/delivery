<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\system\MenuInterface;

class MenuPushForm extends ConfirmFormBase {

  /**
   * @var MenuInterface
   */
  protected $menu;
  public function buildForm(array $form, FormStateInterface $form_state, MenuInterface $menu = NULL) {
    $this->menu = $menu;
    return parent::buildForm($form, $form_state);
  }

  public function getQuestion() {
    return $this->t('Publish @count menu changes?', [
      '@count' => count(static::differences($this->menu->id())),
    ]);
  }

  public function getCancelUrl() {
    return Url::fromRoute('entity.menu.edit_form', [
      'menu' => $this->menu->id(),
    ]);
  }

  public function getFormId() {
    return 'delivery-menu-push-form';
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'operations' => [],
      'title' => $this->t('Push changes'),
      'progress_message' => $this->t('Pushing changes @current of @total.'),
      'error_message' => $this->t('Error pushing changes.'),
    ];

    foreach (static::differences($this->menu->id()) as $item) {
      $batch['operations'][] = [
        [$this, 'process'], [$item->target_entity_id]
      ];
    }
    $form_state->setRedirect('entity.menu.edit_form', [
      'menu' => $this->menu->id(),
    ]);
    batch_set($batch);
  }

  public function process($entityId) {
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager */
    $workspaceManager = \Drupal::service('workspaces.manager');

    $activeWorkspace = $workspaceManager->getActiveWorkspace();

    /** @var \Drupal\workspaces\WorkspaceAssociationInterface $workspaceAssiociaton */
    $workspaceAssiociaton = \Drupal::service('workspaces.association');
    /** @var \Drupal\workspaces\WorkspaceInterface $parentWorkspace */
    $parentWorkspace = $activeWorkspace->parent->entity;
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

    $entity = $storage->load($entityId);
    $entityType = $entity->getEntityType();

    $parentRevisions = $workspaceAssiociaton->getTrackedEntities(
      $parentWorkspace->id(),
      $entity->getEntityTypeId(),
      [$entity->id()]
    );

    if (empty($parentRevisions)) {
      $parentRevisions = array_keys($storage->getQuery()->allRevisions()
        ->notExists('workspace')
        ->condition($storage->getEntityType()->getKey('id'), $entity->id())
        ->execute());
    }
    else {
      $parentRevisions = array_keys($parentRevisions[$entity->getEntityTypeId()]);
    }

    /** @var ContentEntityInterface $result */
    $result = $storage->createRevision($entity);

    $revisionParentField = $entityType->getRevisionMetadataKey('revision_parent');
    $revisionMergeParentField = $entityType->getRevisionMetadataKey('revision_merge_parent');

    $result->{$revisionMergeParentField}->target_revision_id = $entity->getRevisionId();
    $result->{$revisionParentField}->target_revision_id = array_pop($parentRevisions);
    $result->workspace = $parentWorkspace;
    $result->setSyncing(TRUE);
    $workspaceManager->executeInWorkspace($parentWorkspace->id(), function () use ($result) {
      $result->save();
    });
  }

  public static function differences($menu) {
    /** @var \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager */
    $workspaceManager = \Drupal::service('workspaces.manager');

    $source = $workspaceManager->getActiveWorkspace();
    $target = $source->parent->entity;
    if (!$source->auto_push->value || !$target) {
      return FALSE;
    }

    $database = \Drupal::database();
    $query = $database->select('workspace_association', 'source');

    $query->addField('source', 'target_entity_id', 'target_entity_id');
    $query->addField('source', 'target_entity_type_id', 'target_entity_type');
    $query->addField('source', 'target_entity_revision_id', 'source_revision');
    $query->addField('target', 'target_entity_revision_id', 'target_revision');
    $query->condition('source.target_entity_type_id', 'menu_link_content');

    $query->leftJoin('workspace_association', 'target',
      'source.target_entity_id = target.target_entity_id and source.target_entity_type_id = target.target_entity_type_id and target.workspace = :target',
      [':target' => $target->id()]
    );

    $query->leftJoin('menu_link_content_data', 'mld', 'mld.id = source.target_entity_id');
    $query->condition('mld.menu_name', $menu);

    $query->where('source.workspace = :source and (source.target_entity_revision_id != target.target_entity_revision_id or target.target_entity_revision_id is null)', [
      ':source' => $source->id()
    ]);

    return $query->execute()->fetchAll();
  }

}
