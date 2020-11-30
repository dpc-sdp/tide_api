<?php

namespace Drupal\tide_share_link\Authentication;

use Drupal\Core\Session\AccountInterface;
use Drupal\tide_share_link\Entity\ShareLinkTokenInterface;

/**
 * Provides the interface for Share Link Token Authenticated user.
 */
interface ShareLinkTokenAuthUserInterface extends AccountInterface {

  /**
   * Get the token.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   *   The provided Share Link Token.
   */
  public function getShareLinkToken() : ShareLinkTokenInterface;

}
