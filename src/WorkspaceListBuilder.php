<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceListBuilder as OriginalWorkspaceListBuilder;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces\WorkspaceRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WorkspaceListBuilder
 *
 * Local override of workspaces entity list builder to replace the deploy
 * button.
 *
 * @package Drupal\delivery
 */
class WorkspaceListBuilder extends OriginalWorkspaceListBuilder {

  /**
   * @var \Drupal\delivery\WorkspaceAssigment
   */
  protected $workspaceAssigment;

  /**
   * The workspace repository service.
   *
   * @var \Drupal\workspaces\WorkspaceRepositoryInterface
   */
  protected $workspaceRepository;

  /**
   * WorkspaceListBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * @param \Drupal\delivery\WorkspaceAssigment $workspace_assigment
   * @param \Drupal\workspaces\WorkspaceRepositoryInterface $workspace_repository
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    WorkspaceManagerInterface $workspace_manager,
    RendererInterface $renderer,
    WorkspaceAssigment $workspace_assigment,
    WorkspaceRepositoryInterface $workspace_repository
  ) {
    $this->workspaceAssigment = $workspace_assigment;
    $this->workspaceRepository = $workspace_repository;
    parent::__construct($entity_type, $storage, $workspace_manager, $workspace_repository, $renderer);
  }

  /**
   * {@inheritDoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('workspaces.manager'),
      $container->get('renderer'),
      $container->get('delivery.workspace_assignment'),
      $container->get('workspaces.repository')
    );
  }

  /**
   * Loads entity IDs using a pager sorted by the entity ID.
   *
   * @return array
   *   An array of entity IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntityIds() {
    if (!$this->workspaceManager->getActiveWorkspace()) {
      return parent::getEntityIds();
    }
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'));

    if (!\Drupal::currentUser()->hasPermission('administer workspaces')) {
      $workspaces = $this->getUserWorkspaces();
      $query->condition($this->entityType->getKey('id'), $workspaces, 'IN');
    }
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_ids = $this->getEntityIds();
    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * @param $permission
   *
   * @return bool
   *
   * @todo Make this dynamic.
   */
  protected function hasPermission($permission) {
    return \Drupal::currentUser()
      ->hasPermission('add delivery to assigned workspaces');
  }

  /**
   * {@inheritdoc}
   */
  protected function offCanvasRender(array &$build) {
    parent::offCanvasRender($build);
    if (!$active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      return;
    }
    if ($active_workspace->id() == 'live') {
      return;
    }

    $build['active_workspace']['actions']['deploy']['#access'] = FALSE;
    $userWorkspaces = $this->getUserWorkspaces();

    $isAssigned = $this->hasPermission('add delivery to assigned workspaces') || !in_array($active_workspace->id(), $userWorkspaces);

    if (
      $this->hasPermission('add delivery to any workspace') ||
      ($isAssigned && $this->hasPermission('add delivery to assigned workspace'))
    ) {
      $build['active_workspace']['actions']['deliver'] = [
        '#type' => 'link',
        '#title' => t('Deliver content'),
        '#url' => Url::fromRoute('delivery.workspace_delivery_controller', ['workspace' => $active_workspace->id()]),
        '#attributes' => [
          'class' => ['button', 'active-workspace__button'],
        ],
      ];
    }
  }

  /**
   * Return workpsaces ids.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUserWorkspaces() {
    $account = \Drupal::currentUser();
    return $this->workspaceAssigment->getUserWorkspaces($account);
  }

}
