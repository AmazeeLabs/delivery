<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the delivery label of a workspace enabled entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("delivery_item_label")
 */
class DeliveryItemLabel extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
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
    $entity = $this->entityTypeManager->getStorage($values->{$this->aliases['entity_type']})->loadRevision($values->{$this->field_alias});
    if (!$entity) {
      return $this->t('Corresponding content not found');
    }
    return $entity->label();
  }

}
