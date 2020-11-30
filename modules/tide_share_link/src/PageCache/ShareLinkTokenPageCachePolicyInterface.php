<?php

namespace Drupal\tide_share_link\PageCache;

use Symfony\Component\HttpFoundation\Request;

/**
 * The interface for determining the requests with Share Link Token.
 *
 * The service that implements the interface is used to determine whether
 * the page should be served from cache and also if the request contains
 * an access token to proceed to the authentication.
 *
 * @see \Drupal\tide_share_link\PageCache\DisallowShareLinkTokenAuthorizationRequests::check()
 * @see \Drupal\tide_share_link\Authentication\Provider\ShareLinkTokenAuthenticationProvider::applies()
 */
interface ShareLinkTokenPageCachePolicyInterface {

  /**
   * Returns a state whether the request has a Share Link Token.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request object.
   *
   * @return bool
   *   A state whether the request has a Share Link Token.
   */
  public function isShareLinkTokenAuthorizationRequest(Request $request) : bool;

}
