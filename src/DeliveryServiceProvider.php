<?php

namespace Drupal\delivery;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class DeliveryServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    // Replace the workspaces manager with the override that skips entity
    // pre checks.
    if ($container->hasDefinition('workspaces.manager')) {
      $definition = $container->getDefinition('workspaces.manager');
      $definition->setClass(DeliveryWorkspaceManager::class);
    }
  }

}
