<?php


namespace Drupal\delivery\Plugin\Action\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DeliveryAddToCartActionDeriver extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The workspace manager service.
   * @var WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new DeliveryAddToCartActionDeriver.
   */
  public function __construct($base_plugin_id, WorkspaceManagerInterface $workspaceManager) {
    $this->basePluginId = $base_plugin_id;
    $this->workspaceManager = $workspaceManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('workspaces.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      $definitions = [];
      foreach ($this->workspaceManager->getSupportedEntityTypes() as $entityType) {
        $definition = $base_plugin_definition;
        $definition['type'] = $entityType->id();
        $definition['label'] = $this->t('Add @entity_type to delivery cart', ['@entity_type' => $entityType->getSingularLabel()]);
        $definitions[$entityType->id()] = $definition;
      }
      $this->derivatives = $definitions;
    }

    return $this->derivatives;
  }
}
