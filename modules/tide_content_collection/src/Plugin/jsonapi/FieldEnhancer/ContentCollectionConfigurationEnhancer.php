<?php

namespace Drupal\tide_content_collection\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager;
use Drupal\tide_api\Plugin\jsonapi\FieldEnhancer\LinkEnhancer;
use Drupal\tide_content_collection\SearchApiIndexHelperInterface;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Decode Automated Listing Configuration.
 *
 * @ResourceFieldEnhancer(
 *   id = "content_collection_configuration",
 *   label = @Translation("Content Collection Configuration"),
 *   description = @Translation("Decode Content Collection Configuration.")
 * )
 */
class ContentCollectionConfigurationEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The Search API Index helper.
   *
   * @var \Drupal\tide_content_collection\SearchApiIndexHelperInterface
   */
  protected $indexHelper;

  /**
   * The Resource Field Enhancer Manager.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
   */
  protected $fieldEnhancerManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SearchApiIndexHelperInterface $sapi_index_helper, ResourceFieldEnhancerManager $field_enhancer_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->indexHelper = $sapi_index_helper;
    $this->fieldEnhancerManager = $field_enhancer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tide_content_collection.search_api.index_helper'),
      $container->get('plugin.manager.resource_field_enhancer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'apply_link_enhancer' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    $settings = empty($resource_field_info['enhancer']['settings'])
      ? $this->getConfiguration()
      : $resource_field_info['enhancer']['settings'];

    return [
      'apply_link_enhancer' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use link enhancer.'),
        '#description' => $this->t('Apply the link enhancer (link_enhancer) to the Call-to-Action link'),
        '#default_value' => $settings['apply_link_enhancer'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $configuration = json_decode($data);
    if (!empty($configuration->connection->index)) {
      $configuration->connection->serverIndex = $this->indexHelper->getServerIndexId($configuration->connection->index);
    }

    if (!empty($this->getConfiguration()['apply_link_enhancer'])) {
      if (!empty($configuration->callToAction->url)) {
        $cta = json_decode(json_encode($configuration->callToAction), TRUE);
        $cta['uri'] = $cta['url'];

        $link_enhancer = $this->fieldEnhancerManager->createInstance('link_enhancer');
        if ($link_enhancer instanceof LinkEnhancer) {
          $cta = $link_enhancer->undoTransform($cta, $context);
          $configuration->callToAction = (object) $cta;
        }
      }
    }

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    unset($data->connection->serverIndex);

    if (!empty($this->getConfiguration()['apply_link_enhancer'])) {
      if (isset($data->callToAction->uri)) {
        $cta = json_decode(json_encode($data->callToAction), TRUE);
        $link_enhancer = $this->fieldEnhancerManager->createInstance('link_enhancer');
        if ($link_enhancer instanceof LinkEnhancer) {
          $cta = $link_enhancer->transform($cta, $context);
          $cta['url'] = $cta['uri'];
          unset($cta['uri']);
          $data->callToAction = (object) $cta;
        }
      }
    }

    return json_encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'anyOf' => [
        ['type' => 'array'],
        ['type' => 'boolean'],
        ['type' => 'null'],
        ['type' => 'number'],
        ['type' => 'object'],
        ['type' => 'string'],
      ],
    ];
  }

}
