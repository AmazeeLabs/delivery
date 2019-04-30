<?php

namespace Drupal\delivery\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\delivery\DeliveryInterface;
use Drupal\delivery\DeliveryService;
use Drupal\workspaces\WorkspaceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class DeliveryStatusController
 *
 * @package Drupal\delivery\Controller
 */
class DeliveryStatusController extends ControllerBase {

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

  /**
   * Get action for the controller.
   *
   * @param $workspace
   * @param $delivery
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   */
  public function getAction(WorkspaceInterface $workspace, DeliveryInterface $delivery) {
    $response_code = 200;
    $data = $this->deliveryService->getDeliveryDataByWorkspace($workspace, $delivery);
    if (!$data) {
      $response_code = 404;
    }
    return new JsonResponse($data, $response_code);
  }

}
