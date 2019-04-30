<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\delivery\Plugin\views\traits\EntityDeliveryStatusTrait;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Field handler to show the delivery status of a workspace enabled entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_delivery_status")
 */
class EntityDeliveryStatusField extends FieldPluginBase implements ContainerFactoryPluginInterface {
  use EntityDeliveryStatusTrait;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return parent::defineOptions() + $this->defineDeliveryStatusOptions() + [
      'link_to_resolver' => ['default' => 0],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $this->buildDeliveryStatusOptionsForm($form, $form_state);
    $form['link_to_resolver'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to resolution'),
      '#description' => $this->t('In case of modification or conflict, link the label to the conflict resolution page.'),
      '#default_value' => $this->options['link_to_resolver'],
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->prepareEntityStatusQuery();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $values, $field = NULL) {
    $alias = 'entity_delivery_status';

    if ($this->notApplicable) {
      $values->{$alias} = static::$NOT_APPLICABLE;
      return $values->{$alias};
    }

    $source = $values->{$this->sourceAlias};
    $target = $values->{$this->targetAlias};
    // Lowest common ancestor.
    $lca = $values->{$this->lcaAlias};
    // Revision target workspace ID.
    $rev_target = $values->{$this->revisionTargetWorkspace};

    // Conflict.
    if ($source !== $target && $target !== $lca && $source !== $lca) {
     $values->{$alias} = static::$CONFLICT;
    }
    // Outdated.
    else if ($source === $lca && $target !== $lca) {
      $values->{$alias} = static::$OUTDATED;
    }
    else if ($source !== $lca && $target === $lca) {
      // New.
      if (!$rev_target) {
        $values->{$alias} = static::$NEW;
      }
      // Modified.
      else {
        $values->{$alias} = static::$MODIFIED;
      }
    }
    // Identical.
    else {
      $values->{$alias} = static::$IDENTICAL;
    }

    return $values->{$alias};
  }

  public function postExecute(&$values) {
    if (count($values) === 0) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($this->getEntityType());

    foreach ($values as $row) {
      $row->source_revision = $row->{$this->sourceAlias};
      $row->target_revision = $row->{$this->targetAlias};
      $row->_entity = $storage->loadRevision($row->source_revision);
      // Add the actual processed value to the object for easy access.
      $row->status_value = $this->getValue($row);
    }

    parent::postExecute($values);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    $classes = [
      static::$NOT_APPLICABLE => 'not-applicable',
      static::$IDENTICAL => 'identical',
      static::$MODIFIED => 'modified',
      static::$OUTDATED => 'outdated',
      static::$CONFLICT => 'conflict',
      static::$NEW => 'new',
    ];

    $onTarget = $this->workspaceManager->getActiveWorkspace()->id() === $this->getTargetWorkspace();
    if ($onTarget && $this->options['link_to_resolver'] && in_array($value, [static::$MODIFIED, static::$CONFLICT])) {
      $destination = \Drupal::request()->getPathInfo();
      return [
        '#type' => 'link',
        '#title' => $this->getStatusLabel($value),
        '#attributes' => [
          'class' => ['entity-delivery-status', 'entity-delivery-status-' . $classes[$value]],
        ],
        '#url' => Url::fromRoute('revision_tree.resolve_conflicts', [
          'entity_type' => $this->getEntityType(),
          'revision_a' => $values->source_revision,
          'revision_b' => $values->target_revision,
        ], [
          'query' => [
            'destination' => $destination,
          ]
        ]),
        '#attached' => ['library' => ['delivery/entity-status']],
      ];
    }
    else {
      return [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['entity-delivery-status', 'entity-delivery-status-' . $classes[$value]],
        ],
        '#value' => $this->getStatusLabel($value),
        '#attached' => ['library' => ['delivery/entity-status']],
      ];
    }
  }
}
