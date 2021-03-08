<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Service for managing the delivery cart.
 */
class DeliveryCartService {

  /**
   * The private temp store.
   * @var PrivateTempStore
   */
  protected $userPrivateStore;

  /**
   * The workspace manager service.
   * @var WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * The entity type manager service.
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, WorkspaceManagerInterface $workspaceManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->userPrivateStore = $tempStoreFactory->get('delivery');
    $this->workspaceManager = $workspaceManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Adds the entity from the current request to the delivery cart.
   */
  public function addToCart(EntityInterface $entity) {
    $cart = $this->userPrivateStore->get('delivery_cart');
    if (empty($cart)) {
      $cart = [];
    }
    $revisionId = $this->getEntityRevisionId($entity);
    $cart[$entity->getEntityTypeId()][$revisionId] = [
      'entity_id' => $entity->id(),
      'revision_id' => $revisionId,
      'workspace_id' => $this->workspaceManager->getActiveWorkspace()->id(),
    ];
    $this->userPrivateStore->set('delivery_cart', $cart);
    \Drupal::moduleHandler()->invokeAll('delivery_cart_add_item', [$entity]);
  }

  /**
   * Removes an entity from the cart.
   */
  public function removeFromCart(EntityInterface $entity) {
    $cart = $this->userPrivateStore->get('delivery_cart');
    $revisionId = $this->getEntityRevisionId($entity);
    if (!empty($cart) && isset($cart[$entity->getEntityTypeId()][$revisionId])) {
      unset($cart[$entity->getEntityTypeId()][$revisionId]);
      // Check if there are any entries left for this entity type. If not, just
      // remove it.
      if (empty($cart[$entity->getEntityTypeId()])) {
        unset($cart[$entity->getEntityTypeId()]);
      }

      // If the cart is empty, we can just unset it from the private store.
      if (empty($cart)) {
        $this->userPrivateStore->delete('delivery_cart');
      }
      else {
        $this->userPrivateStore->set('delivery_cart', $cart);
      }
    }
  }

  /**
   * Removes all items from the cart.
   */
  public function emptyCart() {
    $this->userPrivateStore->delete('delivery_cart');
  }

  /**
   * Checks if an entity exists in the cart.
   */
  public function entityExistsInCart(EntityInterface $entity) {
    $cart = $this->userPrivateStore->get('delivery_cart');
    $revisionId = $this->getEntityRevisionId($entity);
    if (!empty($cart[$entity->getEntityTypeId()][$revisionId])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the current delivery cart.
   */
  public function getCart() {
    return $this->userPrivateStore->get('delivery_cart');
  }

  /**
   * Returns the revision id of an entity.
   */
  protected function getEntityRevisionId(EntityInterface $entity) {
    $entityDefinition = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    $revisionIdField = $entityDefinition->getKey('revision');
    return $entity->{$revisionIdField}->getValue()[0]['value'];
  }
}
