<?php

namespace Drupal\tide_share_link\Authentication;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\tide_share_link\Entity\ShareLinkTokenInterface;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Authenticated user of the Share Link Token.
 *
 * @package Drupal\tide_share_link\Authentication
 */
class ShareLinkTokenAuthUser implements ShareLinkTokenAuthUserInterface {

  /**
   * The share link token.
   *
   * @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface
   */
  protected $token;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The faux user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The role of authorized token.
   *
   * @var \Drupal\user\RoleInterface|null
   */
  protected $tokenRole;

  /**
   * Authenticated user role.
   *
   * @var \Drupal\user\RoleInterface|null
   */
  protected $authenticatedRole;

  /**
   * AccountProxy constructor.
   *
   * @param \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $token
   *   The Share link token.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ShareLinkTokenInterface $token, ConfigFactoryInterface $config_factory) {
    $this->token = $token;
    $this->configFactory = $config_factory;
    $this->account = User::getAnonymousUser();
    $this->authenticatedRole = $this->getAuthenticatedRole();
    $this->tokenRole = $this->getTokenRole();
  }

  /**
   * Get the user account.
   *
   * @return \Drupal\user\UserInterface
   *   The account.
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * {@inheritdoc}
   */
  public function getShareLinkToken() : ShareLinkTokenInterface {
    return $this->token;
  }

  /**
   * Gets the Entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected static function getEntityTypeManager() : EntityTypeManagerInterface {
    return \Drupal::entityTypeManager();
  }

  /**
   * Gets the route match service.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The route match.
   */
  protected static function getRouteMatch() : RouteMatchInterface {
    return \Drupal::routeMatch();
  }

  /**
   * Get the Authenticated user role.
   *
   * @return \Drupal\user\RoleInterface|null
   *   The role.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAuthenticatedRole() : ?RoleInterface {
    return static::getEntityTypeManager()->getStorage('user_role')->load(RoleInterface::AUTHENTICATED_ID);
  }

  /**
   * Get the role of the authorized token.
   *
   * @return \Drupal\user\RoleInterface|null
   *   The role.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getTokenRole() : ?RoleInterface {
    $token_role = $this->configFactory->get('share_link_token.settings')->get('token_role');
    if ($token_role) {
      return static::getEntityTypeManager()
        ->getStorage('user_role')
        ->load($token_role);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getAccount()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($exclude_locked_roles = FALSE) {
    return $this->getAccount()->getRoles($exclude_locked_roles);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission($permission) {
    if ($this->tokenRole && $this->tokenRole->hasPermission($permission)) {
      return TRUE;
    }
    if ($this->authenticatedRole && $this->authenticatedRole->hasPermission($permission)) {
      return TRUE;
    }
    return $this->getAccount()->hasPermission($permission);
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredLangcode($fallback_to_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreferredAdminLangcode($fallback_to_default = TRUE) {
    return $this->getAccount()->getPreferredAdminLangcode($fallback_to_default);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsername() {
    @trigger_error('\Drupal\Core\Session\AccountInterface::getUsername() is deprecated in drupal:8.0.0 and will be removed before drupal:9.0.0. Use \Drupal\Core\Session\AccountInterface::getAccountName() or \Drupal\user\UserInterface::getDisplayName() instead. See https://www.drupal.org/node/2572493', E_USER_DEPRECATED);
    return $this->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountName() {
    return $this->getAccount()->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    return $this->getAccount()->getDisplayName();
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail() {
    return $this->getAccount()->getEmail();
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeZone() {
    return $this->getAccount()->getTimeZone();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessedTime() {
    return $this->getAccount()->getLastAccessedTime();
  }

}
