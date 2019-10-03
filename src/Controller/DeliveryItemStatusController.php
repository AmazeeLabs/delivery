<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\delivery\DeliveryService;
use Drupal\delivery\Entity\DeliveryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class DeliveryItemStatusController extends ControllerBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\delivery\DeliveryService
   */
  protected $deliveryService;

  /**
   * DeliveryStatusController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\delivery\DeliveryService $delivery_service
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DeliveryService $delivery_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->deliveryService = $delivery_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('delivery.service')
    );
  }

  public function getStatus(DeliveryItem $delivery_item) {
    return new JsonResponse($delivery_item->getStatus());
  }
}
