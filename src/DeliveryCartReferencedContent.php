<?php


namespace Drupal\delivery;

use Drupal\delivery\Entity\MenuLinkContent;
use Drupal\Core\Entity\EntityInterface;

class DeliveryCartReferencedContent {

  /**
   * Looks for any referenced menu items and adds them to the cart.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function addMenuItems(EntityInterface $entity){
    if(!self::isNode($entity)) {
      return;
    }

    $node_id = $entity->id();
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $routes = $menu_link_manager->loadLinksByRoute('entity.node.canonical', ['node' => $node_id]);

    if(!empty($routes)) {
      foreach ($routes as $menuLink){
        $plugin = $menuLink->getPluginDefinition();
        if(isset($plugin) && isset($plugin['metadata']) && isset($plugin['metadata']['entity_id'])){
          $id = $plugin['metadata']['entity_id'];
          $linkEntity = MenuLinkContent::load($id);
          if($linkEntity){
            \Drupal::service('delivery.cart')->addToCart($linkEntity);
          }
        }
      }
    }
  }

  /**
   * Confirm if entity is of type node.
   *
   * @param $entity
   *
   * @return bool
   */
  protected static function isNode($entity) {
    return $entity->getEntityTypeId() === "node";
  }

}
