<?php

namespace Drupal\workspaces_negotiator_path;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class PathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * PathProcessor constructor.
   */
  public function __construct(WorkspaceManagerInterface $workspace_manager) {
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $current_workspace = &drupal_static('WorkspacesPathProcessor_active_workspace');
    if (!isset($current_workspace)) {
      $current_workspace = $this->workspaceManager->getActiveWorkspace();
    }
    if (empty($current_workspace)) {
      return $path;
    }
    $path_prefix = $current_workspace->get('path_prefix')->getValue();
    if (!empty($path_prefix[0]['value']) && $path_prefix[0]['value'] != '/') {
      // Check first if the path prefix of the workspace is really a prefix for
      // the current workspace. Only in that case we will remove the path
      // prefix.
      if (PathPrefixHelper::pathPrefixMatch($path, $path_prefix[0]['value'])) {
        $path = substr($path, strlen($path_prefix[0]['value']));
      }
    }
    // Make sure that we don't get an invalid (empty) path. In that case, just
    // set the path to '/'. This can happen when the request uri matches exactly
    // the path prefix.
    if (empty($path)) {
      $path = '/';
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $current_workspace = &drupal_static('WorkspacesPathProcessor_active_workspace');
    if (!isset($current_workspace)) {
      $current_workspace = $this->workspaceManager->getActiveWorkspace();
    }
    if (empty($current_workspace)) {
      return $path;
    }
    $workspace = !empty($options['source_workspace']) ? $options['source_workspace'] : $current_workspace;
    $path_prefix = $workspace->get('path_prefix')->getValue();
    if (!empty($path_prefix[0]['value']) && $path_prefix[0]['value'] != '/') {
      $options['prefix'] = $path_prefix[0]['value'] . '/' . $options['prefix'];
    }
    // Make sure that we did not create an invalid prefix (one that starts with
    // '/').
    if (strpos($options['prefix'], '/') === 0) {
      $options['prefix'] = substr($options['prefix'], 1);
    }
    return $path;
  }

}
