<?php

namespace Drupal\workspaces_negotiator_path\Plugin\LanguageNegotiation;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class for identifying language via URL prefix or domain.
 *
 * @LanguageNegotiation(
 *   id = \Drupal\workspaces_negotiator_path\Plugin\LanguageNegotiation\LanguageNegotiationWorkspaceAndUrl::METHOD_ID,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_URL},
 *   weight = -10,
 *   name = @Translation("Workspace prefix and URL"),
 *   description = @Translation("Language from the workspace path prefix and the URL (Path prefix or domain). All the settings can be provided in the URL language negotiation.")
 * )
 */
class LanguageNegotiationWorkspaceAndUrl extends LanguageNegotiationUrl implements ContainerFactoryPluginInterface, OutboundPathProcessorInterface {
  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-workspace-and-url';

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   * The workspace manager service.
   */
  protected $workspaceManager;

  /**
   * LanguageNegotiationWorkspaceAndUrl constructor.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager) {
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritdoc}
   * @todo: Find a way to not duplicate this pile of code.
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;

    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $config = $this->config->get('language.negotiation')->get('url');

      switch ($config['source']) {
        case LanguageNegotiationUrl::CONFIG_DOMAIN :
          $langcode = parent::getLangcode($request);
          break;
        case LanguageNegotiationUrl::CONFIG_PATH_PREFIX:
          // In some cases (when the field storage needs to be updated for
          // example because of a new field), loading a workspace may generate
          // a database exception, because the field is not yet there. To fix
          // this we just catch the exception and fallback to the parent
          // implementation.
          // Another case is when the this is called during a workspace
          // negotiation (that can happen in some cases when importing
          // configuration) when it will generate a
          // ServiceCircularReferenceException because a call to get the active
          // workspace will also trigger another workspace negotiation.
          try {
            $current_workspace = $this->workspaceManager->getActiveWorkspace();
          }
          catch (\RuntimeException $e) {
            watchdog_exception('workspace_language_negotiation', $e);
            return parent::getLangcode($request);
          }
          $path_prefix = $current_workspace->get('path_prefix')->getValue();
          $request_path = urldecode($request->getPathInfo());
          if (!empty($path_prefix[0]['value']) && $path_prefix[0]['value'] != '/') {
            // Check first if the path prefix of the workspace is really a prefix for
            // the current workspace. Only in that case we will remove the path
            // prefix.
            if (PathPrefixHelper::pathPrefixMatch($request_path, $path_prefix[0]['value'])) {
              $request_path = substr($request_path, strlen($path_prefix[0]['value']));
            }
          }

          $request_path = trim($request_path, '/');
          $path_args = explode('/', $request_path);
          $prefix = array_shift($path_args);

          // Search prefix within added languages.
          $negotiated_language = FALSE;
          foreach ($languages as $language) {
            if (isset($config['prefixes'][$language->getId()]) && $config['prefixes'][$language->getId()] == $prefix) {
              $negotiated_language = $language;
              break;
            }
          }

          if ($negotiated_language) {
            $langcode = $negotiated_language->getId();
          }
          break;
      }
    }

    return $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    // Make sure that we actually use the interface language, in case no
    // language is specified in the options. Otherwise, the parent method will
    // use the url language detection, which is actually not configurable.
    if (!isset($options['language'])) {
      $language = $this->languageManager->getCurrentLanguage();
      $options['language'] = $language;
    }
    // No need to actually call the parent processor here because it should be
    // called by the language manager.
    return $path;
  }
}
