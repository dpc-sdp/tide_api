<?php

namespace Drupal\tide_content_collection\Plugin\Field\FieldWidget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tide_automated_listing\SearchApiIndexHelper;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementation of the 'content_collection_configuration_widget_enhanced' widget.
 *
 * @FieldWidget(
 *   id = "content_collection_configuration_enhanced",
 *   label = @Translation("Content Collection Configuration (Enhanced)"),
 *   field_types = {
 *     "content_collection_configuration"
 *   }
 * )
 */
class ContentCollectionConfigurationWidgetEnhanced extends StringTextareaWidget implements ContainerFactoryPluginInterface {

  /**
   * The Search API Index helper.
   *
   * @var \Drupal\tide_automated_listing\SearchApiIndexHelper
   */
  protected $indexHelper;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The search API index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ModuleHandlerInterface $module_handler, SearchApiIndexHelper $index_helper) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->moduleHandler = $module_handler;
    $this->indexHelper = $index_helper;
    $this->getIndex();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('tide_automated_listing.sapi_index_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'schema_validation' => FALSE,
      'callToAction' => FALSE,
      'contentTypes' => TRUE,
      'field_topic' => TRUE,
      'field_tags' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() : array {
    $summary = parent::settingsSummary();

    $schema_validation = $this->getSetting('schema_validation');
    $summary[] = $this->t('Validate against the JSON schema: @validation', [
      '@validation' => $schema_validation ? $this->t('Yes') : $this->t('No'),
    ]);

    $callToAction = $this->getSetting('callToAction');
    $summary[] = $this->t('Call To Action Field: @status', [
      '@status' => $callToAction ? $this->t('Enabled') : $this->t('Disabled'),
    ]);

    $contentTypes = $this->getSetting('contentTypes');
    $summary[] = $this->t('Content Types Field: @status', [
      '@status' => $contentTypes ? $this->t('Enabled') : $this->t('Disabled'),
    ]);

    $field_topic = $this->getSetting('field_topic');
    $summary[] = $this->t('Content Topics Field: @status', [
      '@status' => $field_topic ? $this->t('Enabled') : $this->t('Disabled'),
    ]);

    $field_tags = $this->getSetting('field_tags');
    $summary[] = $this->t('Content Tags Field: @status', [
      '@status' => $field_tags ? $this->t('Enabled') : $this->t('Disabled'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    $element = parent::settingsForm($form, $form_state);

    $element['schema_validation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate against the JSON schema'),
      '#default_value' => $this->getSetting('schema_validation'),
    ];
    $element['callToAction'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable Call To Action'),
      '#default_value' => $this->getSetting('callToAction'),
    ];
    $element['contentTypes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable Content Types'),
      '#default_value' => $this->getSetting('contentTypes'),
    ];
    $element['contentFields']['field_topic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable Content Topics Field'),
      '#default_value' => $this->getSetting('field_topic'),
    ];
    $element['contentFields']['field_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable/Disable Content Tags Field'),
      '#default_value' => $this->getSetting('field_tags'),
    ];
    return $element;
  }

  /**
   * Get search API index.
   *
   * @return \Drupal\search_api\IndexInterface|null|false
   *   The index, NULL upon failure, FALSE when no index is selected.
   */
  protected function getIndex() {
    if (!$this->index) {
      // Load and verify the index.
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = NULL;
      $index_id = $this->fieldDefinition->getFieldStorageDefinition()
        ->getSetting('index');
      if ($index_id) {
        $index = $this->indexHelper->loadSearchApiIndex($index_id);
        if ($index && $this->indexHelper->isValidNodeIndex($index)) {
          $this->index = $index;
        }
      }
      else {
        return FALSE;
      }
    }

    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['value']['#element_validate'][] = [$this, 'validateJson'];

    // Use CodeMirror editor if webform module is enabled.
    if ($this->moduleHandler->moduleExists('webform')) {
      $element['value']['#type'] = 'webform_codemirror';
      $element['value']['#mode'] = 'javascript';
      $element['value']['#skip_validation'] = TRUE;
      $element['value']['#attributes']['style'] = 'max-height: 500px;';
    }

    // Hide the YAML configuration field.
    $element['value']['#access'] = FALSE;

    // Load and verify the index.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();
    $index_error = '';
    if ($index === NULL) {
      $index_error = $this->t('Invalid Search API Index.');
    }
    elseif ($index === FALSE) {
      $index_error = $this->t('No Search API Index has been selected for this field.');
    }

    if (!$index) {
      $element['error'] = [
        '#type' => 'markup',
        '#markup' => $index_error,
        '#prefix' => '<div class="form-item--error-message">',
        '#suffix' => '</div>',
        '#allowed_tags' => ['div'],
      ];
      return $element;
    }

    $json = $element['value']['#default_value'];
    if (!empty($json)) {
      $json_object = json_decode($json, TRUE);
      if ($json_object === NULL) {
        $element['value']['#access'] = TRUE;
        return $element;
      }
    }

    $element['title'] = [
      '#title' => $this->t('Title'),
      '#type' => 'textfield',
      '#description' => 'Title displayed above results.',
      '#default_value' => $json_object['title'] ?? '',
      '#weight' => 1,
    ];

    $element['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#description' => 'Description displayed above the results',
      '#default_value' => $json_object['description'] ?? '',
      '#weight' => 2,
    ];

    if ($this->getSetting('callToAction')) {
      $element['callToAction'] = [
        '#type' => 'details',
        '#title' => $this->t('Call To Action'),
        '#description' => 'A link to another page.',
        '#open' => TRUE,
        '#weight' => 3,
      ];
      $element['callToAction']['text'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Text'),
        '#description'   => $this->t('Display text of the link.'),
      ];
      $element['callToAction']['url'] = [
        '#type'          => 'url',
        '#title'         => $this->t('Url'),
        '#description'   => $this->t('A relative or absolute URL.'),
      ];
    }

    $configuration = $items[$delta]->configuration ?? [];

    $element['#attached']['library'][] = 'field_group/formatter.horizontal_tabs';

    $element['tabs'] = [
      '#type' => 'horizontal_tabs',
      '#tree' => TRUE,
      '#weight' => 4,
      '#group_name' => 'tabs',
    ];

    $this->buildContentTab($items, $delta, $element, $form, $form_state, $configuration, $json_object);
    $this->buildLayoutTab($items, $delta, $element, $form, $form_state, $configuration, $json_object);

    return $element;
  }

  /**
   * Build Content Tab.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param int $delta
   *   The current delta.
   * @param array $element
   *   The element.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The YAML configuration of the listing.
   * @param array $json_object
   *   The json_object of the listing.
   */
  protected function buildContentTab(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL) {
    $element['tabs']['content'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Content'),
      '#group_name' => 'tabs_content',
    ];

    if ($this->indexHelper->isNodeTypeIndexed($this->index) && $this->getSetting('contentTypes')) {
      $element['tabs']['content']['contentTypes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select Content types'),
        '#options' => $this->indexHelper->getNodeTypes(),
        '#default_value' => $json_object['internal']['contentTypes'] ?? [],
      ];
    }

    $element['tabs']['content']['contentFields'] = [
      '#type' => 'details',
      '#title' => $this->t('Content Fields'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#group_name' => 'tabs_content_fields',
    ];

    if ($this->indexHelper->isFieldTopicIndexed($this->index) && $this->getSetting('field_topic')) {
      $element['tabs']['content']['contentFields']['field_topic_wrapper']['field_topic'] = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_topic', $json_object['internal']['contentFields']['field_topic']['values'] ?? []);
      $element['tabs']['content']['contentFields']['field_topic_wrapper']['field_topic_operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields']['field_topic']['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
    }

    if ($this->indexHelper->isFieldTagsIndexed($this->index) && $this->getSetting('field_tags')) {
      $element['tabs']['content']['contentFields']['field_tags_wrapper']['field_tags'] = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_tags', $json_object['internal']['contentFields']['field_tags']['values'] ?? []);
      $element['tabs']['content']['contentFields']['field_tags_wrapper']['field_tags_operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields']['field_tags']['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
    }
  }

  /**
   * Build Layout Tab.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param int $delta
   *   The current delta.
   * @param array $element
   *   The element.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $configuration
   *   The YAML configuration of the listing.
   * @param array $json_object
   *   The json_object of the listing.
   */
  protected function buildLayoutTab(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL) {
    $element['tabs']['layout'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Layout'),
      '#group_name' => 'layout',
    ];
  }

  /**
   * Build a filter operator select element.
   *
   * @param string $default_value
   *   The default operator.
   * @param string $description
   *   The description of the operator.
   *
   * @return string[]
   *   The form element.
   */
  protected function buildFilterOperatorSelect($default_value = 'AND', $description = NULL) {
    return [
      '#type' => 'select',
      '#title' => $this->t('Filter operator'),
      '#description' => $description,
      '#default_value' => $default_value ?? 'AND',
      '#options' => [
        'AND' => $this->t('AND'),
        'OR' => $this->t('OR'),
      ],
    ];
  }

  /**
   * Callback to validate the JSON.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateJson(array $element, FormStateInterface $form_state) {
    $json = $element['#value'];
    if (!empty($json)) {
      $json_object = json_decode($json);
      if ($json_object === NULL) {
        $form_state->setError($element, t('Invalid JSON.'));
      }
      elseif ($this->getSetting('schema_validation')) {
        // Validate against the JSON Schema.
        $json_schema = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('schema');
        if (!empty($json_schema)) {
          $json_schema_object = json_decode($json_schema);
          $schema_storage = new SchemaStorage();
          $schema_storage->addSchema('file://content_collection_configuration_schema', $json_schema_object);
          $json_validator = new Validator(new Factory($schema_storage));
          $num_errors = $json_validator->validate($json_object, $json_schema_object);
          if ($num_errors) {
            $errors = [];
            foreach ($json_validator->getErrors() as $error) {
              $errors[] = t('[@property] @message', [
                '@property' => $error['property'],
                '@message' => $error['message'],
              ]);
            }
            $form_state->setError($element, t('JSON does not validate against the schema. Violations: @errors.', [
              '@errors' => implode(' - ', $errors),
            ]));
          }
        }
      }
    }
  }

}
