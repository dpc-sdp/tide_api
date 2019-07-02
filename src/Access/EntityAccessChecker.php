<?php

namespace Drupal\tide_api\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Access\EntityAccessChecker as JsonapiEntityAccessChecker;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Class EntityAccessChecker.
 *
 * @package Drupal\tide_api\Access
 */
class EntityAccessChecker extends JsonapiEntityAccessChecker {

  /**
   * {@inheritdoc}
   *
   * @see https://www.drupal.org/project/jsonapi/issues/2992833#comment-12818258
   * @see https://www.drupal.org/project/drupal/issues/3043321
   */
  protected function checkRevisionViewAccess(EntityInterface $entity, AccountInterface $account) {
    // The ParagraphAccessControlHandler already checks for access.
    if ($entity instanceof Paragraph) {
      return AccessResult::allowed();
    }
    return parent::checkRevisionViewAccess($entity, $account);
  }

}
