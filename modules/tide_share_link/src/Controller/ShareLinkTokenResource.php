<?php

namespace Drupal\tide_share_link\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\node\NodeInterface;
use Drupal\tide_share_link\Entity\ShareLinkTokenInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resource controller for Share Link Token.
 *
 * @package Drupal\tide_share_link\Controller
 */
class ShareLinkTokenResource extends EntityResource {

  use ContainerAwareTrait;

  /**
   * Gets the individual Share Link Token.
   *
   * @param \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $entity
   *   The loaded Share Link Token entity.
   * @param \Drupal\node\NodeInterface $node
   *   The loaded Node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
   *   Thrown when access to the entity is not allowed.
   */
  public function getShareLinkToken(ShareLinkTokenInterface $entity, NodeInterface $node, Request $request) {
    // Only load active token.
    if (!$entity->isActive()) {
      throw new AccessDeniedHttpException();
    }

    // The requested node must match the shared node of the requested token.
    if (!$entity->isSharedNode($node, FALSE)) {
      throw new NotFoundHttpException();
    }

    return parent::getIndividual($entity, $request);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollection(ResourceType $resource_type, Request $request) {
    throw new NotFoundHttpException();
  }

  /**
   * {@inheritdoc}
   */
  public function getIndividual(EntityInterface $entity, Request $request) {
    throw new NotFoundHttpException();
  }

  /**
   * {@inheritdoc}
   */
  public function createIndividual(ResourceType $resource_type, Request $request) {
    throw new NotFoundHttpException();
  }

  /**
   * {@inheritdoc}
   */
  public function patchIndividual(ResourceType $resource_type, EntityInterface $entity, Request $request) {
    throw new NotFoundHttpException();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIndividual(EntityInterface $entity) {
    throw new NotFoundHttpException();
  }

}
