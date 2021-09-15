<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * View builder for Share Link Token entities.
 *
 * @package Drupal\tide_share_link\Entity
 */
class ShareLinkTokenViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface[] $entities */
    if (empty($entities)) {
      return;
    }

    parent::buildComponents($build, $entities, $displays, $view_mode);

    foreach ($entities as $id => $entity) {
      $bundle = $entity->bundle();
      $display = $displays[$bundle];

      if ($display->getComponent('api_info')) {
        $build[$id]['api_info'] = [
          '#lazy_builder' => [
            '\Drupal\tide_share_link\ShareLinkTokenListBuilderCallback::renderApiInformation',
            [$entity->id()],
          ],
        ];
      }
    }
  }

}
