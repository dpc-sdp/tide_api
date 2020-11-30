<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Share Link Token entity.
 *
 * @see \Drupal\tide_share_link\Entity\ShareLinkToken.
 */
class ShareLinkTokenAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $entity */
    $node = $entity->getSharedNode();
    $can_view_node = $node && $node->access('view', $account);

    switch ($operation) {
      case 'view':
        // Expired links are treated as unpublished.
        if (!$entity->isPublished() || $entity->isExpired()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished share link token entities');
        }

        return AccessResult::allowedIfHasPermission($account, 'view published share link token entities');

      // Deny access if the account cannot view the Node/Node Revision.
      case 'update':
        return $can_view_node ? AccessResult::allowedIfHasPermission($account, 'add share link token entities') : AccessResult::forbidden();

      case 'delete':
        return $can_view_node ? AccessResult::allowedIfHasPermission($account, 'delete share link token entities') : AccessResult::forbidden();
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add share link token entities');
  }

}
