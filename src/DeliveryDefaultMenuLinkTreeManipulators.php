<?php

namespace Drupal\delivery;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Menu\DefaultMenuLinkTreeManipulators;
use Drupal\Core\Menu\InaccessibleMenuLink;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Decorates the DefaultMenuLinkTreeManipulators service to remove deleted
 * items.
 */
class DeliveryDefaultMenuLinkTreeManipulators implements DeliveryDefaultMenuLinkTreeManipulatorsInterface {

  /**
   * @var DefaultMenuLinkTreeManipulators
   */
  protected $menuLinkTreeManipulatorsInner;

  /**
   * @var EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * DeliveryDefaultMenuLinkTreeManipulators constructor.
   */
  public function __construct(DefaultMenuLinkTreeManipulators $menuLinkTreeManipulatorsInner, EntityRepositoryInterface $entityRepository) {
    $this->menuLinkTreeManipulatorsInner = $menuLinkTreeManipulatorsInner;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritDoc}
   */
  public function checkAccess(array $tree) {
    // Because we cannot call the protected menuLinkCheckAccess() here, we have
    // to call first the checkAccess() and the navigate the menu tree again to
    // remove deleted items.
    $tree = $this->menuLinkTreeManipulatorsInner->checkAccess($tree);
    $tree = $this->checkDeletedItems($tree);
    return $tree;
  }

  /**
   * Denies access to deleted items in a menu tree.
   */
  protected function checkDeletedItems(array $tree) {
    foreach ($tree as $key => $element) {
      // For any item which is allowed, check if it is deleted.
      if (isset($tree[$key]->access) && $tree[$key]->access->isAllowed() && $element->link instanceof MenuLinkContent) {
        $uuid = $element->link->getDerivativeId();
        $entity = $this->entityRepository->loadEntityByUuid('menu_link_content', $uuid);
        $deleted = $entity->get('deleted')->getValue()[0]['value'];
        // If the menu item is deleted, then deny the access and set an
        // InaccessibleMenuLink instead of the real link.
        if (!empty($deleted)) {
          $tree[$key]->access = AccessResult::forbidden()->cachePerPermissions();
          $tree[$key]->link = new InaccessibleMenuLink($tree[$key]->link);
        }
      }
      // In any case, also check the subtree.
      if ($tree[$key]->subtree) {
        $tree[$key]->subtree = $this->checkDeletedItems($tree[$key]->subtree);
      }
    }
    return $tree;
  }

  /**
   * {@inheritDoc}
   */
  public function checkNodeAccess(array $tree) {
    return $this->menuLinkTreeManipulatorsInner->checkNodeAccess($tree);
  }

  /**
   * {@inheritDoc}
   */
  public function generateIndexAndSort(array $tree) {
    return $this->menuLinkTreeManipulatorsInner->generateIndexAndSort($tree);
  }

  /**
   * {@inheritDoc}
   */
  public function flatten(array $tree) {
    return $this->menuLinkTreeManipulatorsInner->flatten($tree);
  }
}
