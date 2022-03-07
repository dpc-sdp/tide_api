<?php

namespace Drupal\tide_content_collection;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\elasticsearch_connector\ElasticSearch\Parameters\Factory\IndexFactory;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\FieldsHelper;

/**
 * Class Search API Index Helper.
 *
 * @package Drupal\tide_content_collection
 */
class SearchApiIndexHelper implements SearchApiIndexHelperInterface {
  use StringTranslationTrait;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Search API Field Helper.
   *
   * @var \Drupal\search_api\Utility\FieldsHelper
   */
  protected $sapiFieldsHelper;

  /**
   * The Entity Type Bundle Info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * SearchApiIndexHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The Entity Type Bundle Info service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The string translation service.
   * @param \Drupal\search_api\Utility\FieldsHelper $sapi_fields_helper
   *   The SAPI Fields Helper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, TranslationInterface $translation, FieldsHelper $sapi_fields_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->stringTranslation = $translation;
    $this->sapiFieldsHelper = $sapi_fields_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledSearchApiNodeIndices() : array {
    try {
      $index_storage = $this->entityTypeManager->getStorage('search_api_index');
      /** @var \Drupal\search_api\IndexInterface[] $indices */
      $indices = $index_storage->loadByProperties(['status' => TRUE]);
      return $indices;
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledSearchApiNodeIndexList() : array {
    $indices = $this->getEnabledSearchApiNodeIndices();
    $options = [];
    foreach ($indices as $index) {
      if ($this->isValidNodeIndex($index)) {
        $options[$index->id()] = $index->label();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidNodeIndex(IndexInterface $index) : bool {
    if (!$index->status()) {
      return FALSE;
    }
    $entity_types = $index->getEntityTypes();
    if (count($entity_types) > 1) {
      return FALSE;
    }

    $entity_type = reset($entity_types);
    return !($entity_type !== 'node');
  }

  /**
   * {@inheritdoc}
   */
  public function loadSearchApiIndex(string $id) : ?IndexInterface {
    try {
      $index_storage = $this->entityTypeManager->getStorage('search_api_index');
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $index_storage->load($id);
      return $index;
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isNodeStickyIndexedAsInteger(IndexInterface $index) : bool {
    $sticky = $this->getIndexedNodeField($index, 'sticky');
    return $sticky ? $this->isIntegerField($index, $sticky) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isNodeTypeIndexed(IndexInterface $index) : bool {
    return (bool) $this->getIndexedNodeField($index, 'type');
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeTypes() : array {
    try {
      /** @var \Drupal\node\NodeTypeInterface[] $types */
      $types = $this->entityTypeManager->getStorage('node_type')
        ->loadMultiple();
      $node_types = [];
      foreach ($types as $type) {
        $node_types[$type->id()] = $type->label();
      }
      asort($node_types);
      return $node_types;
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildTaxonomyTermSelector(string $vid, array $default_values = []) : ?array {
    try {
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
        ->load($vid);
      if (!$vocabulary) {
        return NULL;
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
      return NULL;
    }

    $element = [
      '#title' => $this->t('Select terms'),
    ] + $this->buildEntityReferenceSelector('taxonomy_term', [$vid], $default_values);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityReferenceSelector(string $entity_type_id, array $bundles, array $default_values = []) : ?array {
    try {
      $plural_label = $this->entityTypeManager->getDefinition($entity_type_id)->getPluralLabel();

      $entity_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $delta => $bundle) {
        if (!isset($entity_bundles[$bundle])) {
          unset($bundles[$delta]);
        }
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
      return NULL;
    }

    $element = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select @plural_label', ['@plural_label' => $plural_label]),
      '#target_type' => $entity_type_id,
      '#tags' => TRUE,
      '#autocreate' => FALSE,
      '#default_value' => [],
    ];
    if (!empty($bundles)) {
      $element['#selection_settings']['target_bundles'] = $bundles;
    }

    if (!empty($default_values)) {
      try {
        $entities = $this->entityTypeManager->getStorage($entity_type_id)
          ->loadMultiple($default_values);
        if ($entities) {
          /** @var \Drupal\taxonomy\TermInterface[] $entities */
          foreach ($entities as $entity) {
            if (in_array($entity->bundle(), $bundles)) {
              $element['#default_value'][] = $entity;
            }
          }
        }
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_content_collection', $exception);
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldTopicIndexed(IndexInterface $index) : bool {
    return (bool) $this->getIndexedNodeField($index, 'field_topic');
  }

  /**
   * {@inheritdoc}
   */
  public function buildTopicFilter(IndexInterface $index, array $default_values = []) : ?array {
    if (!$this->isFieldTopicIndexed($index)) {
      return NULL;
    }

    $element = $this->buildTaxonomyTermSelector('topic', $default_values);
    if ($element) {
      $element['#title'] = $this->t('Select Topics');
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isFieldTagsIndexed(IndexInterface $index) : bool {
    return (bool) $this->getIndexedNodeField($index, 'field_tags');
  }

  /**
   * {@inheritdoc}
   */
  public function buildTagsFilter(IndexInterface $index, array $default_values = []) : ?array {
    if (!$this->isFieldTagsIndexed($index)) {
      return NULL;
    }

    $element = $this->buildTaxonomyTermSelector('tags', $default_values);
    if ($element) {
      $element['#title'] = $this->t('Select Tags');
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntityReferenceFieldFilter(IndexInterface $index, string $field_id, array $default_values = []) : ?array {
    $reference_fields = $this->extractIndexEntityReferenceFields($index);
    if (!isset($reference_fields[$field_id])) {
      return NULL;
    }

    try {
      $field = $reference_fields[$field_id];
      return $this->buildEntityReferenceSelector($field['target_type'], $field['target_bundles'] ?? [], $default_values);
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityReferenceFieldInfo(IndexInterface $index, string $field_id) : ?array {
    $reference_fields = $this->extractIndexEntityReferenceFields($index);
    if (isset($reference_fields[$field_id])) {
      return $reference_fields[$field_id];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexedNodeField(IndexInterface $index, string $node_field_name) : ?string {
    $index_fields = &drupal_static(__CLASS__ . '::' . __METHOD__, []);
    if (array_key_exists($node_field_name, $index_fields)) {
      return $index_fields[$node_field_name];
    }

    $fields = $index->getFields();
    foreach ($fields as $field) {
      if ($field->getPropertyPath() == $node_field_name && $field->getDatasourceId() === 'entity:node') {
        $index_fields[$node_field_name] = $field->getFieldIdentifier();
        return $index_fields[$node_field_name];
      }
    }

    $index_fields[$node_field_name] = NULL;
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidIndexField(IndexInterface $index, string $field_id) : bool {
    return (bool) $index->getField($field_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isIntegerField(IndexInterface $index, string $field_id) : bool {
    $field = $index->getField($field_id);
    return $field && ($field->getType() === 'integer');
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexIntegerFields(IndexInterface $index, array $excludes = []) : array {
    $fields = [];
    foreach ($index->getFields() as $field_id => $field) {
      if (!in_array($field_id, $excludes) && $field->getType() === 'integer') {
        $fields[$field_id] = $field->getLabel();
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexStringFieldPropertyPath(IndexInterface $index, string $field_id) : ?string {
    $field = $index->getField($field_id);
    if (!empty($field)) {
      return $field->getPropertyPath();
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexStringFields(IndexInterface $index, array $excludes = []) : array {
    $fields = [];
    foreach ($index->getFields() as $field_id => $field) {
      if (!in_array($field_id, $excludes) && $field->getType() === 'string') {
        $fields[$field_id] = $field->getLabel();
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexTextFields(IndexInterface $index, array $excludes = []) : array {
    $fields = [];
    foreach ($index->getFields() as $field_id => $field) {
      if (!in_array($field_id, $excludes) && $field->getType() === 'text') {
        $fields[$field_id] = $field->getLabel();
      }
    }

    return $fields;
  }

  /**
   * Extract all index entity reference fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   * @param string[] $excludes
   *   The field IDs to exclude.
   *
   * @return array
   *   The list of entity reference fields.
   */
  protected function extractIndexEntityReferenceFields(IndexInterface $index, array $excludes = []) : array {
    $reference_fields = &drupal_static(__METHOD__);
    if (isset($reference_fields)) {
      return static::excludeArrayKey($reference_fields, $excludes);
    }

    $reference_fields = [];
    $fields = $index->getFields();
    foreach ($fields as $field_id => $field) {
      if ($field->getType() !== 'integer') {
        continue;
      }
      try {
        $definition = $this->sapiFieldsHelper->retrieveNestedProperty($index->getPropertyDefinitions('entity:node'), $field->getPropertyPath());
        if (!($definition instanceof FieldItemDataDefinitionInterface)) {
          continue;
        }
        /** @var \Drupal\field\FieldConfigInterface $field_config */
        $field_config = $definition->getFieldDefinition();
        if (!static::isEntityReferenceField($field_config->getType())) {
          continue;
        }
        $settings = $definition->getSettings();
        $target_type = $settings['target_type'];
        if (empty($target_type)) {
          continue;
        }

        $reference_fields[$field_id] = [
          'label' => $field->getLabel(),
          'target_type' => $target_type,
          'target_bundles' => $settings['handler_settings']['target_bundles'] ?? [],
        ];
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_content_collection', $exception);
        continue;
      }
    }

    return static::excludeArrayKey($reference_fields, $excludes);
  }

  /**
   * Check if a field type is entity reference.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return bool
   *   TRUE if the field is entity reference.
   */
  protected static function isEntityReferenceField($field_type) {
    $entity_reference_types = [
      'entity_reference',
      'entity_reference_revisions',
      'entity_hierarchy',
    ];

    return in_array($field_type, $entity_reference_types);
  }

  /**
   * {@inheritdoc}
   */
  public static function excludeArrayKey(array $array, array $excluded_keys = []) : array {
    return array_diff_key($array, array_combine($excluded_keys, $excluded_keys));
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexEntityReferenceFields(IndexInterface $index, array $excludes = []) : array {
    $reference_fields = $this->extractIndexEntityReferenceFields($index, $excludes);
    $fields = [];
    foreach ($reference_fields as $field_id => $field_info) {
      $fields[$field_id] = $field_info['label'];
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexDateFields(IndexInterface $index, array $excludes = []) : array {
    $date_fields = &drupal_static(__METHOD__);
    if (isset($date_fields)) {
      return static::excludeArrayKey($date_fields, $excludes);
    }

    /** @var \Drupal\search_api\Item\FieldInterface[] $date_fields */
    $date_fields = [];
    $fields = $index->getFields();
    foreach ($fields as $field_id => $field) {
      if ($field->getType() === 'date') {
        $date_fields[$field_id] = $field->getLabel();
      }
    }

    return static::excludeArrayKey($date_fields, $excludes);
  }

  /**
   * {@inheritdoc}
   */
  public function getServerIndexId($index) : ?string {
    try {
      if (!($index instanceof IndexInterface) && is_string($index)) {
        $index = $this->loadSearchApiIndex($index);
        if (!$index) {
          return NULL;
        }
      }
      $server = $index->getServerInstance();
      if ($server && $server->getBackendId() === 'elasticsearch') {
        return IndexFactory::getIndexName($index);
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_content_collection', $exception);
    }

    return $index->id();
  }

}
