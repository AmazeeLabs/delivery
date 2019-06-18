<?php

namespace Drupal\workspaces_negotiator_path\Plugin\GraphQL\Fields;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Get the current workspace.
 *
 * @GraphQLField(
 *   id = "active_workspace",
 *   secure = true,
 *   name = "activeWorkspace",
 *   parents = {"InternalUrl"},
 *   type = "[entity:workspace]"
 * )
 */
class ActiveWorkspace extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The workspace manager.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('workspaces.manager')
    );
  }

  /**
   * ActiveWorkspace constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   *   The workspace manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    WorkspaceManagerInterface $workspaceManager
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    // WorkspacesPathProcessor_active_workspace
    $activeWorkspace = $this->workspaceManager->getActiveWorkspace();
    if ($activeWorkspace) {
      yield $activeWorkspace;
    }
  }

}
