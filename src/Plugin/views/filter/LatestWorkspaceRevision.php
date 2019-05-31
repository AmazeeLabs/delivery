<?php

namespace Drupal\delivery\Plugin\Views\filter;

use Drupal\views\Plugin\views\filter\LatestRevision;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\ViewsHandlerManager;

/**
 * Filter to show only the latest revision of an entity in current workspace.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("latest_workspace_revision")
 */
class LatestWorkspaceRevision extends LatestRevision {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new LatestRevision.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager Service.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_handler
   *   Views Handler Plugin Manager.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   Workspace manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ViewsHandlerManager $join_handler, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $join_handler);
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.views.join'),
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $keys = $entity_type->getKeys();
    $definition = [
      'table' => 'revision_tree_index',
      'type' => 'INNER',
      'field' => 'entity_id',
      'left_table' => $query_base_table,
      'left_field' => $keys['id'],
      'extra' => [
        [
          'field' => 'workspace',
          'value' => $this->workspaceManager->getActiveWorkspace()->id(),
          'operator' => '=',
        ],
        [
          'field' => 'revision_id',
          'left_field' => $keys['revision'],
        ],
        [
          'field' => 'entity_type',
          'value' => $entity_type->id(),
        ],
      ],
    ];

    $join = $this->joinHandler->createInstance('standard', $definition);
    $query->addTable($query_base_table, $this->relationship, $join);
  }

}
