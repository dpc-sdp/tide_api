<?php

namespace Drupal\tide_api\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\tide_api\Event\GetCacheIdEvent;
use Drupal\tide_api\TideApiRedirectRepository;
use Drupal\tide_api\Event\GetRouteEvent;
use Drupal\tide_api\TideApiEvents;
use Drupal\tide_api\TideApiHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Tide Api Controller.
 *
 * @package Drupal\tide_api\Controller
 */
class TideApiController extends ControllerBase {

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
   * The system event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The API Helper.
   *
   * @var \Drupal\tide_api\TideApiHelper
   */
  protected $apiHelper;

  /**
   * The redirect repository.
   *
   * @var \Drupal\tide_api\TideApiRedirectRepository
   */
  protected $redirectRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The data cache ID.
   *
   * @var string
   */
  protected $cacheId;

  /**
   * The collected cache metadata.
   *
   * @var \Drupal\Core\Cache\CacheableMetadata
   */
  protected $cacheMetadata;

  /**
   * The entity from the route.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $routeEntity;

  /**
   * Constructs a new PathController.
   *
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   JSON:API resource type repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\tide_api\TideApiHelper $api_helper
   *   The Tide API Helper.
   * @param \Drupal\tide_api\TideApiRedirectRepository $redirect_repository
   *   The redirect entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepository $resource_type_repository, EventDispatcherInterface $event_dispatcher, TideApiHelper $api_helper, TideApiRedirectRepository $redirect_repository, LanguageManagerInterface $language_manager) {
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->apiHelper = $api_helper;
    $this->redirectRepository = $redirect_repository;
    $this->languageManager = $language_manager;
    $this->cacheMetadata = new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.alias_manager'),
      $container->get('entity_type.manager'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('event_dispatcher'),
      $container->get('tide_api.helper'),
      $container->get('tide_api.redirect_repository'),
      $container->get('language_manager')
    );
  }

  /**
   * Get route details from provided source or alias.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function getRoute(Request $request) {
    $code = Response::HTTP_NOT_FOUND;
    $entity = NULL;
    $path = $this->apiHelper->getRequestedPath($request);
    $json_response = $this->apiHelper->getDefaultJsonResponse($this->cacheMetadata);

    try {
      if ($path) {
        $this->initializeCacheId($path, $request);
        $this->cacheMetadata->addCacheContexts(['url.query_args:path', 'user']);

        // First load from cache_data.
        $cached_route_data = $this->cache('data')->get($this->cacheId);
        if ($cached_route_data) {
          // Check if the current has permission to access the path.
          $url = $cached_route_data->data['uri'] ? Url::fromUri($cached_route_data->data['uri']) : NULL;
          if ($url && !$url->access()) {
            $code = $this->apiHelper->setJsonResponseError($json_response, Response::HTTP_FORBIDDEN);
          }
          else {
            $code = Response::HTTP_OK;
            $json_response['data']['id'] = $cached_route_data->data['id'];
            $json_response['data']['attributes'] = $cached_route_data->data['json_response']['attributes'];
            unset($json_response['errors']);
            if (!empty($cached_route_data->tags) && is_array($cached_route_data->tags)) {
              $this->cacheMetadata->addCacheTags($cached_route_data->tags);
            }
          }
        }
        // Cache miss.
        else {
          $this->resolvePath($request, $path, $json_response, $code);
        }
      }
      // Path param is required.
      else {
        $code = $this->apiHelper->setJsonResponseError($json_response, Response::HTTP_BAD_REQUEST, $this->t("URL query parameter 'path' is required."));
      }
    }
    catch (\Exception $exception) {
      $code = $this->apiHelper->setJsonResponseError($json_response, Response::HTTP_BAD_REQUEST, $exception->getMessage());
    }

    // Return an uncached response upon failure.
    if ($code != Response::HTTP_OK) {
      return new JsonResponse($json_response, $code);
    }

    $response = new CacheableJsonResponse($json_response);
    return $response->addCacheableDependency($this->cacheMetadata);
  }

  /**
   * Find the current entity based on the path if no entity is defined in cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Request object.
   * @param string $path
   *   The requested path.
   * @param array $json_response
   *   The current json response array.
   * @param int $code
   *   The current HTTP Status code.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function resolvePath(Request $request, $path, array &$json_response, &$code) {
    if (!$this->resolveRedirect($request, $path, $json_response, $code)) {
      $this->resolveAliasPath($request, $path, $json_response, $code);
    }

    // Dispatch a GET_ROUTE event so that other modules can modify it.
    if ($code != Response::HTTP_BAD_REQUEST) {
      $entity = $this->routeEntity ? clone $this->routeEntity : NULL;
      $event = new GetRouteEvent(clone $request, $json_response, $entity, $code, clone $this->cacheMetadata);
      $this->eventDispatcher->dispatch(TideApiEvents::GET_ROUTE, $event);
      // Update the response.
      $code = $event->getCode();
      $json_response = $event->getJsonResponse();
      if ($event->isOk()) {
        $this->cacheMetadata->addCacheableDependency($event->getCacheableMetadata());
        if (!$entity) {
          // Re-retrieve the entity from the event.
          if (!empty($json_response['data']['attributes']['entity_id'])) {
            $entity = $this->entityTypeManager->getStorage($json_response['data']['attributes']['entity_type'])
              ->load($json_response['data']['attributes']['entity_id']);
          }
        }

        if ($entity) {
          $this->cacheMetadata->addCacheableDependency($entity);
          // Cache the response with the same tags with the entity.
          $cached_route_data = [
            'json_response' => $json_response['data'],
            'uri' => ($entity->getEntityTypeId() != 'redirect') ? $entity->toUrl()->toUriString() : NULL,
            'id' => $entity->uuid(),
          ];
          $this->cache('data')->set($this->cacheId, $cached_route_data, Cache::PERMANENT, $this->cacheMetadata->getCacheTags());
        }
      }
      // Something set the Event to failure.
      else {
        unset($json_response['data']);
      }
    }
  }

  /**
   * Find the current entity based on the path if no entity is defined in cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   * @param string $path
   *   The requested path.
   * @param array $json_response
   *   The current json response array.
   * @param int $code
   *   The current HTTP Status code.
   */
  protected function resolveAliasPath(Request $request, $path, array &$json_response, &$code) {
    $source = $this->aliasManager->getPathByAlias($path);
    $url = $this->apiHelper->findUrlFromPath($source);
    if ($url) {
      // Check if the current user has permission to access the path.
      if ($url->access()) {
        $entity = $this->apiHelper->findEntityFromUrl($url);
        if ($entity) {
          $this->routeEntity = $entity;
          $json_response['data']['id'] = $entity->uuid();
          $this->apiHelper->setJsonResponseDataAttributesFromEntity($json_response, $entity, $this->cacheMetadata);

          // Cache the response with the same tags with the entity.
          $cached_route_data = [
            'json_response' => $json_response['data'],
            'uri' => $url->toUriString(),
            'id' => $entity->uuid(),
          ];
          $this->cache('data')
            ->set($this->cacheId, $cached_route_data, Cache::PERMANENT, $entity->getCacheTags());

          $code = Response::HTTP_OK;
          unset($json_response['errors']);
        }
      }
      else {
        $code = Response::HTTP_FORBIDDEN;
        $this->apiHelper->setJsonResponseError($json_response, $code);
      }
    }
  }

  /**
   * Attempt to resolve a redirect from the requested path.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $path
   *   The request path.
   * @param array $json_response
   *   The current JSON response.
   * @param int $code
   *   The return code.
   *
   * @return bool
   *   TRUE if the redirect can be resolved.
   */
  protected function resolveRedirect(Request $request, $path, array &$json_response, &$code) {
    if ($path != '/') {
      $base_url = $request->getSchemeAndHttpHost() . $request->getBaseUrl();
      $query = [];
      $url = parse_url($base_url . $path);
      if (is_array($url)) {
        if (!empty($url['path'])) {
          $path = $url['path'];
        }
        if (!empty($url['query'])) {
          parse_str($url['query'], $query);
        }
      }

      $language = $this->languageManager->getCurrentLanguage()->getId();

      // Attempt to find the redirect.
      $redirect = $this->redirectRepository->findMatchingRedirect($path, $query, $language);
      // Could it be a wildcard one?
      if (!$redirect) {
        $redirect = $this->redirectRepository->findMatchingWildcardRedirect($path, $query, $language);
      }

      // Redirect found.
      if ($redirect) {
        $this->routeEntity = $redirect;
        $this->cacheMetadata->addCacheableDependency($redirect);

        $destination = $redirect->getRedirectUrl();
        $redirect_url = $destination->toString(TRUE);
        $this->cacheMetadata->addCacheableDependency($redirect_url);

        $json_response['data']['id'] = $redirect->uuid();
        $json_response['data']['type'] = 'redirect';
        $json_response['data']['attributes'] = [
          'status_code' => $redirect->getStatusCode(),
          'redirect_url' => $redirect_url->getGeneratedUrl(),
          'redirect_type' => $destination->isExternal() ? 'external' : 'internal',
        ];
        $code = Response::HTTP_OK;
        unset($json_response['errors']);

        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Generate the cache ID.
   *
   * @param string $path
   *   The requested path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object.
   */
  protected function initializeCacheId($path, Request $request) {
    // Create a GetCacheIdEvent preparing for dispatching the Event.
    $event = new GetCacheIdEvent($request, 'tide_api:route:path:' . substr($path, 0, 128) . hash('sha256', $path));
    // Dispatching GetCacheIdEvent event to give other modules an opportunity to
    // alter it.
    $event = $this->eventDispatcher->dispatch(TideApiEvents::GET_CACHE_ID, $event);
    $this->cacheId = $event->getCacheId();
  }

}
