<?php

namespace Drupal\tide_share_link\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Share Link Token entities.
 *
 * @ingroup tide_share_link
 */
class ShareLinkTokenSettingsForm extends ConfigFormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'share_link_token_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tide_share_link.settings'];
  }

  /**
   * Defines the settings form for Share Link Token entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form definition array.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('tide_share_link.settings');

    $role_options = [];
    $admin_permissions = [
      'administer site configuration',
      'administer software updates',
      'administer modules',
      'administer nodes',
      'bypass node access',
      'administer users',
      'administer permissions',
    ];
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role_id => $role) {
      if ($role_id === RoleInterface::ANONYMOUS_ID || $role_id === RoleInterface::AUTHENTICATED_ID || $role->isAdmin()) {
        continue;
      }

      foreach ($admin_permissions as $permission) {
        if ($role->hasPermission($permission)) {
          continue 2;
        }
      }

      $role_options[$role->id()] = $role->label();
    }

    $form['token_role'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select a role to grant to JSON:API requests with Share Link Token authorization.'),
      '#description' => $this->t('Authorized requests with a valid Share Link Token will be treated an authenticated user with the selected role.<br/> For security reasons, roles with certain administrative permissions cannot be selected.'),
      '#options' => $role_options,
      '#default_value' => $form_state->getValue('token_role') ?? $config->get('token_role'),
      '#required' => TRUE,
    ];

    $form['default_expiry'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Default expiry'),
      '#description' => $this->t('The default expiry in second for new share link tokens, eg. 3600 (1 hour), 86400 (1 day), 604800 (1 week), 2592000 (1 month).'),
      '#default_value' => $form_state->getValue('default_expiry') ?? ($config->get('default_expiry') ?: 2592000),
      '#required' => TRUE,
    ];

    $form['cleanup_in_cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cleanup expired tokens during cron'),
      '#default_value' => $form_state->getValue('cleanup_in_cron') ?? $config->get('cleanup_in_cron'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('tide_share_link.settings');
    $config->set('token_role', $form_state->getValue('token_role'));
    $config->set('default_expiry', $form_state->getValue('default_expiry'));
    $config->set('cleanup_in_cron', $form_state->getValue('cleanup_in_cron'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
