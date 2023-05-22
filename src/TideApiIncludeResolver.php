<?php

namespace Drupal\tide_api;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\Access\EntityAccessChecker;
use Drupal\jsonapi\IncludeResolver;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * {@inheritdoc}
 */
class TideApiIncludeResolver extends IncludeResolver {

  /**
   * Original service object.
   */
  protected IncludeResolver $innerService;

  /**
   * {@inheritdoc}
   */
  public function __construct(IncludeResolver $original_include_resolver, EntityTypeManagerInterface $entity_type_manager, EntityAccessChecker $entity_access_checker) {
    $this->innerService = $original_include_resolver;
    parent::__construct($entity_type_manager, $entity_access_checker);
  }

  /**
   * {@inheritdoc}
   */
  public function resolve($data, $include_parameter) {
    assert($data instanceof ResourceObject || $data instanceof ResourceObjectData);
    $data = $data instanceof ResourceObjectData ? $data : new ResourceObjectData([$data], 1);
    $include_tree = self::toIncludeTree($data, $include_parameter);
    return IncludedData::deduplicate($this->resolveIncludeTree($include_tree, $data));
  }

  /**
   * {@inheritdoc}
   */
  protected static function toIncludeTree(ResourceObjectData $data, $include_parameter) {
    // $include_parameter: 'one.two.three, one.two.four'.
    $include_paths = array_map('trim', explode(',', $include_parameter));
    // $exploded_paths: [['one', 'two', 'three'], ['one', 'two', 'four']].
    $exploded_paths = array_map(function ($include_path) {
      return array_map('trim', explode('.', $include_path));
    }, $include_paths);
    $resolved_paths_per_resource_type = [];
    /** @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface $resource_object */
    foreach ($data as $resource_object) {
      $resource_type = $resource_object->getResourceType();
      $resource_type_name = $resource_type->getTypeName();
      if (isset($resolved_paths_per_resource_type[$resource_type_name])) {
        continue;
      }
      $resolved_paths_per_resource_type[$resource_type_name] = self::resolveInternalIncludePaths($resource_type, $exploded_paths);
    }
    $resolved_paths = array_reduce($resolved_paths_per_resource_type, 'array_merge', []);
    return static::buildTree($resolved_paths);
  }

  /**
   * Resolves an array of public field paths.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $base_resource_type
   *   The base resource type from which to resolve an internal include path.
   * @param array $paths
   *   An array of exploded include paths.
   *
   * @return array
   *   An array of all possible internal include paths derived from the given
   *   public include paths.
   *
   * @see self::buildTree
   */
  protected static function resolveInternalIncludePaths(ResourceType $base_resource_type, array $paths) {
    $internal_paths = array_map(function ($exploded_path) use ($base_resource_type) {
      if (empty($exploded_path)) {
        return [];
      }
      return \Drupal::service('jsonapi.field_resolver')->resolveInternalIncludePath($base_resource_type, $exploded_path);
    }, $paths);
    $flattened_paths = array_reduce($internal_paths, 'array_merge', []);
    return $flattened_paths;
  }

}
