<?php

namespace Drupal\tide_api\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Get Cache Id Event.
 *
 * @package Drupal\tide_api\Event
 */
class GetCacheIdEvent extends Event {

  /**
   * The Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The cache ID.
   *
   * @var string
   */
  protected $cacheId;

  /**
   * GetRouteEvent constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   * @param string $cache_id
   *   String.
   */
  public function __construct(Request $request, string $cache_id) {
    $this->request = $request;
    $this->cache_id = $cache_id;
  }

  /**
   * Returns cache id.
   *
   * @return string
   *   Cache id
   */
  public function getCacheId(): string {
    return $this->cache_id;
  }

  /**
   * Sets up cache id.
   *
   * @param string $cache_id
   *   The new cache id.
   */
  public function setCacheId(string $cache_id) {
    $this->cache_id = $cache_id;
  }

  /**
   * Returns the current Request object of the event.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The Request object.
   */
  public function getRequest(): Request {
    return $this->request;
  }

}
