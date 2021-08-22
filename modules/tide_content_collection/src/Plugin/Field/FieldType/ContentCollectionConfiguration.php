<?php

namespace Drupal\tide_content_collection\Plugin\Field\FieldType;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringLongItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Defines the 'content_collection_configuration' field type.
 *
 * @FieldType(
 *   id = "content_collection_configuration",
 *   label = @Translation("Content Collection Configuration"),
 *   description = @Translation("A field containing JSON configuration for automated content collection."),
 *   category = @Translation("Content Collection"),
 *   default_widget = "content_collection_configuration_raw",
 *   default_formatter = "content_collection_configuration_raw",
 * )
 */
class ContentCollectionConfiguration extends StringLongItem {

  /**
   * The Search API Index helper.
   *
   * @var \Drupal\tide_content_collection\SearchApiIndexHelperInterface
   */
  protected $indexHelper;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);
    $this->indexHelper = static::getContainer()->get('tide_content_collection.search_api.index_helper');
    $this->moduleHandler = static::getContainer()->get('module_handler');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['configuration'] = DataDefinition::create('any')
      ->setLabel(t('Computed configuration'))
      ->setDescription(t('The computed configuration object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\tide_content_collection\ContentCollectionConfigurationProcessed')
      ->setSetting('text source', 'value');

    return $properties;
  }

  /**
   * Return the container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  protected static function getContainer() {
    return \Drupal::getContainer();
  }

  /**
   * Return the default JSON schema.
   *
   * @return string
   *   The schema.
   */
  public static function getDefaultJsonSchema() : string {
    $cache_id = 'tide_content_collection.json.schema';
    $schema_data = \Drupal::cache('data')->get($cache_id);
    if ($schema_data !== FALSE) {
      $schema_data = $schema_data->data;
      if (Json::decode($schema_data) === NULL) {
        $schema_data = '';
      }
    }
    if (!$schema_data) {
      $schema_file = drupal_get_path('module', 'tide_content_collection') . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'schema.json';
      if (@file_exists($schema_file)) {
        $schema_data = file_get_contents($schema_file);
        if ($schema_data === FALSE) {
          $schema_data = '';
        }
        else {
          \Drupal::cache('data')->set($cache_id, $schema_data);
        }
      }
    }

    return $schema_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'index' => 'node',
      'schema' => static::getDefaultJsonSchema(),
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $sapi_options = $this->indexHelper->getEnabledSearchApiNodeIndexList();
    $sapi_index = $this->getSetting('index');
    if (!isset($sapi_options[$sapi_index])) {
      $sapi_index = NULL;
    }

    $element['index'] = [
      '#type' => 'select',
      '#title' => t('Search API Index'),
      '#options' => $sapi_options,
      '#default_value' => $sapi_index,
      '#required' => TRUE,
      '#weight' => -20,
      '#disabled' => $has_data,
    ];

    $schema = $this->getSetting('schema');
    $element['schema'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JSON Schema'),
      '#description' => $this->t('This JSON Schema can be used by the Raw JSON field widget to validate the JSON string.'),
      '#rows' => 10,
      '#default_value' => $schema,
      '#required' => FALSE,
      '#weight' => -10,
      '#element_validate' => [
        [$this, 'validateJsonSchema'],
      ],
    ];

    // Use CodeMirror editor if webform module is enabled.
    if ($this->moduleHandler->moduleExists('webform')) {
      $element['schema']['#type'] = 'webform_codemirror';
      $element['schema']['#mode'] = 'javascript';
      $element['schema']['#skip_validation'] = TRUE;
      $element['schema']['#attributes']['style'] = 'max-height: 500px;';
    }

    return $element;
  }

  /**
   * Validate callback to check the JSON Schema.
   *
   * @param array $element
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateJsonSchema(array $element, FormStateInterface $form_state) {
    $schema = $form_state->getValue(['settings', 'schema']);
    if (!empty($schema)) {
      if (Json::decode($schema) === NULL) {
        $form_state->setError($element, t('Invalid JSON Schema.'));
      }
    }
  }

}
