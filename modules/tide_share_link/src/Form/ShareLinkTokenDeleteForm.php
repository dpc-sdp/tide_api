<?php

namespace Drupal\tide_share_link\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Share Link Token entities.
 *
 * @ingroup tide_share_link
 */
class ShareLinkTokenDeleteForm extends ContentEntityDeleteForm {

  /**
   * The shared node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $entity */
    $entity = $this->getEntity();
    $this->node = $entity->getSharedNode();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    if ($this->node) {
      return $this->node->toUrl('revision');
    }
    // Otherwise fall back to the front page.
    return Url::fromRoute('<front>');
  }

}
