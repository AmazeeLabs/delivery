<?php

namespace Drupal\delivery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

interface DeliveryInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

}
