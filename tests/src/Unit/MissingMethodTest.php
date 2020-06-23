<?php

namespace Drupal\Tests\delivery\Unit;

use Drupal\delivery\Form\DeliveryPushForm;
use Drupal\Tests\UnitTestCase;

class MissingMethodTest extends UnitTestCase {

  const METHOD = 'pushChangesBatch';

  /**
   * @var \Drupal\delivery\Form\DeliveryPushForm
   */
  protected $form;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $messenger;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityRepository;

  /**
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $deliveryService;

  /**
   * Set up the form class.
   */
  public function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $this->entityRepository = $this->createMock('Drupal\Core\Entity\EntityRepositoryInterface');
    $this->deliveryService = $this->createMock('Drupal\delivery\DeliveryService');
    $this->form = new DeliveryPushForm($this->entityTypeManager, $this->messenger, $this->entityRepository, $this->deliveryService);
  }

  /**
   * Tests that the method does not exist.
   *
   * @throws \ReflectionException
   */
  public function testMethodDoesNotExist() {
    $reflectionClass = new \ReflectionClass($this->form);
    $this->assertFalse($reflectionClass->hasMethod(self::METHOD));
  }

  /**
   * Tests that an error is thrown when the method is called.
   */
  public function testMethodThrowsException() {
    $error_var = NULL;
    try {
      $this->form->{self::METHOD}();
    } catch (\Error $error) {
      $error_var = $error;
    }
    $this->assertInstanceOf('Error', $error_var);
  }

}
