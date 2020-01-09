<?php

namespace Drupal\delivery\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\delivery\WorkspaceAssigment;
use Drupal\workspaces\WorkspaceInterface;

class AssignedWorkspaceAccess implements AccessInterface {

  /**
   * @var \Drupal\delivery\WorkspaceAssigment
   *
   * The workspace assignment service.
   */
  protected $workspaceAssignment;

  public function __construct(WorkspaceAssigment $workspace_assigment) {
    $this->workspaceAssignment = $workspace_assigment;
  }

  public function access(WorkspaceInterface $workspace, AccountInterface $account) {
    if ($account->hasPermission('administer workspaces')) {
      return AccessResult::allowed();
    }

    $assigned_workspaces = $this->workspaceAssignment->getUserWorkspaces($account);
    if (in_array($workspace->id(), $assigned_workspaces)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }
}
