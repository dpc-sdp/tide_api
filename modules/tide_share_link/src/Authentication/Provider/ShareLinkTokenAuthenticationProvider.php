<?php

namespace Drupal\tide_share_link\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tide_share_link\Authentication\ShareLinkTokenAuthUser;
use Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Authentication Provider for Share Link Token.
 *
 * @package Drupal\tide_share_link\Authentication\Provider
 */
class ShareLinkTokenAuthenticationProvider implements AuthenticationProviderInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request policy.
   *
   * @var \Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface
   */
  protected $requestPolicy;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ShareLinkTokenAuthenticationProvider constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\tide_share_link\PageCache\ShareLinkTokenPageCachePolicyInterface $request_policy
   *   The request policy.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ShareLinkTokenPageCachePolicyInterface $request_policy, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestPolicy = $request_policy;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return $this->requestPolicy->isShareLinkTokenAuthorizationRequest($request);
  }

  /**
   * {@inheritdoc}
   *
   * At this point, it's still very early during the routing process. The route
   * params are not available yet hence we could not validate the requested node
   * against the provided token. Therefore we leave the validation process to
   * another request event subscriber.
   *
   * @see \Drupal\tide_share_link\EventSubscriber\ShareLinkTokenValidation::validateJsonapiRequestedNode()
   */
  public function authenticate(Request $request) {
    // Fetch the Share Link Token from headers.
    $token_header = trim($request->headers->get('X-Share-Link-Token', '', TRUE));
    if (!$token_header) {
      throw new AccessDeniedHttpException();
    }

    /** @var \Drupal\tide_share_link\ShareLinkTokenStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('share_link_token');
    $token = $storage->loadByToken($token_header);
    if (!$token || ($token && !$token->isActive())) {
      throw new AccessDeniedHttpException();
    }

    return new ShareLinkTokenAuthUser($token, $this->configFactory);
  }

}
