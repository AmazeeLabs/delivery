<?php

namespace Drupal\delivery\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters by workspace ID.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("delivery_active_workspace_filter")
 */
class DeliveryWorkspaceFilter extends InOperator {

  const SOURCE = 'source';

  const TARGET = 'workspaces_target_id';

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
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Filter by delivery workspace');
    $this->definition['options callback'] = [$this, 'generateOptions'];
  }

  /**
   * Override the query to ensure the active workspace is being used if needed.
   */
  public function query() {
    $this->ensureMyTable();

    $value = $this->options['value'] ?? [];
    $current_workspace = $this->workspaceManager->getActiveWorkspace();
    $workspace_id = $current_workspace->id();

    // "All" means "either" or "both" depending on the operator.
    if (!empty($value['all'])) {
      $condition = $this->operator == 'in' ? 'OR' : 'AND';
      $this->query->addWhere(
        $this->options['group'],
        (new Condition($condition))
          ->condition(self::SOURCE, $workspace_id, $this->operator)
          ->condition(self::TARGET, $workspace_id, $this->operator)
      );
    }
    // Filter individually.
    else {
      if (!empty($value['source'])) {
        $this->query->addWhere($this->options['group'], self::SOURCE, $workspace_id, $this->operator);
      }
      if (!empty($value['target'])) {
        $this->query->addWhere($this->options['group'], self::TARGET, $workspace_id, $this->operator);
      }
    }
  }

  /**
   * Helper function that generates the options.
   *
   * @return array
   */
  public function generateOptions() {
    $options = [
      'source' => $this->t('Source workspace'),
      'target' => $this->t('Target workspace'),
    ];
    return $options;
  }

}
