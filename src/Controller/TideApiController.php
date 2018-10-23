<?php

namespace Drupal\tide_api\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\redirect\Exception\RedirectLoopException;
use Drupal\redirect\RedirectRepository;
use Drupal\tide_api\Event\GetRouteEvent;
use Drupal\tide_api\TideApiEvents;
use Drupal\tide_api\TideApiHelper;
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
   * @var  \Drupal\redirect\RedirectRepository
   */
  protected $redirectRepository;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
   * @param \Drupal\redirect\RedirectRepository $redirect_repository
   *   The redirect entity repository.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   */
  public function __construct(AliasManagerInterface $alias_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepository $resource_type_repository, EventDispatcherInterface $event_dispatcher, TideApiHelper $api_helper, RedirectRepository $redirect_repository, LanguageManagerInterface $language_manager) {
    $this->aliasManager = $alias_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->eventDispatcher = $event_dispatcher;
    $this->apiHelper = $api_helper;
    $this->redirectRepository = $redirect_repository;
    $this->languageManager = $language_manager;
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
      $container->get('redirect.repository'),
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
    $json_response = [
      'links' => [
        'self' => Url::fromRoute('tide_api.jsonapi.alias')->setAbsolute()->toString(),
      ],
    ];
    $json_response['errors'] = [$this->t('Path not found.')];
    $entity = NULL;

    $path = $request->query->get('path');
    $site = $request->query->get('site');

    try {
      if ($path) {
        $cid = 'tide_api:route:path:' . hash('sha256', $path);

        // First load from cache_data.
        $cached_route_data = $this->cache('data')->get($cid);
        if ($cached_route_data) {
          // Check if the current has permission to access the path.
          $url = Url::fromUri($cached_route_data->data['uri']);
          if ($url->access()) {
            $code = Response::HTTP_OK;
            $json_response['data'] = $cached_route_data->data['json_response'];
            unset($json_response['errors']);
          }
          else {
            $code = Response::HTTP_FORBIDDEN;
            $json_response['errors'] = [$this->t('Permission denied.')];
          }
        }
        // Cache miss.
        else {

          if ($redirect = $this->redirectRepository->findMatchingRedirect($path, [], $this->languageManager->getCurrentLanguage()->getId())) {
            // Handle internal path.
            $url = $redirect->getRedirectUrl();
            $redirect_url = $url->toString();
            $type = substr($redirect_url, 1, strpos($redirect_url, '/', 1) - 1) == 'site-' . $site ? 'internal' : 'external';
            if ($type == 'external') {
              $redirect_url = $url->setAbsolute()->toString();
            }
            else {
              $redirect_url = str_replace('/site-' . $site, '', $redirect_url);
            }
            $json_response['data'] = [
              'status_code' => $redirect->getStatusCode(),
              'type' => $type,
              'redirect_url' => $redirect_url,
            ];
            $code = Response::HTTP_OK;
            unset($json_response['errors']);
          }
          else {

            $source = $this->aliasManager->getPathByAlias($path);

            $url = $this->apiHelper->findUrlFromPath($source);
            if ($url) {
              // Check if the current has permission to access the path.
              if ($url->access()) {
                $entity = $this->apiHelper->findEntityFromUrl($url);
                if ($entity) {
                  $endpoint = $this->apiHelper->findEndpointFromEntity($entity);
                  $entity_type = $entity->getEntityTypeId();
                  $json_response['data'] = [
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
                  ];
                  $this->cache('data')
                    ->set($cid, $cached_route_data, Cache::PERMANENT, $entity->getCacheTags());

                  $code = Response::HTTP_OK;
                  unset($json_response['errors']);
                }
              } else {
                $code = Response::HTTP_FORBIDDEN;
                $json_response['errors'] = [$this->t('Permission denied.')];
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
              if (!$event->isOk()) {
                unset($json_response['data']);
              }
            }
          }
        }

      }
      else {
        $code = Response::HTTP_BAD_REQUEST;
        $json_response['errors'] = [$this->t('URL query parameter "path" is required.')];
      }
    }
    catch (\Exception $exception) {
      $code = Response::HTTP_BAD_REQUEST;
      $json_response['errors'] = [$exception->getMessage()];
    }

    return new JsonResponse($json_response, $code);
  }

}
