<?php

namespace Drupal\tide_share_link\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Share Link Token entity.
 *
 * @ingroup tide_share_link
 *
 * @ContentEntityType(
 *   id = "share_link_token",
 *   label = @Translation("Share Link Token"),
 *   internal = TRUE,
 *   handlers = {
 *     "view_builder" = "Drupal\tide_share_link\ShareLinkTokenViewBuilder",
 *     "list_builder" = "Drupal\tide_share_link\ShareLinkTokenListBuilder",
 *     "views_data" = "Drupal\tide_share_link\Entity\ShareLinkTokenViewsData",
 *     "storage" = "Drupal\tide_share_link\ShareLinkTokenStorage",
 *     "form" = {
 *       "default" = "Drupal\tide_share_link\Form\ShareLinkTokenForm",
 *       "edit" = "Drupal\tide_share_link\Form\ShareLinkTokenForm",
 *       "delete" = "Drupal\tide_share_link\Form\ShareLinkTokenDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\tide_share_link\ShareLinkTokenHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\tide_share_link\ShareLinkTokenAccessControlHandler",
 *   },
 *   base_table = "share_link_token",
 *   translatable = FALSE,
 *   admin_permission = "administer share link token entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "token" = "uuid",
 *     "uuid" = "token",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/node/{node}/share/{share_link_token}",
 *     "edit-form" = "/node/{node}/share/{share_link_token}/edit",
 *     "delete-form" = "/node/{node}/share/{share_link_token}/delete",
 *     "collection" = "/admin/content/share_link_token",
 *     "node-collection" = "/node/{node}/share",
 *     "revision-collection" = "/node/{node}/revisions/{node_revision}/share",
 *   },
 *   field_ui_base_route = "share_link_token.settings"
 * )
 */
class ShareLinkToken extends ContentEntityBase implements ShareLinkTokenInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use EntityOwnerTrait;

  /**
   * The shared node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) : void {
    parent::preCreate($storage_controller, $values);
    $config = \Drupal::config('tide_share_link.settings');
    $default_expiry = $config->get('default_expiry') ?: 2592000;
    $values += [
      'uid' => \Drupal::currentUser()->id(),
      'expiry' => \Drupal::time()->getCurrentTime() + $default_expiry,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getToken() : string {
    return $this->uuid();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() : string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) : ShareLinkTokenInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() : int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) : ShareLinkTokenInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiry() : int {
    return $this->get('expiry')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExpiry($timestamp) : ShareLinkTokenInterface {
    $this->set('expiry', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isExpired() : bool {
    return $this->getExpiry() < \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() : bool {
    return $this->isPublished() && !$this->isExpired();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) : array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['token']->setLabel(t('Token'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -30,
      ]);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The user ID of author of the Share Link Token entity.'))
      ->setRevisionable(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -25,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('This field shows the CMS-given name for the preview link. It includes the page title, the page’s node number and the email address of the person sharing.<br/> You can edit it to be more meaningful to preview link recipients. If you’ll be sharing a draft page multiple times, it could be helpful to put a version number or the time of the edits or latest draft (eg. Minister’s report, 2pm).'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -35,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -35,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status']
      ->setLabel(t('Status'))
      ->setDescription(t('Whether the share preview link is active.'))
      ->setSetting('on_label', t('Active'))
      ->setSetting('off_label', t('Revoked'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => FALSE],
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'boolean',
        'settings' => [
          'format' => 'custom',
          'format_custom_true' => t('Active'),
          'format_custom_false' => t('Revoked'),
        ],
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['nid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Node ID'))
      ->setDescription(t('The ID of the shared node.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);

    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Node Revision ID'))
      ->setDescription(t('The Revision ID of the shared node.'))
      ->setRequired(FALSE)
      ->setReadOnly(TRUE);

    $fields['expiry'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Expiry'))
      ->setDescription(t('When you share a preview link it automatically expires after a set amount of time. You can extend the time limit by updating these date and time fields.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => -20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => -15,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedNode() : ?NodeInterface {
    if (!$this->node) {
      $node_storage = $this->entityTypeManager()->getStorage('node');
      if ($this->getSharedNodeRevisionId() !== NULL) {
        $this->node = $node_storage->loadRevision($this->getSharedNodeRevisionId());
      }
      elseif ($this->getSharedNodeId() !== NULL) {
        $this->node = $node_storage->load($this->getSharedNodeId());
      }
    }
    return $this->node;
  }

  /**
   * {@inheritdoc}
   */
  public function setSharedNode(NodeInterface $node) : ShareLinkTokenInterface {
    $this->set('nid', $node->id());
    $this->set('vid', $node->isDefaultRevision() ? NULL : $node->getLoadedRevisionId());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedNodeId() {
    return $this->get('nid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedNodeRevisionId() {
    return $this->get('vid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isSharedNode(NodeInterface $node = NULL, $compare_revision_id = TRUE) : bool {
    $shared_node = $this->getSharedNode();
    if (!$shared_node) {
      return FALSE;
    }
    return $node
      && ($shared_node->id() === $node->id())
      && ($compare_revision_id && $this->getSharedNodeRevisionId() ? $shared_node->getLoadedRevisionId() === $node->getLoadedRevisionId() : TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) : array {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    switch ($rel) {
      case 'canonical':
      case 'edit-form':
      case 'delete-form':
      case 'node-collection':
        $uri_route_parameters['node'] = $this->getSharedNodeId();
        break;

      case 'revision-collection':
        $uri_route_parameters['node'] = $this->getSharedNodeId();
        $uri_route_parameters['node_revision'] = $this->getSharedNodeRevisionId() ?? 0;
        break;
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $cache_tags = parent::getCacheTagsToInvalidate();
    if ($this->getSharedNode()) {
      $cache_tags = Cache::mergeTags($cache_tags, $this->getSharedNode()->getCacheTags());
    }
    return $cache_tags;
  }

}
