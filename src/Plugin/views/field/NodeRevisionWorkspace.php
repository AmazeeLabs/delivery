<?php

namespace Drupal\delivery\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Field handler to add the workspace field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("node_revision_workspace")
 */
class NodeRevisionWorkspace extends FieldPluginBase {

  /**
   * @param \Drupal\views\ViewExecutable $view
   * @param \Drupal\views\Plugin\views\display\DisplayPluginBase $display
   * @param array|null $options
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields['workspace'] = [
      'table' => 'node_revision',
      'field' => 'workspace',
    ];
  }

  /**
   * @param \Drupal\views\ResultRow $values
   *
   * @return \Drupal\Component\Render\MarkupInterface|\Drupal\views\Render\ViewsRenderPipelineMarkup|string
   */
  public function render(ResultRow $values) {
    return $this->getValue($values, 'workspace');
  }

}
