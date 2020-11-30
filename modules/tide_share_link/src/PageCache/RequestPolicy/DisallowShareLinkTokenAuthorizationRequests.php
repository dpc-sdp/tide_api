<?php

namespace Drupal\tide_share_link\PageCache\RequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not serve a page from cache if Share Link Token authentication applies.
 *
 * @package Drupal\tide_share_link\PageCache
 */
class DisallowShareLinkTokenAuthorizationRequests extends ShareLinkTokenPageCachePolicyBase implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    return $this->isShareLinkTokenAuthorizationRequest($request) ? static::DENY : NULL;
  }

}
