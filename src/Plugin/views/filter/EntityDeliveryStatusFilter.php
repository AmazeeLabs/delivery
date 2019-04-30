<?php

namespace Drupal\delivery\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\delivery\Plugin\views\traits\EntityDeliveryStatusTrait;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Filter workspace enabled entities by delivery status.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entity_delivery_status")
 */
class EntityDeliveryStatusFilter extends InOperator implements ContainerFactoryPluginInterface {
  use EntityDeliveryStatusTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->valueOptions = [
      static::$IDENTICAL => $this->getStatusLabel(static::$IDENTICAL),
      static::$MODIFIED => $this->getStatusLabel(static::$MODIFIED),
      static::$OUTDATED => $this->getStatusLabel(static::$OUTDATED),
      static::$CONFLICT => $this->getStatusLabel(static::$CONFLICT),
    ];

    $this->valueFormType = 'select';
  }

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
    parent::buildOptionsForm($form, $form_state);
    $this->buildDeliveryStatusOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->prepareEntityStatusQuery();

    if ($this->notApplicable && !$this->value) {
      return;
    }

    $condition = new Condition('OR');
    foreach ($this->value as $value) {
      switch ($value) {
        case static::$IDENTICAL:
          $condition->where(
            "{$this->sourceAlias} IS NULL AND {$this->targetAlias} IS NULL"
          );
          break;
        case static::$MODIFIED:
          $condition->where(
            "{$this->sourceAlias} IS NOT NULL AND {$this->targetAlias} IS NULL"
          );
          break;
        case static::$OUTDATED:
          $condition->where(
            "{$this->sourceAlias} IS NULL AND {$this->targetAlias} IS NOT NULL"
          );
          break;
        case static::$CONFLICT:
          $condition->where(
            "{$this->sourceAlias} IS NOT NULL AND {$this->targetAlias} IS NOT NULL"
          );
          break;
        default:
          break;
      }
    }
//    $this->query->addWhere(0, $condition);
  }
}
