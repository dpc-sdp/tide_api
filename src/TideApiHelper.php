<?php

namespace Drupal\tide_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;

/**
 * Class TideApiHelper.
 *
 * @package Drupal\tide_api
 */
class TideApiHelper {

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSONAPI resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The default front page.
   *
   * @var string
   */
  protected $frontPage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   JSONAPI resource type repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config factory service.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepository $resource_type_repository, ConfigFactoryInterface $config_factory) {
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->configFactory = $config_factory;
  }

  /**
   * Return a URL object from the given path.
   *
   * @param string $path
   *   The path, eg. /node/1 or /about-us.
   *
   * @return \Drupal\Core\Url|null
   *   The URL. NULL if the path has no scheme.
   */
  public function findUrlFromPath($path) {
    $url = NULL;
    if ($path) {
      try {
        if ($path === '/') {
          $path = $this->getFrontPagePath();
        }
        $url = Url::fromUri('internal:' . $path);
      }
      catch (\Exception $exception) {
        return NULL;
      }
    }

    return $url;
  }

  /**
   * Return an entity from a URL object.
   *
   * @param \Drupal\Core\Url $url
   *   The Url object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity. NULL if not found.
   */
  public function findEntityFromUrl(Url $url) {
    try {
      // Try to resolve URL to entity-based path.
      $params = $url->getRouteParameters();
      $entity_type = key($params);
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($params[$entity_type]);

      return $entity;
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

  /**
   * Return absolute endpoint for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The endpoint. NULL if not found.
   */
  public function findEndpointFromEntity(EntityInterface $entity) {
    $endpoint = NULL;
    try {
      // Get JSONAPI configured path for this entity.
      $jsonapi_entity_path = $this->getEntityJsonapiPath($entity);
      if ($jsonapi_entity_path) {
        // Build an endpoint as an absolute URL.
        $endpoint = Url::fromRoute('jsonapi.' . $jsonapi_entity_path . '.individual', [$entity->getEntityTypeId() => $entity->uuid()])
          ->setAbsolute()
          ->toString();
      }
    }
    catch (\Exception $exception) {
      return NULL;
    }

    return $endpoint;
  }

  /**
   * Lookup JSONAPI path for a provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity to lookup the JSONAPI path for.
   *
   * @return string|null
   *   JSONAPI path for provided entity or NULL if no path was found.
   */
  public function getEntityJsonapiPath(EntityInterface $entity) {
    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
    $config_path = $resource_type->getTypeName();

    return $config_path;
  }

  /**
   * Gets the current front page path.
   *
   * @return string
   *   The front page path.
   */
  public function getFrontPagePath() {
    // Lazy-load front page config.
    if (!isset($this->frontPage)) {
      $this->frontPage = $this->configFactory->get('system.site')
        ->get('page.front');
    }

    return $this->frontPage;
  }

}
