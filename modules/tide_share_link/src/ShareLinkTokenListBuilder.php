<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Share Link Token entities.
 *
 * @ingroup tide_share_link
 */
class ShareLinkTokenListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Route Match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new NodeListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $storage);

    $this->dateFormatter = $date_formatter;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['token'] = [
      'data' => $this->t('Token'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['name'] = $this->t('Name');
    $header['node'] = [
      'data' => $this->t('Node'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['author'] = [
      'data' => $this->t('Created by'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['changed'] = [
      'data' => $this->t('Updated on'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['expiry'] = [
      'data' => $this->t('Expiry'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $entity */
    $row = [];
    $row['token'] = $entity->getToken();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.share_link_token.canonical',
      [
        'share_link_token' => $entity->id(),
        'node' => $entity->getSharedNode() ? $entity->getSharedNode()->id() : 0,
      ]
    );
    $row['node'] = NULL;
    $node = $entity->getSharedNode();
    if ($node) {
      $row['node'] = $node->isDefaultRevision()
        ? Link::fromTextAndUrl($node->getTitle(), $node->toUrl('canonical'))
        : Link::fromTextAndUrl($this->t('@title (rev. @vid)', [
          '@title' => $node->getTitle(),
          '@vid' => $node->getLoadedRevisionId(),
        ]), $node->toUrl('revision'))->toString();
    }
    $row['author']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
    $row['expiry'] = $this->dateFormatter->format($entity->getExpiry(), 'short');
    if ($entity->isExpired()) {
      $row['status'] = $entity->isPublished() ? $this->t('Expired') : $this->t('Revoked and Expired');
    }
    else {
      $row['status'] = $entity->isPublished() ? $this->t('Active') : $this->t('Revoked');
    }
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'), 'desc');

    $node = $this->getNodeFromCurrentRoute();
    if ($node) {
      $query->condition('nid', $node instanceof NodeInterface ? $node->id() : (int) $node)->accessCheck(TRUE);
    }

    $node_revision = $this->getNodeRevisionFromRoute();
    if ($node_revision) {
      $query->condition('vid', (int) $node_revision)->accessCheck(TRUE);
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit)->accessCheck(TRUE);
    }
    return $query->execute();
  }

  /**
   * Get the Node from current route.
   *
   * @return \Drupal\node\NodeInterface|mixed|null
   *   The node.
   */
  protected function getNodeFromCurrentRoute() {
    return $this->routeMatch->getParameter('node');
  }

  /**
   * Get the Node Revision ID from current route.
   *
   * @return mixed|null
   *   The node revision ID.
   */
  protected function getNodeRevisionFromRoute() {
    return $this->routeMatch->getParameter('node_revision');
  }

}
