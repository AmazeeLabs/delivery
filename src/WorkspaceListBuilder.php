<?php

namespace Drupal\delivery;

use Drupal\Core\Url;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceListBuilder as OriginalWorkspaceListBuilder;

/**
 * Local override of workspaces entity list builder to replace the deploy button.
 */
class WorkspaceListBuilder extends OriginalWorkspaceListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function offCanvasRender(array &$build) {
    parent::offCanvasRender($build);
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    $build['active_workspace']['actions']['deploy']['#access'] = FALSE;
    if ($active_workspace->id() !== 'live') {
      $build['active_workspace']['actions']['deliver'] = [
        '#type' => 'link',
        '#title' => t('Deliver content'),
        '#url' => Url::fromRoute('delivery.workspace_delivery_controller', ['workspace' => $active_workspace->id()]),
        '#attributes' => [
          'class' => ['button', 'active-workspace__button'],
        ]
      ];
    }
  }
}
