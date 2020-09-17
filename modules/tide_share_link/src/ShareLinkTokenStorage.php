<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\tide_share_link\Entity\ShareLinkTokenInterface;

/**
 * Provides storage for Share Link Token.
 *
 * @package Drupal\tide_share_link
 */
class ShareLinkTokenStorage extends SqlContentEntityStorage implements ShareLinkTokenStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function deleteBySharedNodeId($nid) : void {
    $tokens = $this->loadByProperties(['nid' => $nid]);
    if ($tokens) {
      $this->delete($tokens);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteBySharedNodeRevisionId($vid) : void {
    $tokens = $this->loadByProperties(['vid' => $vid]);
    if ($tokens) {
      $this->delete($tokens);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadByToken($token) : ?ShareLinkTokenInterface {
    $tokens = $this->loadByProperties([$this->getEntityType()->getKey('uuid') => $token]);
    return $tokens ? reset($tokens) : NULL;
  }

}
