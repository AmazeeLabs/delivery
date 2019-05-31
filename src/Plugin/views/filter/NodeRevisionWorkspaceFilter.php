<?php

namespace Drupal\delivery\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
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
 * @ViewsFilter("node_revision_workspace_filter")
 */
class NodeRevisionWorkspaceFilter extends InOperator {

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
    $this->valueTitle = t('Workspace');
    $this->definition['options callback'] = [$this, 'generateOptions'];
    $this->definition['allow empty'] = TRUE;
  }

  /**
   * Add the current workspace option to the options config.
   *
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['current_workspace'] = ['default' => 0];
    return $options;
  }

  /**
   * Add the current workspace option to the options form.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['current_workspace'] = [
      '#type' => 'checkbox',
      '#title' => t("Use the user's current workspace"),
      '#default_value' => $this->options['current_workspace'],
    ];
  }

  /**
   * Override the query to ensure the active workspace is being used if needed.
   */
  public function query() {
    // Set the value to that of the active workspace if requested.
    if (!empty($this->options['current_workspace'])) {
      $workspace = $this->workspaceManager->getActiveWorkspace();

      if (!empty($this->value) && empty($this->value[$workspace->id()])) {
        $this->ensureMyTable();
        // Should be always false.
        $field = $this->getField();
        $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", '-1', '=');
        return $this->query;
      }
      elseif (!empty($this->value[$workspace->id()])) {
        return;
      }

      $this->value = [$workspace->id() => $workspace->id()];
    }
    if (!empty($this->value) || $this->operator == 'empty') {
      parent::query();
    }
  }

  /**
   * Skip validation if no options have been chosen so we can use it as a
   * non-filter.
   */
  public function validate() {
    if (!empty($this->value)) {
      parent::validate();
    }
  }

  /**
   * Helper function that generates the options.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function generateOptions() {
    $options = [];
    $workspaces = $this->entityTypeManager
      ->getStorage('workspace')
      ->loadMultiple();
    foreach ($workspaces as $workspace) {
      $options[$workspace->id()] = $workspace->label();
    }
    return $options;
  }

  /**
   * Replaces "Unknown" in the summary with "Current workspace".
   *
   * @return string
   */
  public function adminSummary() {
    $summary = parent::adminSummary();
    if (!empty($this->options['current_workspace'])) {
      $summary = str_replace('in Unknown', '= Current workspace', $summary);
    }
    return $summary;
  }

}
