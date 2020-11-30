<?php

namespace Drupal\tide_share_link\PageCache;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Share Link Token Page Cache Policy base class.
 *
 * @package Drupal\tide_share_link\PageCache
 */
abstract class ShareLinkTokenPageCachePolicyBase implements ShareLinkTokenPageCachePolicyInterface {

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DisallowShareLinkTokenAuthorizationRequests constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function isShareLinkTokenAuthorizationRequest(Request $request) : bool {
    $tokens = &drupal_static(__METHOD__);
    $token_header = trim($request->headers->get('X-Share-Link-Token', '', TRUE));
    if ($token_header) {
      if (isset($tokens[$token_header])) {
        return TRUE;
      }

      /** @var \Drupal\tide_share_link\ShareLinkTokenStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('share_link_token');
      $token = $storage->loadByToken($token_header);
      $tokens[$token_header] = $token && $token->isActive();
      return $tokens[$token_header];
    }
    return FALSE;
  }

}
