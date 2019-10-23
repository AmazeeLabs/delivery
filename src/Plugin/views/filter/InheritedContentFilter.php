<?php

namespace Drupal\delivery\Plugin\Views\filter;

use Drupal\delivery\Plugin\views\traits\CurrentWorkspaceViewsFilterTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;

/**
 * Filter a list of entities to show only entities relevant for the selected workspace
 * currently either the current workspace, or all other workspaces
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("inherited_content_filter")
 */
class InheritedContentFilter extends InOperator {

  use CurrentWorkspaceViewsFilterTrait {
    query as traitQuery;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->valueTitle = t('Allowed node titles');
    $this->definition['options callback'] = array($this, 'generateOptions');
  }

  /**
   * Adds condition to filter by current or !current workspace
   * This handles arrays so could be extended to be a list of
   * workspace, instead of just current and !current
   */
  public function query() {
    $this->traitQuery();
    $query_base_table = $this->relationship ?: $this->view->storage->get('base_table');

    $values = $this->value;
    $aliases = array_keys($this->query->tables[$query_base_table]);
    $alias = end($aliases);

    foreach ($values as $value) {
      $negate_condition = (substr($value, 0, 1) == '!');
      $operator = ($negate_condition) ? '!=' : '=';
      $condition = ($negate_condition) ? substr($value, 1) : $value;

      $this->query->addWhere('AND', $alias . '.workspace', $condition, $operator);
    }
  }

  /**
   * Helper function that generates the options.
   * @return array
   */
  public function generateOptions() {
    return array(
      '!'. $this->workspaceManager->getActiveWorkspace()->id() => $this->t('Inherited content'),
      $this->workspaceManager->getActiveWorkspace()->id() => $this->t('Own content'),
    );
  }

}
