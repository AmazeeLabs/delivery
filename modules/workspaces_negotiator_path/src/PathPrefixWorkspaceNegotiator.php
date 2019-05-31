<?php

namespace Drupal\workspaces_negotiator_path;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\Negotiator\WorkspaceNegotiatorInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces_negotiator_path\Utils\PathPrefixHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class PathPrefixWorkspaceNegotiator implements WorkspaceNegotiatorInterface {
  /**
   * The workspace storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $workspaceStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->workspaceStorage = $entity_type_manager->getStorage('workspace');
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveWorkspace(Request $request) {
    $valid_workspaces = array_map(function ($workspace) {
      return [
        'id' => $workspace->id(),
        'path_prefix' => $workspace->get('path_prefix')->getValue()[0]['value'],
      ];
    }, $this->getValidWorkspaces());
    $best_fit = PathPrefixHelper::findBestPathPrefixFit(urldecode($request->getPathInfo()), $valid_workspaces);
    if (!empty($best_fit)) {
      if ($best_fit['id'] && ($workspace = $this->workspaceStorage->load($best_fit['id']))) {
        return $workspace;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setActiveWorkspace(WorkspaceInterface $workspace) {}

  /**
   * Returns an array with all the workspaces which should be checked by the
   * path prefix negotiator.
   */
  protected function getValidWorkspaces() {
    // This is a bit odd, but if we just call the loadMultiple() method with no
    // arguments, it generates and error like this one: https://www.drupal.org/project/devel/issues/2999494
    // sometimes in cli (when using drush scr for example and try to get a
    // workspace entity storage instance).
    $workspaces = $this->workspaceStorage->loadMultiple($this->workspaceStorage->getQuery()->execute());
    return array_filter($workspaces, function ($workspace) {
      // Remove the workspaces which have an empty path_prefix field.
      $path_prefix = $workspace->get('path_prefix')->getValue();
      return (!empty($path_prefix[0]['value']));
    });
  }

}
