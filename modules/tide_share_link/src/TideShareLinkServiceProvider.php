<?php

namespace Drupal\tide_share_link;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\tide_share_link\Controller\ShareLinkTokenResource;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider class.
 *
 * @package Drupal\tide_share_link
 */
class TideShareLinkServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    // Check if jsonapi_extras is installed.
    if (isset($modules['jsonapi_extras'])) {
      if ($container->hasDefinition('jsonapi.entity_resource')) {
        $entity_resource = $container->getDefinition('jsonapi.entity_resource');
        // Pretends to be a JSON:API Controller.
        $container->register('jsonapi.entity_resource.share_link_token',
          ShareLinkTokenResource::class)
          ->setArguments($entity_resource->getArguments())
          ->addMethodCall('setContainer', [new Reference('service_container')]);
      }
    }
  }

}
