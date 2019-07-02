<?php

namespace Drupal\delivery;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\delivery\Access\DeliveryContentTranslationOverviewAccess;
use Drupal\delivery\ParamConverter\DeliveryEntityConverter;
use Symfony\Component\DependencyInjection\Reference;

class DeliveryServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('paramconverter.entity')) {
      $container
        ->getDefinition('paramconverter.entity')
        ->setClass(DeliveryEntityConverter::class);
    }

    if ($container->hasDefinition('content_translation.overview_access')) {
      $definition = $container->getDefinition('content_translation.overview_access');
      $definition->setClass(DeliveryContentTranslationOverviewAccess::class)
        ->addArgument(new Reference('workspaces.manager'));
    }
  }

}
