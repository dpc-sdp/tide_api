<?php

namespace Drupal\tide_share_link\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Routing\Routes;
use Drupal\node\NodeInterface;
use Drupal\tide_share_link\Authentication\ShareLinkTokenAuthUserInterface;
use Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to validate JSON:API node against the Share Link Token.
 *
 * @package Drupal\tide_share_link\EventSubscriber
 */
class ShareLinkTokenValidation implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The share link token request policy.
   *
   * @var \Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface
   */
  protected $requestPolicy;

  /**
   * ShareLinkTokenValidation constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current account.
   * @param \Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface $request_policy
   *   The token request policy.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, ShareLinkTokenPageCachePolicyInterface $request_policy) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->requestPolicy = $request_policy;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Our subscriber must have very low priority,
    // as it relies on route resolver to parse all params.
    $events = [];
    $events[KernelEvents::REQUEST][] = ['validateJsonapiRequestedNode', -1000];

    return $events;
  }

  /**
   * Validate the requested node from JSON:API against the requested token.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event.
   */
  public function validateJsonapiRequestedNode(RequestEvent $event) {
    // Only works Share Link Token authorization requests.
    if (!$this->requestPolicy->isShareLinkTokenAuthorizationRequest($event->getRequest())) {
      return;
    }

    // Only works with JSON:API requests.
    if (!$this->isJsonapiRequest($event->getRequest())) {
      return;
    }

    // Only works when the current user is Share Link Token Auth.
    $account = $this->currentUser->getAccount();
    if (!($account instanceof ShareLinkTokenAuthUserInterface)) {
      throw new AccessDeniedHttpException();
    }

    // Only works with node.
    $entity = $event->getRequest()->attributes->get('entity');
    if (!($entity instanceof NodeInterface)) {
      throw new AccessDeniedHttpException();
    }

    // Ensure the requested node is also shared in the Token.
    $token = $account->getShareLinkToken();
    if ($token && !$token->isSharedNode($entity)) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Determine whether the current request is JSON:API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return bool
   *   TRUE if it is JSON:API.
   */
  public function isJsonapiRequest(Request $request) : bool {
    return Routes::isJsonApiRequest($request->attributes->all());
  }

}
