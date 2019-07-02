<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Url;
use Drupal\delivery\Entity\DeliveryItem;
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

    $base_table = $this->relationship ?: $this->view->storage->get('base_table');
    $this->addAdditionalFields(['id', 'source_revision', 'resolution', 'entity_type', 'entity_id', 'target_workspace']);

    $currentRevisions = \Drupal::service('plugin.manager.views.join')->createInstance('standard', [
      'table' => 'workspace_association',
      'field' => 'entity_id',
      'type' => 'LEFT',
      'left_table' => $base_table,
      'left_field' => 'entity_id',
      'extra' => [
        ['left_field' => 'entity_type', 'field' => 'target_entity_type_id'],
        ['left_field' => 'target_workspace', 'field' => 'workspace'],
      ]
    ]);

    $currentRevisionsAlias = $this->query->addTable('workspace_association', $base_table, $currentRevisions);
    $this->aliases['current_revision'] = $this->query->addField($currentRevisionsAlias, 'revision_id', 'current_revision');
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    return [
      $values->{$this->aliases['id']},
      $values->{$this->aliases['entity_type']},
      $values->{$this->aliases['source_revision']},
      $values->{$this->field_alias},
      $values->{$this->aliases['resolution']},
      $values->{$this->aliases['current_revision']},
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    list($id, $entity_type, $source, $result, $resolution, $current) = $this->getValue($values);

    if ($result) {
      // If the result revision is not the target workspaces current revision,
      // or the resolution status
      // TODO: Modified status.
      $outdated = $result != $current || in_array($resolution, [
        DeliveryItem::RESOLUTION_MERGE,
        DeliveryItem::RESOLUTION_TARGET,
      ]);

      // We have a result revision. This item has already been fulfilled. Just
      // show the status.
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['entity-delivery-status', 'entity-delivery-status-' . ($outdated ? 'outdated' : 'identical')],
        ],
        '#value' => $outdated ? $this->t('Outdated') : $this->t('Identical'),
        '#attached' => ['library' => ['delivery/entity-status']],
      ];
    }
    else {
      $new = !$current;
      $destination = \Drupal::request()->getPathInfo();
      return [
        '#type' => 'link',
        '#title' => $new ? $this->t('New') : $this->t('Modified'),
        '#attributes' => [
          'class' => ['entity-delivery-status', 'entity-delivery-status-' . ($new ? 'new' : 'modified')],
        ],
        '#url' => Url::fromRoute($new ? 'delivery_item.push' : 'delivery_item.resolve', [
          // TODO: Properly retrieve the current delivery id.
          'delivery' => $this->view->args[0],
          'delivery_item' => $id,
        ], [
          'query' => [
            'destination' => $destination,
          ]
        ]),
        '#attached' => ['library' => ['delivery/entity-status']],
      ];
    }
  }
}
