<?php

namespace Drupal\delivery;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Overrides the default menu link manager.
 */
class MenuLinkManager implements MenuLinkManagerInterface {

  /**
   * The decorated menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $innerMenuLinkManager;

  /**
   * The workspace manager service.
   *
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a MenuLinkManager object.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   */
  public function __construct(MenuLinkManagerInterface $menu_link_manager, WorkspaceManagerInterface $workspace_manager) {
    $this->innerMenuLinkManager = $menu_link_manager;
    $this->workspaceManager = $workspace_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = [];

    // Ensure that menu link discovery is done in the context of the default
    // workspace.
    $this->workspaceManager->executeInWorkspace(WorkspaceInterface::DEFAULT_WORKSPACE, function () use (&$definitions) {
      $definitions = $this->innerMenuLinkManager->getDefinitions();
    });

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->innerMenuLinkManager->getDefinition($plugin_id, $exception_on_invalid);
  }

  /**
   * {@inheritdoc
   */
  public function hasDefinition($plugin_id) {
    return $this->innerMenuLinkManager->hasDefinition($plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    return $this->innerMenuLinkManager->createInstance($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->innerMenuLinkManager->getInstance($options);
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild() {
    $this->innerMenuLinkManager->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLinksInMenu($menu_name) {
    $this->innerMenuLinkManager->deleteLinksInMenu($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function removeDefinition($id, $persist = TRUE) {
    $this->innerMenuLinkManager->removeDefinition($id, $persist);
  }

  /**
   * {@inheritdoc}
   */
  public function loadLinksByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    return $this->innerMenuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function addDefinition($id, array $definition) {
    return $this->innerMenuLinkManager->addDefinition($id, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function updateDefinition($id, array $new_definition_values, $persist = TRUE) {
    return $this->innerMenuLinkManager->updateDefinition($id, $new_definition_values, $persist);
  }

  /**
   * {@inheritdoc}
   */
  public function resetLink($id) {
    return $this->innerMenuLinkManager->resetLink($id);
  }

  /**
   * {@inheritdoc}
   */
  public function countMenuLinks($menu_name = NULL) {
    return $this->innerMenuLinkManager->countMenuLinks($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentIds($id) {
    return $this->innerMenuLinkManager->getParentIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildIds($id) {
    return $this->innerMenuLinkManager->getChildIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function menuNameInUse($menu_name) {
    return $this->innerMenuLinkManager->menuNameInUse($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function resetDefinitions() {
    $this->innerMenuLinkManager->resetDefinitions();
  }

}
