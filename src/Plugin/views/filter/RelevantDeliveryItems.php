<?php

namespace Drupal\delivery\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters by current workspace.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("relevant_delivery_items")
 */
class RelevantDeliveryItems extends FilterPluginBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * NodeRevisionWorkspaceFilter constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('workspaces.manager')
    );
  }

  /**
   * Override the query to ensure the active workspace is being used if needed.
   */
  public function query() {
    $this->ensureMyTable();
    $current_workspace = $this->workspaceManager->getActiveWorkspace();
    $workspace_id = $current_workspace->id();
    $this->query->addWhere($this->options['group'], (new Condition('OR'))
      ->condition('source_workspace', $workspace_id)
      ->condition('target_workspace', $workspace_id));
  }

}
