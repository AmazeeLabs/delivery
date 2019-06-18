<?php

namespace Drupal\workspaces_negotiator_path\Plugin\GraphQL\Fields;

use Drupal\Core\Path\PathValidatorInterface;
use Drupal\graphql\GraphQL\Cache\CacheableValue;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\workspaces_negotiator_path\PathPrefixWorkspaceNegotiator;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Retrieve a route object based on a path.
 *
 * @GraphQLField(
 *   id = "url_route",
 *   secure = true,
 *   name = "route",
 *   description = @Translation("Loads a route by its path and sets the active workspace."),
 *   type = "Url",
 *   arguments = {
 *     "path" = "String!"
 *   },
 *   weight = 1,
 * )
 */
class Route extends \Drupal\graphql_core\Plugin\GraphQL\Fields\Routing\Route {

  /**
   * The path prefix negotiator.
   *
   * @var \Drupal\workspaces_negotiator_path\PathPrefixWorkspaceNegotiator
   */
  protected $pathPrefixWorkspaceNegotiator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.validator'),
      $container->get('language_negotiator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('language_manager'),
      $container->get('redirect.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('path_processor_manager'),
      $container->get('workspaces_negotiator_path.prefix')
    );
  }

  /**
   * Route constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin id.
   * @param mixed $pluginDefinition
   *   The plugin definition.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path validator service.
   * @param \Drupal\language\LanguageNegotiator|null $languageNegotiator
   *   The language negotiator.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\redirect\RedirectRepository $redirectRepository
   *   The redirect repository, if redirect module is active.
   * @param \Drupal\Core\PathProcessor\InboundPathProcessorInterface
   *   An inbound path processor, to clean paths before redirect lookups.
   * @param \Drupal\workspaces_negotiator_path\PathPrefixWorkspaceNegotiator
   *   The workspace path prefix negotiator.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    PathValidatorInterface $pathValidator,
    $languageNegotiator,
    $languageManager,
    $redirectRepository,
    $pathProcessor,
    PathPrefixWorkspaceNegotiator $pathPrefixWorkspaceNegotiator
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $pathValidator, $languageNegotiator, $languageManager, $redirectRepository, $pathProcessor);
    $this->pathPrefixWorkspaceNegotiator = $pathPrefixWorkspaceNegotiator;
  }

  /**
   * {@inheritdoc}
   *
   * Execute routing in language and workspace context.
   *
   * Language context and workspace context have to be inferred from the path
   * prefix, but set before `resolveValues` is invoked.
   */
  public function resolve($value, array $args, ResolveContext $context, ResolveInfo $info) {
    $workspace = $this->pathPrefixWorkspaceNegotiator->getActiveWorkspaceByPath($args['path']);

    if ($workspace) {
      $context->setContext('workspace', $workspace->id(), $info);
    }

    return parent::resolve($value, $args, $context, $info);
  }

  /**
   * {@inheritDoc}
   */
  protected function isWorkspaceAwareField() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    if ($this->redirectRepository) {
      $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

      $processedPath = $this->pathProcessor
        ->processInbound($args['path'], Request::create($args['path']));

      if ($redirect = $this->redirectRepository->findMatchingRedirect($processedPath, [], $currentLanguage)) {
        yield $redirect;
        return;
      }
    }

    if (($url = $this->pathValidator->getUrlIfValidWithoutAccessCheck($args['path'])) && $url->access()) {
      yield $url;
    }
    else {
      yield (new CacheableValue(NULL))->addCacheTags(['4xx-response']);
    }
  }

}
