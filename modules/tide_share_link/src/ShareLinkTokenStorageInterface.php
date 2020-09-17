<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\tide_share_link\Entity\ShareLinkTokenInterface;

/**
 * Provides the interface for Share Link Token Storage.
 *
 * @package Drupal\tide_share_link
 */
interface ShareLinkTokenStorageInterface extends ContentEntityStorageInterface {

  /**
   * Delete all share link tokens of a Node.
   *
   * @param int $nid
   *   The node ID.
   */
  public function deleteBySharedNodeId($nid) : void;

  /**
   * Delete all share link tokens of a Node Revision.
   *
   * @param int $vid
   *   The node revision ID.
   */
  public function deleteBySharedNodeRevisionId($vid) : void;

  /**
   * Load a share link token by token value.
   *
   * @param string $token
   *   The token value.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface|null
   *   The share link token.
   */
  public function loadByToken($token) : ?ShareLinkTokenInterface;

}
