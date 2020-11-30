<?php

namespace Drupal\tide_api\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class TideApi Route Alter Subscriber.
 *
 * @package Drupal\tide_api\EventSubscriber
 */
class TideApiRouteAlterSubscriber implements EventSubscriberInterface {

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * JsonApiExtrasRouteAlterSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RoutingEvents::ALTER => [['onRoutingRouteAlterReplaceJsonapiPrefix']],
    ];
  }

  /**
   * Alters routes suffixed with `.jsonapi` to update the path with prefix.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event to process.
   */
  public function onRoutingRouteAlterReplaceJsonapiPrefix(RouteBuildEvent $event) {
    $default_prefix = 'jsonapi';

    $prefix = $this->configFactory
      ->get('jsonapi_extras.settings')
      ->get('path_prefix');

    // Only update routes if jsonapi_extras configuration exists and is
    // different from default prefix $default_prefix.
    if ($prefix && $prefix != $default_prefix) {
      $prefix = sprintf('/%s/', $prefix);

      $collection = $event->getRouteCollection();
      foreach ($collection->getIterator() as $name => $route) {
        if (strpos($name, 'jsonapi.tide_api') === 0) {
          $path = str_replace('/' . $default_prefix . '/', $prefix, $route->getPath());
          $collection->get($name)->setPath($path);
        }
      }
    }
  }

}
