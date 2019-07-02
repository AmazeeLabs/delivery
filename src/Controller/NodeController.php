<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Controller\NodeController as OriginalNodeController;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overwrites the original node controller class.
 */
class NodeController extends OriginalNodeController {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, Connection $database) {
    parent::__construct($date_formatter, $renderer);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getRevisionIds(NodeInterface $node, NodeStorageInterface $node_storage) {
    // We change the logic of the parent method so that we only return the
    // ancestors of the revision, not all the revisions. This is used on the
    // revision overview page.
    // A direct recursive query to get the ancestors is not something that can
    // be done right now unless we introduce a dependency on the actual dbms.
    // For example for mysql: https://stackoverflow.com/questions/12948009/finding-all-parents-in-mysql-table-with-single-query-recursive-query/12948271#12948271
    // For sqlite maybe something like this: https://stackoverflow.com/questions/20215744/how-to-create-a-mysql-hierarchical-recursive-query/45174473#45174473
    // So instead, we do this in two steps: first, we will get all the ancestors
    // of a specific revision. Then, just for pagination reasons, we will run
    // an sql query where we just have the condition that the revision field is
    // among the ancestors. This can be, however, problematic when there is a
    // huge number of ancestors. In that case we may want to switch to manually
    // handle the pager.
    $ancestors = $this->getAncestorsForRevision($node);
    // Add the current revision to the ancestors array, so that it will also be
    // returned by the query bellow.
    $ancestors[] = $node->getRevisionId();
    $revisionField = $node->getEntityType()->getKey('revision');

    // Get the paginated result.
    $query = $this->database->select($node->getEntityType()->getRevisionTable(), 'r')->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('r', [$revisionField]);
    $query->condition('r.' . $revisionField, $ancestors, 'IN');
    $query->limit(50);
    $query->orderBy('r.' . $revisionField, 'DESC');
    $result = $query->execute()->fetchAllKeyed(0, 0);
    return $result;
  }

  /**
   * Returns an array with all the ancestors of a node revision, using only the
   * target_id of the revision parent field, not the merge_target_id.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node revision.
   *
   * @return array
   *   An array with all the ancestors of the node.
   */
  protected function getAncestorsForRevision(NodeInterface $node) {
    $revision_field = $node->getEntityType()->getKey('revision');
    $parent_revision_id_field = $node->getEntityType()->getRevisionMetadataKey('revision_parent');

    // First, just get all the revisions of the node, then look only for the
    // ancestors of the passed in node revision.
    $query = $this->database->select($node->getEntityType()->getRevisionTable(), 'r');
    $query->fields('r', [$revision_field, $parent_revision_id_field]);
    $query->condition('r.' . $node->getEntityType()->getKey('id'), $node->id());
    // All the ancestors will have the revision id smaller than the current
    // revision id, so add this condition to the query as well.
    $query->condition('r.' . $revision_field, $node->getRevisionId(), '<=');
    $all_revisions = $query->execute()->fetchAllKeyed(0, 1);
    $ancestors = [];
    if (!empty($all_revisions) && !empty($all_revisions[$node->getRevisionId()])) {
      $parent = $all_revisions[$node->getRevisionId()];
      while (!empty($parent)) {
        $ancestors[] = $parent;
        $parent = $all_revisions[$parent];
      }
    }
    return $ancestors;
  }

}
