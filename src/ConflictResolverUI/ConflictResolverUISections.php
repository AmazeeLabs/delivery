<?php

namespace Drupal\delivery\ConflictResolverUI;

use Drupal\Core\Conflict\Entity\ContentEntityConflictHandler;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\delivery\DocumentMerge;
use Drupal\revision_tree\ConflictResolverUI\ConflictResolverUIInterface;

/**
 * Default conflict resolver UI service which just shows a simple select element
 * to choose one of the two revisions in conflict.
 */
class ConflictResolverUISections implements ConflictResolverUIInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * Constructs a ConflictResolverUIDefault object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entityFormBuilder
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entityFormBuilder
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entityFormBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function conflictResolverUI(RevisionableInterface $revision_a, RevisionableInterface $revision_b) {
    /** @var $revision_a \Drupal\Core\Entity\ContentEntityInterface */
    $common_ancestor = $this->getLowestCommonAncestorEntity($revision_a, $revision_a->getRevisionId(), $revision_b->getRevisionId());
    $storage = $this->entityTypeManager->getStorage($revision_a->getEntityType()->id());
    $new_revision = $storage->createRevision($revision_a);
    // When merging revision a to b, we set the revision b as parent and
    // revision a as merge parent.
    $new_revision->revision_parent->target_id = $revision_b->getRevisionId();
    $new_revision->revision_parent->merge_target_id = $revision_a->getRevisionId();

    /** @var \Drupal\Core\Conflict\Entity\ContentEntityConflictHandler $conflictHandler */
    $conflictHandler = $this->entityTypeManager->getHandler($new_revision->getEntityTypeId(), 'conflict.resolution_handler');

    $displayMode = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load($revision_a->getEntityTypeId() . '.' . $revision_a->bundle() . '.default');

    // Invoke auto merge processes from the conflict module.
    $conflictHandler->autoMergeNonEditedTranslations($revision_a, $revision_b);
    $conflictHandler->autoMergeNonEditableFields($revision_a, $revision_b, $displayMode);

    // Auto-Merge sections field.
    // TODO: Handle this with per field auto-merge plugins instead.
    if ($revision_a->hasField('body')) {
      $merge = new DocumentMerge();
      $source = $common_ancestor ? $common_ancestor->get('body')->get(0)->value : '<div id="dummy"></div>';

      $left = $revision_a->get('body')->get(0)->value;
      $merge->setLabel('left', t('@workpsace version', ['@workspace' => $revision_a->workpsace->entity->label()])->__toString());

      $right = $revision_b->get('body')->get(0)->value;
      $merge->setLabel('right', t('@workpsace version', ['@workspace' => $revision_a->workpsace->entity->label()])->__toString());

      $result = $left && $right && $source ? $merge->merge($source, $left, $right) : '';

      $new_revision->get('body')->setValue([
        'value' => $result,
        'format' => $revision_a->get('body')->format
      ]);

    }

    $new_revision->{ContentEntityConflictHandler::CONFLICT_ENTITY_ORIGINAL} = $common_ancestor;
    $new_revision->{ContentEntityConflictHandler::CONFLICT_ENTITY_SERVER} = $revision_b;

    /** @var \Drupal\Core\Entity\ContentEntityForm $form */
    $form = $this->entityFormBuilder->getForm($new_revision, 'merge');
    return $form;
  }


  /**
   * Temporary use copypasted method from revision_tree module.
   * Returns the lowest common ancestor entity revision of two revisions.
   */
  protected function getLowestCommonAncestorEntity(RevisionableInterface $entity, $first_revision_id, $second_revision_id) {
    /* @var \Drupal\revision_tree\RevisionTreeHandlerInterface $revisionTreeHandler */
    $revisionTreeHandler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'revision_tree');
    $commonAncestor = $revisionTreeHandler->getLowestCommonAncestor($entity, $first_revision_id, $second_revision_id);
    if (!empty($commonAncestor)) {
      $commonAncestor = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadRevision($commonAncestor);
      if ($commonAncestor instanceof TranslatableInterface && $entity instanceof TranslatableInterface && $commonAncestor->hasTranslation($entity->language()->getId())) {
        $commonAncestor = $commonAncestor->getTranslation($entity->language()->getId());
      }
      return $commonAncestor;
    }
    return NULL;
  }

}
