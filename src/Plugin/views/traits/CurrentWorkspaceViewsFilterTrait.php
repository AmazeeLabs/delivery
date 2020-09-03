<?php

namespace Drupal\delivery\Plugin\views\traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CurrentWorkspaceViewsFilterTrait {
  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Views Handler Plugin Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinHandler;

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
    $this->entityTypeManager = $entity_type_manager;
    $this->joinHandler = $join_handler;
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
    // Ensure we actually have an active workspace.
    if (!$this->workspaceManager->getActiveWorkspace()) {
      return;
    }
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $keys = $entity_type->getKeys();
    $definition = [
      'table' => 'workspace_association',
      'type' => 'INNER',
      'field' => 'target_entity_id',
      'left_table' => $query_base_table,
      'left_field' => $keys['id'],
      'extra' => [
        [
          'field' => 'workspace',
          'value' => $this->workspaceManager->getActiveWorkspace()->id(),
          'operator' => '=',
        ],
        [
          'field' => 'target_entity_type_id',
          'value' => $entity_type->id(),
        ]
      ],
    ];

    $join = $this->joinHandler->createInstance('standard', $definition);
    $associations = $query->addTable($query_base_table, $this->relationship, $join);

    $deletedJoin = $this->joinHandler->createInstance('standard', [
      'table' => $entity_type->getRevisionTable(),
      'type' => 'INNER',
      'field' => $keys['revision'],
      'left_table' => $associations,
      'left_field' => 'target_entity_revision_id',
      'extra' => [
        [
          'field' => 'deleted',
          'value' => '0',
          'operator' => '=',
        ],
      ],
    ]);
    $query->addTable($query_base_table, $this->relationship, $deletedJoin);

  }

}

