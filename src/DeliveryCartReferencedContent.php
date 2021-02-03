<?php


namespace Drupal\delivery;

use Drupal\delivery\Entity\MenuLinkContent;
use Drupal\Core\Entity\EntityInterface;

class DeliveryCartReferencedContent {

  static $count = 0;


  /**
   * Looks for any referenced menu items and adds them to the cart.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return false|int
   */
  public static function addMenuItems(EntityInterface $entity){
    if(!self::isNode($entity)) {
      return FALSE;
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
            self::$count++;
          }
        }
      }
    }

    return self::$count;
  }

  public static function addMediaItems(EntityInterface $entity) {

  }

  public static function addBlocksFromLayoutBuilder(EntityInterface $entity) {
    if(!self::isNode($entity)) {
      return FALSE;
    }

    if(!self::hasField($entity,'layout_builder__layout')) {
      return FALSE;
    }

    $sections = $entity->get('layout_builder__layout')->getSections();

    foreach ($sections as $section){
      $components = $section->getComponents();
      foreach ($components as $component){
        $renderedArray = $component->toRenderArray();
        $theme = $renderedArray['#theme'] ? $renderedArray['#theme'] : '';
        if ($theme === "block") {
          $content = $renderedArray['content'] ? $renderedArray['content'] : '';
          if (isset($content['#block_content'])) {
            $blockContent = $content['#block_content'];
            \Drupal::service('delivery.cart')->addToCart($blockContent);
            self::$count++;
          }
        }
      }
    }

    return self::$count;
  }

  /**
   * Confirm if entity is of type node.
   *
   * @param $entity
   *
   * @return bool
   */
  protected static function isNode(EntityInterface $entity) {
    return $entity->getEntityTypeId() === "node";
  }

  protected static function hasField(EntityInterface $entity, string $field){
    return $entity->hasField($field);
  }

}
