<?php

namespace Drupal\tide_api\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\jsonapi\EventSubscriber\JsonApiRequestValidator;
use Drupal\jsonapi\JsonApiSpec;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class TideApiJsonApiRequestValidator.
 *
 * @package Drupal\tide_api
 */
class TideApiJsonApiRequestValidator extends JsonApiRequestValidator {

  /**
   * The Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * TideApiJsonApiRequestValidator constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Get custom query parameters.
   *
   * @return array
   *   The custom params.
   */
  protected function getCustomQueryParameters() {
    $custom_params = [];
    $this->moduleHandler->alter('tide_api_jsonapi_custom_query_parameters', $custom_params);
    return $custom_params;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\jsonapi\EventSubscriber\JsonApiRequestValidator::validateQueryParams()
   */
  protected function validateQueryParams(Request $request) {
    $invalid_query_params = [];
    foreach (array_keys($request->query->all()) as $query_parameter_name) {
      // Ignore reserved (official) query parameters.
      if (in_array($query_parameter_name, JsonApiSpec::getReservedQueryParameters())) {
        continue;
      }

      if (!JsonApiSpec::isValidCustomQueryParameter($query_parameter_name)) {
        $invalid_query_params[] = $query_parameter_name;
      }
    }

    // Drupal uses the `_format` query parameter for Content-Type negotiation.
    // Using it violates the JSON:API spec. Nudge people nicely in the correct
    // direction. (This is special cased because using it is pretty common.)
    if (in_array('_format', $invalid_query_params, TRUE)) {
      $uri_without_query_string = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
      $exception = new CacheableBadRequestHttpException((new CacheableMetadata())->addCacheContexts(['url.query_args:_format']), 'JSON:API does not need that ugly \'_format\' query string! 🤘 Use the URL provided in \'links\' 🙏');
      $exception->setHeaders(['Link' => $uri_without_query_string]);
      throw $exception;
    }

    $extra_params = $this->getCustomQueryParameters();
    $invalid_query_params = array_diff($invalid_query_params, $extra_params);
    if (empty($invalid_query_params)) {
      return NULL;
    }

    $message = sprintf('The following query parameters violate the JSON:API spec: \'%s\'.', implode("', '", $invalid_query_params));
    $exception = new CacheableBadRequestHttpException((new CacheableMetadata())->addCacheContexts(['url.query_args']), $message);
    $exception->setHeaders(['Link' => 'http://jsonapi.org/format/#query-parameters']);
    throw $exception;
  }

}
