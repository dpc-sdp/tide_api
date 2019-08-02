<?php

namespace Drupal\tide_api;

/**
 * Contains all events dispatched from Tide API.
 */
class TideApiEvents {

  /**
   * The event dispatched when Tide API finishes processing its route.
   *
   * @Event("Drupal\tide_api\Event\GetRouteEvent")
   */
  const GET_ROUTE = 'tide_api.get_route';

  /**
   * The event dispatched when Tide API generates its cache id.
   *
   * @Event("Drupal\tide_api\Event\GetCacheIdEvent")
   */
  const GET_CACHE_ID = 'tide_api.get_cache_id';

}
