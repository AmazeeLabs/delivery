<?php

namespace Drupal\delivery\Plugin\views\traits;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\revision_tree\RevisionTreeQueryInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Common functionality for the.
 */
trait EntityDeliveryStatusTrait {
  public static $NOT_APPLICABLE = -1;
  public static $IDENTICAL = 0;
  public static $MODIFIED = 1;
  public static $OUTDATED = 2;
  public static $CONFLICT = 3;
  public static $NEW = 4;


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
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\revision_tree\RevisionTreeQueryInterface
   */
  protected $revisionTreeQuery;

  /**
   * The default workspace id.
   *
   * @var string
   */
  protected $defaultWorkspace;

  /**
   * Field alias containing the revision the source workspace.
   *
   * @var string
   */
  protected $sourceAlias;

  /**
   * Field alias containing the revision on the target workspace.
   *
   * @var string
   */
  protected $targetAlias;

  /**
   * Field alias containing the lca revision id.
   *
   * @var string
   */
  protected $lcaAlias;

  /**
   * The revision target workspace id.
   *
   * @var string
   */
  protected $revisionTargetWorkspace;

  /**
   * The source workspace id.
   *
   * @var string
   */
  protected $sourceWorkspace;

  /**
   * The target workspace id.
   *
   * @var string
   */
  protected $targetWorkspace;

  /**
   * Indicator that the field currently is not applicable.
   *
   * @var bool
   */
  protected $notApplicable = FALSE;

  /**
   * Constructs a new WorkspaceCompare field.
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
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   *   The workspace manager.
   * @param \Drupal\revision_tree\RevisionTreeQueryInterface $revisionTreeQuery
   *   The revision tree querying service.
   * @param string $defaultWorkspace
   *   The default workspace id.
   */
  public function __construct(
    array $configuration,
  $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ViewsHandlerManager $join_handler,
    WorkspaceManagerInterface $workspaceManager,
    RevisionTreeQueryInterface $revisionTreeQuery,
    $defaultWorkspace
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->joinHandler = $join_handler;
    $this->workspaceManager = $workspaceManager;
    $this->revisionTreeQuery = $revisionTreeQuery;
    $this->defaultWorkspace = $defaultWorkspace;
  }

  protected function getWorkspaceHierarchy($workspaceId) {
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($workspaceId);
    $context = [$workspace->id()];
    while ($workspace = $workspace->parent_workspace->entity) {
      $context[] = $workspace->id();
    }
    $context[] = NULL;
    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.views.join'),
      $container->get('workspaces.manager'),
      $container->get('revision_tree.query'),
      $container->hasParameter('workspace.default')
        ? $container->getParameter('workspace.default')
        : 'live'
    );
  }

  protected function defineDeliveryStatusOptions() {
    return [
      'source_workspace' => ['default' => '__current'],
      'target_workspace' => ['default' => '__parent'],
      'source_workspace_arg' => ['default' => 0],
      'target_workspace_arg' => ['default' => 1],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildDeliveryStatusOptionsForm(&$form, FormStateInterface $form_state) {
    $workspaceOptions = array_map(function (Workspace $workspace) {
        return $workspace->label();
    }, $this->entityTypeManager->getStorage('workspace')->loadMultiple()) + [
      '__current' => $this->t('Current workspace'),
      '__parent' => $this->t('Parent of current workspace'),
      '__argument' => $this->t('From views argument'),
    ];

    $form['source_workspace'] = [
      '#type' => 'select',
      '#title' => $this->t('Source workspace'),
      '#options' => $workspaceOptions,
      '#description' => $this->t('Select a source workspace for the status comparison.'),
      '#default_value' => $this->options['source_workspace'],
    ];

    $form['source_workspace_arg'] = [
      '#type' => 'number',
      '#title' => $this->t('Source workspace argument'),
      '#description' => $this->t('The argument number to get the source workspace from.'),
      '#states' => [
        'visible' => [
          'select[name="options[source_workspace]"]' => ['value' => '__argument'],
        ],
      ],
      '#default_value' => $this->options['source_workspace_arg'],
    ];

    $form['target_workspace'] = [
      '#type' => 'select',
      '#title' => $this->t('Target workspace'),
      '#options' => $workspaceOptions,
      '#description' => $this->t('Select a target workspace for the status comparison.'),
      '#default_value' => $this->options['target_workspace'],
    ];

    $form['target_workspace_arg'] = [
      '#type' => 'number',
      '#title' => $this->t('Target workspace argument'),
      '#description' => $this->t('The argument number to get the target workspace from.'),
      '#states' => [
        'visible' => [
          'select[name="options[target_workspace]"]' => ['value' => '__argument'],
        ],
      ],
      '#default_value' => $this->options['target_workspace_arg'],
    ];

  }

  public function getSourceWorkspace() {
    if (!$this->sourceWorkspace) {
      $this->sourceWorkspace = $this->getWorkspaceArgument('source');
    }
    return $this->sourceWorkspace;
  }

  public function getTargetWorkspace() {
    if (!$this->targetWorkspace) {
      $this->targetWorkspace = $this->getWorkspaceArgument('target');
    }
    return $this->targetWorkspace;
  }

  protected function getWorkspaceArgument($key) {
    switch ($this->options[$key . '_workspace']) {
      case '__current':
        return $this->workspaceManager->getActiveWorkspace()->id();

      break;
      case '__parent':
        $parent = $this->workspaceManager->getActiveWorkspace()->parent_workspace->entity;
        if ($parent) {
          return $parent->id();
        }
        break;

      case '__argument':
        $arg = intval($this->options[$key . '_workspace_arg']);
        if (isset($this->view->args[$arg])) {
          return $this->view->args[$arg];
        }
        break;

      default:
        return $this->options[$key . '_workspace'];
      break;
    }
  }

  public function prepareEntityStatusQuery() {
    if (isset($this->query->fields['num_source']) && isset($this->query->fields['num_target'])) {
      $this->sourceAlias = $this->query->fields['num_source']['alias'];
      $this->targetAlias = $this->query->fields['num_target']['alias'];
      return;
    }

    $source = $this->getSourceWorkspace();
    $target = $this->getTargetWorkspace();

    $this->query->entityStatusApplied = TRUE;

    if (!($source && $target)) {
      $this->notApplicable = TRUE;
      return;
    }

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());

    $sourceHierarchy = $this->getWorkspaceHierarchy($source);
    $targetHierarchy = $this->getWorkspaceHierarchy($target);
    $lcaHierarchy = array_intersect($sourceHierarchy, $targetHierarchy);

    $activeSourceLeaves = $this->revisionTreeQuery->getActiveLeaves($entity_type, ['workspace' => [$source, $target]]);
    $activeTargetLeaves = $this->revisionTreeQuery->getActiveLeaves($entity_type, ['workspace' => [$target]]);
    $activeTargetLeaves->addField('base', 'workspace', 'targetworkspace');

    $activeLcaLeaves = $this->revisionTreeQuery->getActiveLeaves($entity_type, ['workspace' => array_shift($lcaHierarchy)]);

    $base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $keys = $entity_type->getKeys();

    $baseQuery = \Drupal::database()->select($entity_type->getRevisionTable(), 'base');
    $baseQuery->addField('base', $keys['id'], 'entity_id');

    // If the first argument is set, we display the status of an existing
    // delivery, else its the current workspace changed state.
    // Else its the current workspace status. In the latter case, we have to restrict
    // the list of revisions to the on
    // TODO: Separate this properly.
    if (!$this->view->args[0]) {
      $baseQuery->condition('workspace', $source);
    }

    $baseQuery->innerJoin($activeSourceLeaves, 'source', "base.{$keys['revision']} = source.revision_id");
    $baseQuery->innerJoin($activeTargetLeaves, 'target', "base.{$keys['id']} = target.entity_id");
    $baseQuery->innerJoin($activeLcaLeaves, 'lca', "base.{$keys['id']} = lca.entity_id");
    $baseQuery->addField('source', 'revision_id', 'source_revision');
    $baseQuery->addField('target', 'revision_id', 'target_revision');
    $baseQuery->addField('target', 'targetworkspace', 'targetworkspace');
    $baseQuery->addField('lca', 'revision_id', 'lca_revision');

    // If the first argument is set, we display the status of an existing delivery.
    // This means we join with the entity id. In the other case we have to restrict
    // the list of revisions to the leaves of the current workspace and therefore
    // join using the source revision.
    // TODO: Properly separate this.
    if (!$this->view->args[0]) {
      $revisionsTable = $this->joinHandler->createInstance(
        'standard',
        [
          'table' => $baseQuery,
          'field' => 'source_revision',
          'type' => 'INNER',
          'left_table' => $base_table,
          'left_field' => $keys['revision'],
        ]
      );
    }
    else {
      $revisionsTable = $this->joinHandler->createInstance(
        'standard',
        [
          'table' => $baseQuery,
          'field' => 'entity_id',
          'type' => 'INNER',
          'left_table' => $base_table,
          'left_field' => $keys['id'],
        ]
          );
    }

    $revisionsAlias = $this->query->addTable($entity_type->getRevisionTable() . '_source', $this->relationship, $revisionsTable);

    $this->sourceAlias = $this->query->addField($revisionsAlias, 'source_revision', 'source_rev');
    $this->targetAlias = $this->query->addField($revisionsAlias, 'target_revision', 'target_rev');
    $this->lcaAlias = $this->query->addField($revisionsAlias, 'lca_revision', 'lca_rev');
    $this->revisionTargetWorkspace = $this->query->addField($revisionsAlias, 'targetworkspace', 'rev_target');
  }

}
