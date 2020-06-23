<?php

namespace Drupal\delivery;


interface DeliveryDefaultMenuLinkTreeManipulatorsInterface {

  public function checkAccess(array $tree);

  public function checkNodeAccess(array $tree);

  public function generateIndexAndSort(array $tree);

  public function flatten(array $tree);
}
