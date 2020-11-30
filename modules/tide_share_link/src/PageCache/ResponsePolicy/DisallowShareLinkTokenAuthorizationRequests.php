<?php

namespace Drupal\tide_share_link\PageCache\ResponsePolicy;

use Drupal\Core\PageCache\ResponsePolicyInterface;
use Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Do not serve a page from cache if Share Link Token authentication applies.
 *
 * @package Drupal\tide_share_link\PageCache
 */
class DisallowShareLinkTokenAuthorizationRequests extends ShareLinkTokenPageCachePolicyBase implements ResponsePolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Response $response, Request $request) {
    return $this->isShareLinkTokenAuthorizationRequest($request) ? static::DENY : NULL;
  }

}
