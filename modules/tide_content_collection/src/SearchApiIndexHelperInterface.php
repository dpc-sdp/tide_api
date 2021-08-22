<?php

namespace Drupal\tide_content_collection;

use Drupal\search_api\IndexInterface;

/**
 * Search API Index Helper interface.
 */
interface SearchApiIndexHelperInterface {

  /**
   * Get all enabled Search API indices.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   List of enabled indices.
   */
  public function getEnabledSearchApiNodeIndices() : array;

  /**
   * Get the options for Index select.
   *
   * @return string[]
   *   List of indices.
   */
  public function getEnabledSearchApiNodeIndexList() : array;

  /**
   * Check if the Search API Index is a node-only index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API Index to check.
   *
   * @return bool
   *   TRUE if the index is a valid node index.
   */
  public function isValidNodeIndex(IndexInterface $index) : bool;

  /**
   * Load a Search API Index.
   *
   * @param string $id
   *   The ID of the search index to load.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The Search API index, NULL upon failure.
   */
  public function loadSearchApiIndex(string $id) : ?IndexInterface;

  /**
   * Check if an Search API index has the Sticky field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check.
   *
   * @return bool
   *   TRUE if the node index has the Sticky field.
   */
  public function isNodeStickyIndexedAsInteger(IndexInterface $index) : bool;

  /**
   * Check if a Search API index has the Content type field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check.
   *
   * @return bool
   *   TRUE if the node index has the Content type field.
   */
  public function isNodeTypeIndexed(IndexInterface $index) : bool;

  /**
   * Get list of node types.
   *
   * @return string[]
   *   Node types.
   */
  public function getNodeTypes() : array;

  /**
   * Build an Entity autocomplete form element to select taxonomy terms.
   *
   * @param string $vid
   *   The vocabulary machine name.
   * @param int[] $default_values
   *   The default values as a list of tid.
   *
   * @return array|null
   *   The form array, or NULL if the filter can't be built.
   */
  public function buildTaxonomyTermSelector(string $vid, array $default_values = []) : ?array;

  /**
   * Build an Entity autocomplete form element to select entities.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string[] $bundles
   *   The target bundles.
   * @param int[] $default_values
   *   The default values as a list of tid.
   *
   * @return array|null
   *   The form array, or NULL if the filter can't be built.
   */
  public function buildEntityReferenceSelector(string $entity_type_id, array $bundles, array $default_values = []) : ?array;

  /**
   * Check if a Search API index has the Topic field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check.
   *
   * @return bool
   *   TRUE if the node index has the Topic field.
   */
  public function isFieldTopicIndexed(IndexInterface $index) : bool;

  /**
   * Build the Topic filter.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param int[] $default_values
   *   The default values as a list of tid.
   *
   * @return array|null
   *   The form array, or NULL if the filter can't be built.
   */
  public function buildTopicFilter(IndexInterface $index, array $default_values = []) : ?array;

  /**
   * Check if a Search API index has the Tags field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check.
   *
   * @return bool
   *   TRUE if the node index has the Tags field.
   */
  public function isFieldTagsIndexed(IndexInterface $index) : bool;

  /**
   * Build the Tags filter.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param int[] $default_values
   *   The default values as a list of tid.
   *
   * @return array|null
   *   The form array, or NULL if the filter can't be built.
   */
  public function buildTagsFilter(IndexInterface $index, array $default_values = []) : ?array;

  /**
   * Build the filter for an entity reference field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param string $field_id
   *   The index field id.
   * @param array $default_values
   *   The default values as a list of entity ID.
   *
   * @return array|null
   *   The form array, or NULL if the filter can't be built.
   */
  public function buildEntityReferenceFieldFilter(IndexInterface $index, string $field_id, array $default_values = []) : ?array;

  /**
   * Get the SAPI Index field ID of a node field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to check.
   * @param string $node_field_name
   *   The Node field name to check.
   *
   * @return string|null
   *   The index field ID if the node index has the given field.
   */
  public function getIndexedNodeField(IndexInterface $index, string $node_field_name) : ?string;

  /**
   * Check if the search index has a field.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param string $field_id
   *   The field ID.
   *
   * @return bool
   *   TRUE if valid.
   */
  public function isValidIndexField(IndexInterface $index, string $field_id) : bool;

  /**
   * Check if an index field is integer.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param string $field_id
   *   The index field ID.
   *
   * @return bool
   *   Result.
   */
  public function isIntegerField(IndexInterface $index, string $field_id) : bool;

  /**
   * Get all index integer fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string[] $excludes
   *   The field IDs to exclude.
   *
   * @return array
   *   The list of integer fields.
   */
  public function getIndexIntegerFields(IndexInterface $index, array $excludes = []) : array;

  /**
   * Returns an array without the excluded keys.
   *
   * @param array $array
   *   The original array.
   * @param array $excluded_keys
   *   The excluded keys.
   *
   * @return array
   *   The filtered array.
   */
  public static function excludeArrayKey(array $array, array $excluded_keys = []) : array;

  /**
   * Get all index entity reference fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string[] $excludes
   *   The field IDs to exclude.
   *
   * @return array
   *   The list of entity reference fields.
   */
  public function getIndexEntityReferenceFields(IndexInterface $index, array $excludes = []) : array;

  /**
   * Get all date fields of an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   * @param string[] $excludes
   *   Field IDs to exclude.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   Date fields.
   */
  public function getIndexDateFields(IndexInterface $index, array $excludes = []) : array;

  /**
   * Retrieve the index ID on the server.
   *
   * @param \Drupal\search_api\IndexInterface|string $index
   *   The index ID, or the fully-loaded search ID index.
   *
   * @return string|null
   *   The index ID on the server, NULL upon failure.
   */
  public function getServerIndexId($index) : ?string;

}
