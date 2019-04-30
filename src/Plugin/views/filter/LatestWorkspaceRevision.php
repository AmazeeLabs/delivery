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

    $left_table = $entity_type->getRevisionTable();
    $currentWorkspace = $this->workspaceManager->getActiveWorkspace();

    $definition = [
      'table' => $left_table,
      'type' => 'LEFT',
      'field' => $keys['id'],
      'left_table' => $query_base_table,
      'left_field' => $keys['id'],
      'extra' => [
        ['left_field' => $keys['revision'], 'field' => $keys['revision'], 'operator' => '>'],
        ['field' => 'workspace', 'value' => $currentWorkspace->id()]
      ],
    ];

    $join = $this->joinHandler->createInstance('standard', $definition);

    $join_table_alias = $query->addTable($query_base_table, $this->relationship, $join);
    $query->addWhere($this->options['group'], "$join_table_alias.{$keys['id']}", NULL, 'IS NULL');
  }

}
