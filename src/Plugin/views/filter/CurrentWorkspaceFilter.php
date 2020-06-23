<?php

namespace Drupal\delivery\Plugin\Views\filter;

use Drupal\delivery\Plugin\views\traits\CurrentWorkspaceViewsFilterTrait;
use Drupal\views\Plugin\views\filter\LatestRevision;

/**
 * Filter a list of entities to show only entities relevant for the current workspace.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("current_workspace_filter")
 */
class CurrentWorkspaceFilter extends LatestRevision {

  /**
   * Provides our query() and dependencies
   */
  use CurrentWorkspaceViewsFilterTrait;

}
