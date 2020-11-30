<?php

namespace Drupal\tide_share_link\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes as JsonapiRoutes;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for Share Link Token.
 *
 * @package Drupal\tide_share_link
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * The JSON:API base path.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * Instantiates a Routes object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public function __construct(ContainerInterface $container) {
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container);
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = new RouteCollection();

    if (empty($this->resourceTypeRepository)) {
      $this->resourceTypeRepository = $this->container->get('jsonapi.resource_type.repository');
      $this->providerIds = array_keys($this->container->getParameter('authentication_providers'));
      $this->jsonApiBasePath = $this->container->getParameter('jsonapi.base_path');
    }

    $resource_type = $this->resourceTypeRepository->get('share_link_token', 'share_link_token');
    if (!$resource_type) {
      return $routes;
    }

    $entity_type_id = $resource_type->getEntityTypeId();

    $individual_route = new Route("/{$this->jsonApiBasePath}/share_link/{entity}/{node}");
    $individual_route->setMethods(['GET'])
      ->addDefaults([
        JsonapiRoutes::JSON_API_ROUTE_FLAG_KEY => TRUE,
        RouteObjectInterface::CONTROLLER_NAME => 'jsonapi.entity_resource.' . $entity_type_id . ':getShareLinkToken',
        JsonapiRoutes::RESOURCE_TYPE_KEY => $resource_type->getTypeName(),
      ])
      ->addRequirements([
        '_access' => 'TRUE',
        '_format' => 'api_json',
        '_content_type_format' => 'api_json',
      ])
      ->addOptions([
        '_auth' => $this->providerIds,
        'parameters' => [
          'entity' => ['type' => 'entity:' . $entity_type_id],
          'node' => [
            'type' => 'entity:node',
            'converter' => 'paramconverter.entity',
          ],
          JsonapiRoutes::RESOURCE_TYPE_KEY => ['type' => ResourceTypeConverter::PARAM_TYPE_ID],
        ],
      ]);
    $routes->add(static::getRouteName($resource_type, 'individual'), $individual_route);

    return $routes;
  }

  /**
   * Get a unique route name for the JSON:API resource type and route type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string $route_type
   *   The route type. E.g. 'individual' or 'collection'.
   *
   * @return string
   *   The generated route name.
   */
  public static function getRouteName(ResourceType $resource_type, $route_type) {
    return sprintf('jsonapi.%s.%s', $resource_type->getTypeName(), $route_type);
  }

}
