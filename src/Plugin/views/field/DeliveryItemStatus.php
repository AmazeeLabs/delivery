<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Field handler to show the delivery status of a workspace enabled entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("delivery_item_status")
 */
class DeliveryItemStatus extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();
    $this->addAdditionalFields([
      'id',
      'resolution',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    return [
      $values->{$this->aliases['id']},
      $values->{$this->field_alias},
      $values->{$this->aliases['resolution']},
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    list($id, $resolution) = $this->getValue($values);

    return [
      '#type' => $resolution ? 'html_tag' : 'link',
      '#tag' => 'span',
      '#attributes' => [
        'class' => ['delivery-item-status', 'delivery-item-status-' . ($resolution ? 'resolved' : 'pending')],
        'data-delivery-item-id' => $id,
      ],
      '#url' => Url::fromRoute('delivery_item.resolve', [
        // TODO: Properly retrieve the current delivery id.
        'delivery' => $this->view->args[0],
        'delivery_item' => $id,
      ], [
        'query' => [
          'destination' => \Drupal::request()->getPathInfo(),
        ]
      ]),
      '#value' => $this->t('Resolved'),
      '#title' => $this->t('Pending'),
      '#attached' => ['library' => ['delivery/item-status']],
    ];
  }
}
