<?php

namespace Drupal\delivery;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInterface;
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
    if ($active_workspace = $this->workspaceManager->hasActiveWorkspace()) {
      $active_workspace = $this->workspaceManager->getActiveWorkspace();
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
      $this->currentWorkspace = $active_workspace->id();
      $localLinks = parent::loadLinks($menu_name, $parameters);
      $this->currentWorkspace = NULL;

      /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $workspace_revisions */
      $workspace_revisions = isset($tracked_revisions['menu_link_content'])
        ? $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadMultipleRevisions(array_keys($tracked_revisions['menu_link_content']))
        : [];

      $workspace_plugin_ids = array_map(function (MenuLinkContent $menuLinkContent) {
        return $menuLinkContent->getPluginId();
      }, $workspace_revisions);

      $recheckParenthood = [];

      foreach (array_diff($all_menu_content_ids, $workspace_plugin_ids) as $removable) {
        if ($links[$removable]['parent']) {
          $recheckParenthood[] = $links[$removable]['parent'];
        }
        unset($links[$removable]);
      }

      foreach ($workspace_revisions as $workspace_revision) {
        if (isset($links[$workspace_revision->getPluginId()])) {
          $links[$workspace_revision->getPluginId()] = $localLinks[$workspace_revision->getPluginId()] ?? [] + $links[$workspace_revision->getPluginId()];
        }
      }

      foreach ($recheckParenthood as $parentId) {
        if (isset($links[$parentId])) {
          $links[$parentId]['has_children'] = count(array_filter($links, function ($link) use ($parentId) {
            return $link['parent'] === $parentId;
          })) > 0 ? '1' : '0';
        }
      }
    }

    return $links;
  }

  protected static function schemaDefinition() {
    $schema = parent::schemaDefinition();
    $schema['fields']['workspace'] = [
      'type' => 'varchar_ascii',
      'length' => 128,
      'not null' => FALSE,
      'description' => 'The workspace ID.',
    ];
    $schema['indexes']['workspace'] = ['workspace'];
    $schema['unique keys']['id'] = ['id', 'workspace'];
    return $schema;
  }

  protected $currentWorkspace = NULL;

  protected function doSave(array $link) {
    $affected_menus = parent::doSave($link);
    if ($link['provider'] === 'menu_link_content') {
      $entityId = $link['metadata']['entity_id'];
      /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('menu_link_content');
      // TODO: Integrate this into WorkspaceAssociation.
      $result = \Drupal::database()->select('workspace_association', 'wa')
        ->fields('wa', ['workspace', 'target_entity_revision_id'])
        ->condition('target_entity_type_id', 'menu_link_content')
        ->condition('target_entity_id', $entityId)
        ->execute();
      while($row = $result->fetch()) {
        $this->currentWorkspace = $row->workspace;
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface $localLinkEntity */
        $localLinkEntity = $storage->loadRevision($row->target_entity_revision_id);
        $pluginDefinition = $localLinkEntity->getPluginDefinition();
        $pluginDefinition['workspace'] = $row->workspace;
        $affected_menus += parent::doSave($pluginDefinition);
      }
      $this->currentWorkspace = NULL;
    }
    return $affected_menus;
  }

  protected function safeExecuteSelect(SelectInterface $query) {
    if ($this->currentWorkspace) {
      $query->condition('workspace', $this->currentWorkspace);
    }
    else {
      $query->isNull('workspace');
    }
    return parent::safeExecuteSelect($query);
  }

  protected function preSave(array &$link, array $original) {
    $fields = parent::preSave($link, $original);
    if (isset($link['workspace'])) {
      $fields['workspace'] = $link['workspace'];
    }
    return $fields;
  }

}
