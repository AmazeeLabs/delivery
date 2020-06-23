<?php

/**
 * @file
 * Provide views runtime hooks for delivery.module.
 */

use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_query_substitutions().
 *
 * Allow replacement of current user ID so we can cache these queries.
 */
function delivery_views_query_substitutions(ViewExecutable $view) {
  $active_workspace = \Drupal::getContainer()->get('workspaces.manager')->getActiveWorkspace();
  return $active_workspace ? ['***CURRENT_WORKSPACE***' => $active_workspace->id()] : [];
}
