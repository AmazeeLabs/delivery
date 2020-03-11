<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the delivery URL of a workspace enabled entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("delivery_item_url")
 */
class DeliveryItemUrl extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $workspaceAssociation;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('workspaces.manager'),
      $container->get('workspaces.association')
    );
  }

  /**
   * DeliveryItemUrl constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspaceManager
   * @param \Drupal\workspaces\WorkspaceAssociationInterface $workspaceAssociation
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
    WorkspaceManagerInterface $workspaceManager,
    WorkspaceAssociationInterface $workspaceAssociation
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->workspaceManager = $workspaceManager;
    $this->workspaceAssociation = $workspaceAssociation;
  }


  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    $this->addAdditionalFields(['entity_type', 'entity_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    // If there is no entity.
    $entity = $this->entityTypeManager->getStorage($values->{$this->aliases['entity_type']})
      ->loadRevision($values->{$this->field_alias});
    if (!$entity) {
      return '';
    }
    // If this entity is not available in the current workspace.
    $current_workspace_id = $this->workspaceManager->getActiveWorkspace()->id();
    $ids = $this->workspaceAssociation->getEntityTrackingWorkspaceIds($entity);
    if (!in_array($current_workspace_id, $ids)) {
      return '';
    }
    return $entity->toLink()->getUrl()->toString();
  }
}
