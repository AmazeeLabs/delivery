<?php

namespace Drupal\delivery\Access;

use Drupal\content_translation\Access\ContentTranslationOverviewAccess;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Customized translation overview access handler.
 *
 * Block access if the current workspace has only one language assigned.
 */
class DeliveryContentTranslationOverviewAccess extends ContentTranslationOverviewAccess {

  protected $workspaceManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspaceManager) {
    parent::__construct($entity_type_manager);
    $this->workspaceManager = $workspaceManager;
  }

  public function access(RouteMatchInterface $route_match, AccountInterface $account, $entity_type_id) {
    /**
     * @todo @refactor : this uses a field which is not defined by the delivery
     * module: secondary_languages. It generates a fatal error.
     */
    /*$currentWorkspace = $this->workspaceManager->getActiveWorkspace();
    $access = AccessResult::forbiddenIf((
      $currentWorkspace->secondary_languages->count() === 0 && $currentWorkspace->primary_language->count() > 0
    ), 'Current workspace does not allow multiple languages.');
    $access->addCacheContexts(['workspace']);
    $result = parent::access($route_match, $account, $entity_type_id)->orIf($access);
    return $result;*/
    return parent::access($route_match, $account, $entity_type_id);
  }

}
