<?php

namespace Drupal\delivery\Plugin\views\relationship;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsRelationship;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Views;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @ViewsRelationship("delivery_workspace_revision")
 */
class WorkspaceRevision extends RelationshipPluginBase {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinHandler;

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workspaces.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.views.join')
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    WorkspaceManagerInterface $workspaceManager,
    EntityTypeManagerInterface $entityTypeManager,
    ViewsHandlerManager $joinHandler
  ) {
    $this->workspaceManager = $workspaceManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->joinHandler = $joinHandler;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['workspace'] = ['default' => '__parent'];
    return $options;
  }


  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['workspace'] = [
      '#type' => 'select',
      '#title' => $this->t('Target workspace'),
      '#description' => $this->t('Select a workspace to get the latest revision of.'),
      '#options' => [
        '__parent' => $this->t('Parent workspace'),
        '__auto_push' => $this->t('Auto push target'),
      ],
      '#default_value' => $this->options['workspace'],
    ];
  }


  public function query() {
    $activeWorkspace = $this->workspaceManager->getActiveWorkspace();
    $targetWorkspace = '__foo__';
    if ($this->options['workspace'] === '__parent' && $activeWorkspace->parent->target_id) {
      $targetWorkspace = $activeWorkspace->parent->target_id;
    }
    if ($this->options['workspace'] === '__auto_push' && $activeWorkspace->auto_push->value && $activeWorkspace->parent->target_id) {
      $targetWorkspace = $activeWorkspace->parent->target_id;
    }


    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $keys = $entity_type->getKeys();
    $definition = [
      'table' => 'workspace_association',
      'field' => 'target_entity_id',
      'left_table' => $query_base_table,
      'left_field' => $keys['id'],
      'extra' => [
        [
          'field' => 'workspace',
          'value' => $targetWorkspace,
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

    $revisionJoin = $this->joinHandler->createInstance('standard', [
      'table' => $entity_type->getRevisionTable(),
      'field' => $keys['revision'],
      'left_table' => $associations,
      'left_field' => 'target_entity_revision_id',
      'extra' => "$associations.target_entity_revision_id > 0",
    ]);
    $query->addTable($query_base_table, $this->relationship, $revisionJoin);
    $alias = $definition['table'] . '_' . $this->table;
    $this->alias = $this->query->addRelationship($alias, $revisionJoin, $this->definition['base'], $this->relationship);
  }

}
