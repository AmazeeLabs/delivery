<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\delivery\Plugin\views\traits\EntityDeliveryStatusTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler that provides an AJAX delivery status field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("delivery_delivery_status")
 */
class DeliveryAjaxDeliveryStatusField extends FieldPluginBase implements ContainerFactoryPluginInterface {

  use EntityDeliveryStatusTrait;

  protected $deliveryWorkspacesTargetID;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return parent::defineOptions() + $this->defineDeliveryStatusOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $this->buildDeliveryStatusOptionsForm($form, $form_state);
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->deliveryWorkspacesTargetID = $this->query->addField('delivery__workspaces', 'workspaces_target_id', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (empty($values->delivery__workspaces_workspaces_target_id)) {
      return '';
    }
    $span = '<span class="entity-status-callback" data-workspace-id="' . $values->delivery__workspaces_workspaces_target_id . '" data-delivery-id="' . $values->_entity->id() . '"></span>';
    $markup = Markup::create($span);
    return $markup;
  }

}
