<?php

namespace Drupal\tide_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\tide_api\TideApiHelper;
use Shaper\Util\Context;
use Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\UuidLinkEnhancer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Enhancer with alteration for internal link field.
 *
 * @ResourceFieldEnhancer(
 *   id = "link_enhancer",
 *   label = @Translation("Enhanced UUID for link (link field only)"),
 *   description = @Translation("Enhancer with alteration for internal link field.")
 * )
 */
class LinkEnhancer extends UuidLinkEnhancer {

  /**
   * The Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The API Helper.
   *
   * @var \Drupal\tide_api\TideApiHelper
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, TideApiHelper $helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->moduleHandler = $module_handler;
    $this->helper = $helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('tide_api.helper')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\UuidLinkEnhancer::doUndoTransform()
   */
  protected function doUndoTransform($data, Context $context) {
    if (isset($data['uri'])) {
      // Resolve homepage.
      if ($data['uri'] === 'internal:/') {
        $data['frontpage'] = TRUE;
        $frontpage = $this->helper->getFrontPagePath();
        $frontpage_url = $this->helper->findUrlFromPath($frontpage);
        if ($frontpage_url) {
          $homepage = $this->helper->findEntityFromUrl($frontpage_url);
          if ($homepage) {
            $data['uri'] = 'entity:' . $homepage->getEntityTypeId() . '/' . $homepage->id();
          }
        }
      }

      // Check if it is an internal link to an entity.
      preg_match('/entity:(.*)\/(.*)/', $data['uri'], $parsed_uri);
      if (!empty($parsed_uri)) {
        $entity_type = $parsed_uri[1];
        $entity_id = $parsed_uri[2];
        // Add entity info to the link field.
        $data['entity'] = [
          'uri' => $data['uri'],
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
        ];
        $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        if (!is_null($entity)) {
          $data['entity']['bundle'] = $entity->bundle();
          $data['entity']['uuid'] = $entity->uuid();
          // And URL to the entity.
          try {
            $data['url'] = $entity->toUrl('canonical')->toString();
          }
          catch (\Exception $exception) {
            watchdog_exception('tide_api', $exception);
            $data['url'] = '/' . $entity_type . '/' . $entity_id;
          }
        }
      }
    }

    $data = parent::doUndoTransform($data, $context);

    $this->moduleHandler->alter('tide_link_enhancer_undo_transform', $data, $context);

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\UuidLinkEnhancer::doTransform()
   */
  protected function doTransform($value, Context $context) {
    if (isset($value['uri'])) {
      // Check if it is a  UUID link to an entity.
      preg_match('/entity:(.*)\/(.*)\/(.*)/', $value['uri'], $parsed_uri);
      if (!empty($parsed_uri)) {
        unset($value['entity'], $value['url'], $value['frontpage']);
      }
    }

    $value = parent::doTransform($value, $context);

    $this->moduleHandler->alter('tide_link_enhancer_transform', $value, $context);

    return $value;
  }

}
