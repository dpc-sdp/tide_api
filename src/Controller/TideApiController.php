<?php

namespace Drupal\tide_api\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\tide_api\TideApiRedirectRepository;
use Drupal\tide_api\Event\GetRouteEvent;
use Drupal\tide_api\TideApiEvents;
use Drupal\tide_api\TideApiHelper;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TideApiController.
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
   * The site helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $siteHelper;

  /**
   * Constructs a new PathController.
   *
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   JSONAPI resource type repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\tide_api\TideApiHelper $api_helper
   *   The Tide API Helper.
   * @param \Drupal\tide_api\TideApiRedirectRepository $redirect_repository
   *   The redirect entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\tide_site\TideSiteHelper $site_helper
   *   The Tide Site Helper.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepository $resource_type_repository, EventDispatcherInterface $event_dispatcher, TideApiHelper $api_helper, TideApiRedirectRepository $redirect_repository, LanguageManagerInterface $language_manager, TideSiteHelper $site_helper = NULL) {
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->apiHelper = $api_helper;
    $this->redirectRepository = $redirect_repository;
    $this->languageManager = $language_manager;
    $this->siteHelper = $site_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    if (\Drupal::moduleHandler()->moduleExists('tide_site')) {
      return new static(
        $container->get('path.alias_manager'),
        $container->get('entity_type.manager'),
        $container->get('jsonapi.resource_type.repository'),
        $container->get('event_dispatcher'),
        $container->get('tide_api.helper'),
        $container->get('tide_api.repository'),
        $container->get('language_manager'),
        $container->get('tide_site.helper')
      );
    }
    else {
      return new static(
        $container->get('path.alias_manager'),
        $container->get('entity_type.manager'),
        $container->get('jsonapi.resource_type.repository'),
        $container->get('event_dispatcher'),
        $container->get('tide_api.helper'),
        $container->get('tide_api.repository'),
        $container->get('language_manager')
      );
    }
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
    global $base_url;
    $code = Response::HTTP_NOT_FOUND;
    $entity = NULL;

    $path = $request->query->get('path');
    $query = [];
    if ($url = parse_url($base_url . $path)) {
      $path = $url['path'];
      parse_str($url['query'], $query);
    }
    $site = $request->query->get('site');

    $json_response = [
      'data' => [
        'type' => 'route',
        'links' => [
          'self' => [
            'href' => Url::fromRoute('tide_api.jsonapi.alias')->setAbsolute()->toString(),
          ],
        ],
      ],
      'errors' => [
        [
          'status' => $code,
          'title' => $this->t('Path not found.'),
        ],
      ],
      'links' => [
        'self' => [
          'href' => Url::fromRoute('tide_api.jsonapi.alias')->setAbsolute()->toString(),
        ],
      ],
    ];

    try {
      if ($path) {
        $cid = 'tide_api:route:path:' . hash('sha256', $path . $site);

        // First load from cache_data.
        $cached_route_data = $this->cache('data')->get($cid);
        if ($cached_route_data) {
          // Check if the current has permission to access the path.
          $url = Url::fromUri($cached_route_data->data['uri']);
          if ($url->access()) {
            $code = Response::HTTP_OK;
            $json_response['data']['id'] = $cached_route_data->data['id'];
            $json_response['data']['attributes'] = $cached_route_data->data['json_response'];
            unset($json_response['errors']);
          }
          else {
            $code = Response::HTTP_FORBIDDEN;
            $json_response['errors'] = [
              [
                'status' => $code,
                'title' => $this->t('Permission denied.'),
              ],
            ];
            unset($json_response['data']);
          }
        }
        // Cache miss.
        else {
          $this->resolvePath($request, $path, $site, $cid, $json_response, $code);
        }

      }
      else {
        $code = Response::HTTP_BAD_REQUEST;
        $json_response['errors'] = [
          [
            'status' => $code,
            'title' => $this->t("URL query parameter 'path' is required."),
          ],
        ];
        unset($json_response['data']);
      }
    }
    catch (\Exception $exception) {
      $code = Response::HTTP_BAD_REQUEST;
      $json_response['errors'] = [
        [
          'status' => $code,
          'title' => $exception->getMessage(),
        ],
      ];
      unset($json_response['data']);
    }

    return new JsonResponse($json_response, $code);
  }

  /**
   * Find the current entity based on the path if no entity is defined in cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Request object.
   * @param string $path
   *   The passed in path.
   * @param string $site
   *   The passed in site.
   * @param int $cid
   *   The id of the cached response.
   * @param array $json_response
   *   The current json response array.
   * @param int $code
   *   The current HTTP Status code.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function resolvePath(Request $request, $path, $site, $cid, array &$json_response, &$code) {

    if ($path !== '/' && $redirect = $this->redirectRepository->findMatchingRedirect($path, [], $this->languageManager->getCurrentLanguage()->getId())) {
      $this->resolveRedirectPath($redirect, $site, $json_response, $code);
    }
    else {
      $this->resolveAliasPath($request, $path, $site, $cid, $json_response, $code);
    }
    // If it's 404 Path Not Found, look for a wildcard redirect.
    if ($code == 404) {
      if ($path !== '/' && $redirect = $this->redirectRepository->findMatchingWildcardRedirect($path,
          [], $this->languageManager->getCurrentLanguage()->getId())) {
        $this->resolveRedirectPath($redirect, $site, $json_response, $code);
      }
    }
  }

  /**
   * Find the current entity based on the path if no entity is defined in cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Request object.
   * @param string $path
   *   The passed in path.
   * @param string $site
   *   The passed in site.
   * @param int $cid
   *   The id of the cached response.
   * @param array $json_response
   *   The current json response array.
   * @param int $code
   *   The current HTTP Status code.
   */
  private function resolveAliasPath(Request $request, $path, $site, $cid, array &$json_response, &$code) {
    $source = $this->aliasManager->getPathByAlias($path);

    $url = $this->apiHelper->findUrlFromPath($source);
    if ($url) {
      // Check if the current has permission to access the path.
      if ($url->access()) {
        $entity = $this->apiHelper->findEntityFromUrl($url);
        if ($entity) {
          $json_response['data']['id'] = $entity->uuid();
          $endpoint = $this->apiHelper->findEndpointFromEntity($entity);
          $entity_type = $entity->getEntityTypeId();
          $json_response['data']['attributes'] = [
            'entity_type' => $entity_type,
            'entity_id' => $entity->id(),
            'bundle' => $entity->bundle(),
            'uuid' => $entity->uuid(),
            'endpoint' => $endpoint,
          ];

          // Cache the response with the same tags with the entity.
          $cached_route_data = [
            'json_response' => $json_response['data'],
            'uri' => $url->toUriString(),
            'id' => $entity->uuid(),
          ];
          $this->cache('data')
            ->set($cid, $cached_route_data, Cache::PERMANENT, $entity->getCacheTags());

          $code = Response::HTTP_OK;
          unset($json_response['errors']);
        }
      }
      else {
        $code = Response::HTTP_FORBIDDEN;
        $json_response['errors'] = [
          [
            'status' => $code,
            'title' => $this->t('Permission denied.'),
          ],
        ];
        unset($json_response['data']);
      }
    }
    // Dispatch a GET_ROUTE event so that other modules can modify it.
    if ($code != Response::HTTP_BAD_REQUEST) {
      $event_entity = NULL;
      if ($entity) {
        $event_entity = clone $entity;
      }
      $event = new GetRouteEvent(clone $request, $json_response, $event_entity, $code);
      $this->eventDispatcher->dispatch(TideApiEvents::GET_ROUTE, $event);
      // Update the response.
      $code = $event->getCode();
      $json_response = $event->getJsonResponse();
      if ($event->isOk()) {
        // @TODO: Entity is not always a node.
        $url = Url::fromRoute('entity.node.canonical', ['node' => $json_response["data"]["entity_id"]]);
        // Cache the response with the same tags with the entity.
        $cache_entity = $this->apiHelper->findEntityFromUrl($url);
        $cached_route_data = [
          'json_response' => $json_response['data'],
          'uri' => $url->toUriString(),
          'id' => $entity ? $entity->uuid() : NULL,
        ];

        // Cache the response.
        if ($cache_entity) {
          $this->cache('data')
            ->set($cid, $cached_route_data, Cache::PERMANENT, $cache_entity->getCacheTags());
        }
      }
      // Something set the Event to failure.
      else {
        unset($json_response['data']);
      }
    }
  }

  /**
   * Work out a redirect path based on site and any redirects defined.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The resolved redirect.
   * @param string $site
   *   The passed in site.
   * @param array $json_response
   *   The current json response array.
   * @param int $code
   *   The current HTTP Status code.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function resolveRedirectPath(Redirect $redirect, $site, array &$json_response, &$code) {
    // Handle internal path.
    $url = $redirect->getRedirectUrl();
    $redirect_url = $url->toString();
    if (!is_null($this->siteHelper)) {
      $type = substr($redirect_url, 1, strpos($redirect_url, '/', 1) - 1) == 'site-' . $site ? 'internal' : 'external-site';

      if (strpos($redirect_url, '/site-' . $site) === 0) {
        $redirect_url = str_replace('/site-' . $site, '', $redirect_url);
      }

      if (strpos($redirect_url, 'http') === 0) {
        $type = 'external';
      }

      if ($type == 'external-site') {
        $new_site_id = substr($redirect_url, strpos($redirect_url, '-', 1) + 1);
        $new_site_id = substr($new_site_id, 0, strpos($new_site_id, '/', 1));
        if (!is_numeric($new_site_id)) {
          $code = Response::HTTP_BAD_REQUEST;
          $json_response['errors'] = [
            [
              'status' => $code,
              'title' => $this->t('You must include a site id in the To url.'),
            ],
          ];
          unset($json_response['data']);
        }
        else {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $this::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->load($new_site_id);
          $base_url = $this->siteHelper->getSiteBaseUrl($term);
          $redirect_url = $base_url . substr($redirect_url,
              strpos($redirect_url, '/', 1));
          $type = 'external';
        }
      }
    }

    if ($code != Response::HTTP_BAD_REQUEST) {
      $json_response['data']['id'] = $redirect->uuid();
      $json_response['data']['type'] = $type;
      $json_response['data']['attributes']['status_code'] = $redirect->getStatusCode();
      $json_response['data']['attributes']['redirect_url'] = $redirect_url;
      $code = Response::HTTP_OK;
      unset($json_response['errors']);
    }
  }

}
