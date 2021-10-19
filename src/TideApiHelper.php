<?php

namespace Drupal\tide_api;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TideApi Helper.
 *
 * @package Drupal\tide_api
 */
class TideApiHelper {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
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
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
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
      /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
      $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
      $route_name = Routes::getRouteName($resource_type, 'individual');
      $endpoint = Url::fromRoute($route_name, ['entity' => $entity->uuid()])->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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

  /**
   * Get the path query parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony request object.
   *
   * @return string|null
   *   The path query param.
   */
  public function getRequestedPath(Request $request) {
    if ($request->attributes->has('_tide_api_path')) {
      return $request->attributes->get('_tide_api_path');
    }

    return $request->query->get('path');
  }

  /**
   * Override the path query parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony request object.
   * @param string $path
   *   The overridden value.
   */
  public function overrideRequestedPath(Request $request, $path) {
    $request->attributes->set('_tide_api_path', $path);
  }

  /**
   * Get the full URL for a given request object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array|null $query
   *   The query parameters to use. Leave it empty to get the query from the
   *   request object.
   *
   * @return \Drupal\Core\Url
   *   The full URL.
   */
  public function getRequestLink(Request $request, $query = NULL) {
    if ($query === NULL) {
      return Url::fromUri($request->getUri());
    }

    $uri_without_query_string = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
    return Url::fromUri($uri_without_query_string)->setOption('query', $query);
  }

  /**
   * Gets the default JSON:API response for routing API.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata.
   *
   * @return array
   *   The default response.
   */
  public function getDefaultJsonResponse(CacheableMetadata $cache_metadata = NULL) {
    $self_url = static::getRequestLink(\Drupal::request(), \Drupal::request()->query)->setAbsolute()->toString(TRUE);
    if ($cache_metadata) {
      $cache_metadata->addCacheableDependency($self_url);
    }
    $json_response = [
      'data' => [
        'type' => 'route',
        'links' => [
          'self' => [
            'href' => $self_url->getGeneratedUrl(),
          ],
        ],
      ],
      'errors' => [
        [
          'status' => Response::HTTP_NOT_FOUND,
          'title' => static::getApiResponseStatusText(Response::HTTP_NOT_FOUND),
        ],
      ],
      'links' => [
        'self' => [
          'href' => $self_url->getGeneratedUrl(),
        ],
      ],
    ];

    return $json_response;
  }

  /**
   * Set Json response to return errors.
   *
   * @param array $json_response
   *   The Json Response array.
   * @param int $error_code
   *   The error code.
   * @param string|null $message
   *   The translated error message.
   * @param bool $append
   *   Whether to append the error to the response.
   *
   * @return int
   *   The error code.
   */
  public function setJsonResponseError(array &$json_response, $error_code, $message = NULL, $append = FALSE) {
    unset($json_response['data']);

    $error = [
      'status' => $error_code,
      'title' => $message ?? static::getApiResponseStatusText($error_code),
    ];

    if ($append) {
      $json_response['errors'][] = $error;
    }
    else {
      $json_response['errors'] = [$error];
    }

    return $error_code;
  }

  /**
   * Set the JSON response with Entity data.
   *
   * @param array $json_response
   *   The Json Response array.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The cache metadata.
   */
  public function setJsonResponseDataAttributesFromEntity(array &$json_response, EntityInterface $entity, CacheableMetadata $cache_metadata = NULL) {
    $endpoint = $this->findEndpointFromEntity($entity);
    $entity_type = $entity->getEntityTypeId();
    $json_response['data']['attributes'] = [
      'entity_type' => $entity_type,
      'entity_id' => $entity->id(),
      'bundle' => $entity->bundle(),
      'uuid' => $entity->uuid(),
      'endpoint' => $endpoint,
    ];
    if ($cache_metadata) {
      $cache_metadata->addCacheableDependency($entity);
    }
  }

  /**
   * Return the default status text for API response.
   *
   * @param int $code
   *   The status code.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The status text.
   */
  public static function getApiResponseStatusText($code) {
    switch ($code) {
      case Response::HTTP_NOT_FOUND:
        return t('Path not found.');

      case Response::HTTP_FORBIDDEN:
        return t('Permission denied.');

      default:
        return Response::$statusTexts[$code];
    }
  }

}
