<?php

namespace Drupal\tide_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TideApiServiceProvider.
 *
 * @package Drupal\tide_api
 */
class TideApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('jsonapi.custom_query_parameter_names_validator.subscriber')) {
      $definition = $container->getDefinition('jsonapi.custom_query_parameter_names_validator.subscriber');
      $definition->setClass('\Drupal\tide_api\EventSubscriber\TideApiJsonApiRequestValidator');
      $definition->setArguments([new Reference('module_handler')]);
    }

    if ($container->hasDefinition('jsonapi.include_resolver')) {
      $definition = $container->getDefinition('jsonapi.include_resolver');
      $definition->setClass('\Drupal\tide_api\IncludeResolver');
    }

    if ($container->hasDefinition('jsonapi.entity_access_checker')) {
      $definition = $container->getDefinition('jsonapi.entity_access_checker');
      $definition->setClass('\Drupal\tide_api\Access\EntityAccessChecker');
    }
  }

}
