<?php

namespace Drupal\tide_share_link\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Share Link Token entities.
 *
 * @ingroup tide_share_link
 */
interface ShareLinkTokenInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Gets the token value of the Share Link Token.
   *
   * @return string
   *   The token value.
   */
  public function getToken() : string;

  /**
   * Gets the Share Link Token name.
   *
   * @return string
   *   Name of the Share Link Token.
   */
  public function getName() : string;

  /**
   * Sets the Share Link Token name.
   *
   * @param string $name
   *   The Share Link Token name.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   *   The called Share Link Token entity.
   */
  public function setName($name) : ShareLinkTokenInterface;

  /**
   * Gets the Share Link Token creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Share Link Token.
   */
  public function getCreatedTime() : int;

  /**
   * Get the expiry timestamp of the Share Link Token.
   *
   * @return int
   *   The expiry timestamp.
   */
  public function getExpiry() : int;

  /**
   * Set the expiry timestamp of the Share Link Token.
   *
   * @param int $timestamp
   *   The expiry timestamp.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   *   The called Share Link Token entity.
   */
  public function setExpiry($timestamp) : ShareLinkTokenInterface;

  /**
   * Check if the Share Link Token expires.
   *
   * @return bool
   *   TRUE if expired.
   */
  public function isExpired() : bool;

  /**
   * Check if the Share Link Token is valid (published and not expired).
   *
   * @return bool
   *   TRUE if active.
   */
  public function isActive() : bool;

  /**
   * Sets the Share Link Token creation timestamp.
   *
   * @param int $timestamp
   *   The Share Link Token creation timestamp.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   *   The called Share Link Token entity.
   */
  public function setCreatedTime($timestamp) : ShareLinkTokenInterface;

  /**
   * Gets the shared Node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The shared node.
   */
  public function getSharedNode() : ?NodeInterface;

  /**
   * Set the shared node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The shared node.
   *
   * @return \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   *   The called Share Link Token entity.
   */
  public function setSharedNode(NodeInterface $node) : ShareLinkTokenInterface;

  /**
   * Get Node ID of the shared node of the Share Link Token.
   *
   * @return int|string|null
   *   The NID of the node.
   */
  public function getSharedNodeId();

  /**
   * Get Node Revision ID of the shared node of the Share Link Token.
   *
   * @return int|string|null
   *   The VID of the node.
   */
  public function getSharedNodeRevisionId();

  /**
   * Check if a node is the shared node by comparing Nid and Vid.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   The node to check.
   * @param bool $compare_revision_id
   *   Whether to also compare the node revisions ID.
   *
   * @return bool
   *   TRUE if matched.
   */
  public function isSharedNode(NodeInterface $node = NULL, $compare_revision_id = TRUE) : bool;

}
