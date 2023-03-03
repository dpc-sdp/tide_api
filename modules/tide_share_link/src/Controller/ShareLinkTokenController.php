<?php

namespace Drupal\tide_share_link\Controller;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\tide_share_link\Entity\ShareLinkToken;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for Share Link Token.
 *
 * @package Drupal\tide_share_link\Controller
 */
class ShareLinkTokenController extends ControllerBase {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * ShareLinkTokenController constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) : ShareLinkTokenController {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   * Create a share link token for a Node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to share.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to share link token edit form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function shareNode(NodeInterface $node) : RedirectResponse {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $token */
    $token = ShareLinkToken::create([
      'name' => $this->t('Share @type "@title" (@nid) by @username', [
        '@type' => node_get_type_label($node),
        '@title' => Unicode::truncate($node->getTitle(), 100, TRUE, TRUE, 10),
        '@nid' => $node->id(),
        '@username' => $this->currentUser()->getAccountName(),
      ]),
    ]);
    $token->setSharedNode($node);
    $token->save();

    return $this->redirect('entity.share_link_token.canonical', [
      'node' => $node->id(),
      'share_link_token' => $token->id(),
    ]);
  }

  /**
   * Create a share link token for a Node Revision.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to share.
   * @param int $node_revision
   *   The revision ID of the node.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the share link token edit form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function shareNodeRevision(NodeInterface $node, $node_revision) : RedirectResponse {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $token */
    $token = ShareLinkToken::create([
      'name' => $this->t('Share @type revision "@title" (@nid, @vid) by @username', [
        '@type' => node_get_type_label($node),
        '@title' => Unicode::truncate($node->getTitle(), 100, TRUE, TRUE, 10),
        '@nid' => $node->id(),
        '@vid' => $node_revision,
        '@username' => $this->currentUser()->getAccountName(),
      ]),
    ]);
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $this->entityTypeManager()->getStorage('node')->loadRevision($node_revision);
    $token->setSharedNode($revision);
    $token->save();

    return $this->redirect('entity.share_link_token.canonical', [
      'node' => $node->id(),
      'share_link_token' => $token->id(),
    ]);
  }

  /**
   * Check if an account has view access to a Node.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkNodeAccess(AccountInterface $account) : AccessResultInterface {
    // Retrieve the current node.
    $node = $this->routeMatch->getParameter('node');
    if ($node && !($node instanceof NodeInterface)) {
      $node = $this->entityTypeManager()->getStorage('node')->load((int) $node);
    }

    if ($node && $node instanceof NodeInterface) {
      // Attempt to retrieve the node revision.
      $revision = $this->routeMatch->getParameter('node_revision');
      if ($revision && $revision->id() === $node->id()) {
        return AccessResult::allowedIf($revision->access('view', $account))
          ->addCacheableDependency($revision)
          ->addCacheableDependency($account);
      }
      else {
        return AccessResult::allowedIf($node->access('view', $account))
          ->addCacheableDependency($node)
          ->addCacheableDependency($account);
      }
    }

    return AccessResult::forbidden($this->t('Invalid node or node revision provided.'))
      ->addCacheableDependency($account);
  }

}
