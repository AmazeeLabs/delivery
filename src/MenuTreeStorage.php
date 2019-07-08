<?php

namespace Drupal\delivery;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Overrides the default menu storage to provide workspace-specific menu links.
 */
class MenuTreeStorage extends CoreMenuTreeStorage {

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The workspace association service.
   *
   * @var \Drupal\workspaces\WorkspaceAssociationInterface
   */
  protected $workspaceAssociation;

  /**
   * MenuTreeStorage constructor.
   *
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   A Database connection to use for reading and writing configuration data.
   * @param \Drupal\Core\Cache\CacheBackendInterface $menu_cache_backend
   *   Cache backend instance for the extracted tree data.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspace_association
   *   The workspace association service.
   * @param string $table
   *   A database table name to store configuration data in.
   * @param array $options
   *   (optional) Any additional database connection options to use in queries.
   */
  public function __construct(
    WorkspaceManagerInterface $workspace_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $connection,
    CacheBackendInterface $menu_cache_backend,
    CacheTagsInvalidatorInterface $cache_tags_invalidator,
    WorkspaceAssociationInterface $workspace_association,
    string $table,
    array $options = []
  ) {
    parent::__construct($connection, $menu_cache_backend, $cache_tags_invalidator, $table, $options);

    $this->workspaceAssociation = $workspace_association;
    $this->workspaceManager = $workspace_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadTreeData($menu_name, MenuTreeParameters $parameters) {
    // Add any non-default workspace as a menu tree condition parameter so it is
    // included in the cache ID.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if (!$active_workspace->isDefaultWorkspace()) {
      $parameters->conditions['workspace'] = $active_workspace->id();
    }
    return parent::loadTreeData($menu_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  protected function loadLinks($menu_name, MenuTreeParameters $parameters) {
    $links = parent::loadLinks($menu_name, $parameters);

    $all_menu_content_ids = array_filter(array_keys($links), function ($id) use ($links) {
      return $links[$id]['provider'] === 'menu_link_content';
    });

    // Replace the menu link plugin definitions with workspace-specific ones.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if (!$active_workspace->isDefaultWorkspace()) {
      $tracked_revisions = $this->workspaceAssociation->getTrackedEntities($active_workspace->id(), 'menu_link_content');
      if (isset($tracked_revisions['menu_link_content'])) {

        /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $workspace_revisions */
        $workspace_revisions = $this->entityTypeManager->getStorage('menu_link_content')->loadMultipleRevisions(array_keys($tracked_revisions['menu_link_content']));
        $workspace_plugin_ids = array_map(function (MenuLinkContent $menuLinkContent) {
          return $menuLinkContent->getPluginId();
        }, $workspace_revisions);

        foreach (array_diff($all_menu_content_ids, $workspace_plugin_ids) as $removable) {
          unset($links[$removable]);
        }

        foreach ($workspace_revisions as $workspace_revision) {
          if (isset($links[$workspace_revision->getPluginId()])) {
            $pending_plugin_definition = $workspace_revision->getPluginDefinition();
            $links[$workspace_revision->getPluginId()] = [
              'title' => serialize($pending_plugin_definition['title']),
              'description' => serialize($pending_plugin_definition['description']),
              'enabled' => (string) $pending_plugin_definition['enabled'],
              'url' => $pending_plugin_definition['url'],
              'route_name' => $pending_plugin_definition['route_name'],
              'route_parameters' => serialize($pending_plugin_definition['route_parameters']),
              'options' => serialize($pending_plugin_definition['options']),
            ] + $links[$workspace_revision->getPluginId()];
          }
        }
      }
    }

    return $links;
  }

}