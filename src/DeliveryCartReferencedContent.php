<?php


namespace Drupal\delivery;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
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
            self::addMenuParents($linkEntity);
          }
        }
      }
    }

    return self::$count;
  }

  /**
   * Looks for menu parents to also add them to the cart.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function addMenuParents(EntityInterface $entity){
    $parents = $entity->get('parent')->getValue();
    if(!empty($parents)){
      foreach ($parents as $parent){
        $parentId = $parent['value'] ? $parent['value'] : '';
        $parentId = str_replace('menu_link_content:','', $parentId);
        $entity = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $parentId);
        if($entity) {
          \Drupal::service('delivery.cart')->addToCart($entity);
          self::$count++;
          self::addMenuParents($entity);
        }
      }
    }
  }

  /**
   * Invokes the reference content hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public static function referenceContentHook(EntityInterface $entity){
    \Drupal::moduleHandler()->invokeAll('delivery_cart_referenced_content', [$entity]);
  }

  /**
   * Looks for any referenced blocks in layout builder and adds them to the cart.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return false|int
   */
  public static function addBlocksFromLayoutBuilder(EntityInterface $entity) {
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
   * Check if entity has a field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $field
   *
   * @return mixed
   */
  protected static function hasField(EntityInterface $entity, string $field){
    return $entity->hasField($field);
  }

}
