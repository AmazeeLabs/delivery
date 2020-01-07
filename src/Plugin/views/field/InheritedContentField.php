<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\delivery\DeliveryService;
use Drupal\node\Entity\NodeType;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the delivery label of a workspace enabled entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("inherited_content_field")
 */
class InheritedContentField extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\workspaces\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * @var \Drupal\delivery\DeliveryService
   */
  protected $deliveryService;

  /**
   * DeliveryInheritedContentField constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\workspaces\WorkspaceManagerInterface $workspace_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition ,WorkspaceManagerInterface $workspace_manager, DeliveryService $delivery_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->workspaceManager = $workspace_manager;
    $this->deliveryService = $delivery_service;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('workspaces.manager'),
      $container->get('delivery.service')
    );
  }

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? $values->_object->getEntity();

    if ($this->deliveryService->getEntityInheritedFlag($entity)) {
      return $this->t('Own content');
    }
    else {
      return $this->t('Inherited content');
    }
  }
}
