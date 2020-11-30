<?php

namespace Drupal\tide_share_link;

use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;

/**
 * Class ShareLinkTokenSharedNodeFieldItemList for computed field.
 *
 * @package Drupal\tide_share_link
 */
class ShareLinkTokenSharedNodeFieldItemList extends EntityReferenceRevisionsFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $token */
    $token = $this->getEntity();
    $node = $token->getSharedNode();
    if ($node) {
      $this->list = [
        $this->createItem(0, [
          'target_id' => $node->id(),
          'target_revision_id' => $node->getLoadedRevisionId(),
        ]),
      ];
    }
  }

}
