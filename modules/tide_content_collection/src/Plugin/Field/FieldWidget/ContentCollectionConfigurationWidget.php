<?php

namespace Drupal\tide_content_collection\Plugin\Field\FieldWidget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tide_content_collection\SearchApiIndexHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\link\LinkItemInterface;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;

/**
 * Implementation of the content collection configuration widget.
 *
 * @FieldWidget(
 *   id = "content_collection_configuration_ui",
 *   label = @Translation("Content Collection Configuration"),
 *   field_types = {
 *     "content_collection_configuration"
 *   }
 * )
 */
class ContentCollectionConfigurationWidget extends StringTextareaWidget implements ContainerFactoryPluginInterface {

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
   * The search API index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ModuleHandlerInterface $module_handler, SearchApiIndexHelperInterface $index_helper, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->moduleHandler = $module_handler;
    $this->indexHelper = $index_helper;
    $this->getIndex();
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('tide_content_collection.search_api.index_helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'content' => [
        'internal' => [
          'contentTypes' => [
            'enabled' => TRUE,
            'allowed_values' => [],
            'default_values' => [],
          ],
          'field_topic' => [
            'enabled' => TRUE,
            'show_filter_operator' => FALSE,
            'default_values' => [],
          ],
          'field_tags' => [
            'enabled' => TRUE,
            'show_filter_operator' => FALSE,
            'default_values' => [],
          ],
        ],
        'enable_call_to_action' => FALSE,
      ],
      'filters' => [
        'enable_keyword_selection' => FALSE,
        'allowed_advanced_filters' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() : array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    $element = [];
    $element['#attached']['library'][] = 'field_group/formatter.horizontal_tabs';
    $settings = $this->getSettings();
    $field_name = $this->fieldDefinition->getName();
    // Load and verify the index.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getIndex();

    $element['settings'] = [
      '#type' => 'horizontal_tabs',
      '#tree' => TRUE,
      '#group_name' => 'settings',
    ];
    $element['settings']['content'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Content'),
      '#group_name' => 'tabs_content',
    ];

    $element['settings']['content']['enable_call_to_action'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Call to Action'),
      '#default_value' => $settings['content']['enable_call_to_action'] ?? FALSE,
      '#weight' => -1,
    ];

    $content_type_options = $this->indexHelper->getNodeTypes();
    if (!empty($content_type_options)) {
      $element['settings']['content']['internal']['contentTypes'] = [
        '#type' => 'details',
        '#title' => $this->t('Content Types'),
        '#open' => FALSE,
        '#collapsible' => TRUE,
        '#weight' => 2,
      ];
      $element['settings']['content']['internal']['contentTypes']['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable content types'),
        '#default_value' => $settings['content']['internal']['contentTypes']['enabled'] ?? FALSE,
      ];
      $element['settings']['content']['internal']['contentTypes']['allowed_values'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Allowed content types'),
        '#description' => $this->t('When no content type is selected in the widget settings, the widget will show all available content types in the Select content type filter.'),
        '#options' => $content_type_options,
        '#default_value' => $settings['content']['internal']['contentTypes']['allowed_values'] ?? [],
        '#weight' => 1,
      ];
      $element['settings']['content']['internal']['contentTypes']['default_values'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Default content types'),
        '#description' => $this->t('When no content type is selected in the widget settings, the widget will show all available content types in the Select content type filter.'),
        '#options' => $content_type_options,
        '#default_value' => $settings['content']['internal']['contentTypes']['default_values'] ?? [],
        '#weight' => 1,
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $field_name . '][settings_edit_form][settings][settings][content][internal][contentTypes][enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }

    if ($this->indexHelper->isFieldTopicIndexed($index)) {
      $element['settings']['content']['internal']['field_topic'] = [
        '#type' => 'details',
        '#title' => $this->t('Topic'),
        '#open' => FALSE,
        '#collapsible' => TRUE,
        '#weight' => 2,
      ];
      $element['settings']['content']['internal']['field_topic']['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Topic filter'),
        '#default_value' => $settings['content']['internal']['field_topic']['enabled'] ?? FALSE,
      ];
      $element['settings']['content']['internal']['field_topic']['show_filter_operator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show filter operator'),
        '#default_value' => $settings['content']['internal']['field_topic']['show_filter_operator'] ?? FALSE,
      ];
      $default_values = $settings['content']['internal']['field_topic']['default_values'] ?? [];
      $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_topic', $default_values);
      if ($field_filter) {
        $element['settings']['content']['internal']['field_topic']['default_values'] = $field_filter;
        $element['settings']['content']['internal']['field_topic']['default_values']['#title'] = $this->t('Default values for topics');
        $element['settings']['content']['internal']['field_topic']['default_values']['#states'] = [
          'visible' => [
            ':input[name="fields[' . $field_name . '][settings_edit_form][settings][settings][content][internal][field_topic][enabled]"]' => ['checked' => FALSE],
          ],
        ];
      }
    }

    if ($this->indexHelper->isFieldTagsIndexed($index)) {
      $element['settings']['content']['internal']['field_tags'] = [
        '#type' => 'details',
        '#title' => $this->t('Tags'),
        '#open' => FALSE,
        '#collapsible' => TRUE,
        '#weight' => 2,
      ];
      $element['settings']['content']['internal']['field_tags']['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable Tags filter'),
        '#default_value' => $settings['content']['internal']['field_tags']['enabled'] ?? FALSE,
      ];
      $element['settings']['content']['internal']['field_tags']['show_filter_operator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show filter operator'),
        '#default_value' => $settings['content']['internal']['field_tags']['show_filter_operator'] ?? FALSE,
      ];
      $default_values = $settings['content']['internal']['field_tags']['default_values'] ?? [];
      $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_tags', $default_values);
      if ($field_filter) {
        $element['settings']['content']['internal']['field_tags']['default_values'] = $field_filter;
        $element['settings']['content']['internal']['field_tags']['default_values']['#title'] = $this->t('Default values for tags');
        $element['settings']['content']['internal']['field_tags']['default_values']['#states'] = [
          'visible' => [
            ':input[name="fields[' . $field_name . '][settings_edit_form][settings][settings][content][internal][field_tags][enabled]"]' => ['checked' => FALSE],
          ],
        ];
      }
    }

    $entity_reference_fields = $this->getEntityReferenceFields();
    if (!empty($entity_reference_fields)) {
      foreach ($entity_reference_fields as $field_id => $field_label) {
        $element['settings']['content']['internal'][$field_id] = [
          '#type' => 'details',
          '#title' => $field_label,
          '#open' => FALSE,
          '#collapsible' => TRUE,
          '#weight' => 2,
        ];
        $element['settings']['content']['internal'][$field_id]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @field_id filter', ['@field_id' => $field_id]),
          '#default_value' => $settings['content']['internal'][$field_id]['enabled'] ?? FALSE,
        ];
        $element['settings']['content']['internal'][$field_id]['show_filter_operator'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show filter operator'),
          '#default_value' => $settings['content']['internal'][$field_id]['show_filter_operator'] ?? FALSE,
        ];
        $default_values = $default_values = $settings['content']['internal'][$field_id]['default_values'] ?? [];
        $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, $field_id, $default_values);
        if ($field_filter) {
          $element['settings']['content']['internal'][$field_id]['default_values'] = $field_filter;
          $element['settings']['content']['internal'][$field_id]['default_values']['#title'] = $this->t('Default values for @field_id', ['@field_id' => $field_id]);
          $element['settings']['content']['internal'][$field_id]['default_values']['#states'] = [
            'visible' => [
              ':input[name="fields[' . $field_name . '][settings_edit_form][settings][settings][content][internal][' . $field_id . '][enabled]"]' => ['checked' => FALSE],
            ],
          ];
        }
      }
    }

    $element['settings']['filters'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Filters'),
      '#group_name' => 'tabs_filters',
    ];
    $element['settings']['filters']['enable_keyword_selection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow selecting fields for keyword search'),
      '#default_value' => $settings['filters']['enable_keyword_selection'] ?? FALSE,
      '#weight' => 1,
    ];
    $advanced_filters_options = $this->getEntityReferenceFields(NULL, NULL, []);
    $validated_entity_reference_fields = $this->getValidatedIndexEntityReferenceFields();
    $advanced_filters_options = array_intersect_key($advanced_filters_options, array_flip((array) array_keys($validated_entity_reference_fields)));
    $element['settings']['filters']['allowed_advanced_filters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed advanced filters'),
      '#options' => $advanced_filters_options,
      '#default_value' => $settings['filters']['allowed_advanced_filters'] ?? [],
      '#weight' => 2,
    ];

    $element['settings']['#element_validate'][] = [$this, 'validateSettings'];

    return $element;
  }

  /**
   * Handler #element_validate for the "tabs" form elements in settingsForm().
   *
   * Used to set the settings value in a clean structure.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSettings(array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $base_key = [
      'fields',
      $field_name,
      'settings_edit_form',
      'settings',
    ];
    $input = $form_state->getValue(array_merge($base_key, [
      'settings',
    ]));
    $form_state->unsetValue($base_key);
    $entity_reference_fields = $this->getEntityReferenceFields();
    if (isset($input['content']['enable_call_to_action'])) {
      $value = $input['content']['enable_call_to_action'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, [
        'content',
        'enable_call_to_action',
      ]), $value);
    }
    if (isset($input['content']['internal']['contentTypes']['enabled'])) {
      $value = $input['content']['internal']['contentTypes']['enabled'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, [
        'content',
        'internal',
        'contentTypes',
        'enabled',
      ]), $value);
    }
    $content_types_key = ['content', 'internal', 'contentTypes'];
    if (isset($input['content']['internal']['contentTypes']['allowed_values'])) {
      $value = $input['content']['internal']['contentTypes']['allowed_values'] ? array_values(array_filter($input['content']['internal']['contentTypes']['allowed_values'])) : [];
      $form_state->setValue(array_merge($base_key, $content_types_key, ['allowed_values']), $value);
    }
    if (isset($input['content']['internal']['contentTypes']['default_values'])) {
      $value = $input['content']['internal']['contentTypes']['default_values'] ? array_values(array_filter($input['content']['internal']['contentTypes']['default_values'])) : [];
      $form_state->setValue(array_merge($base_key, $content_types_key, ['default_values']), $value);
    }
    $field_topic_key = ['content', 'internal', 'field_topic'];
    if (isset($input['content']['internal']['field_topic']['enabled'])) {
      $value = $input['content']['internal']['field_topic']['enabled'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, $field_topic_key, ['enabled']), $value);
    }
    if (isset($input['content']['internal']['field_topic']['show_filter_operator'])) {
      $value = $input['content']['internal']['field_topic']['show_filter_operator'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, $field_topic_key, ['show_filter_operator']), $value);
    }
    if (isset($input['content']['internal']['field_topic']['default_values'])) {
      $value = $input['content']['internal']['field_topic']['default_values'] ? array_column(array_values(array_filter($input['content']['internal']['field_topic']['default_values'])), 'target_id') : [];
      $form_state->setValue(array_merge($base_key, $field_topic_key, ['default_values']), $value);
    }
    $field_tags_key = ['content', 'internal', 'field_tags'];
    if (isset($input['content']['internal']['field_tags']['enabled'])) {
      $value = $input['content']['internal']['field_tags']['enabled'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, $field_tags_key, ['enabled']), $value);
    }
    if (isset($input['content']['internal']['field_tags']['show_filter_operator'])) {
      $value = $input['content']['internal']['field_tags']['show_filter_operator'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, $field_tags_key, ['show_filter_operator']), $value);
    }
    if (isset($input['content']['internal']['field_tags']['default_values'])) {
      $value = $input['content']['internal']['field_tags']['default_values'] ? array_column(array_values(array_filter($input['content']['internal']['field_tags']['default_values'])), 'target_id') : [];
      $form_state->setValue(array_merge($base_key, $field_tags_key, ['default_values']), $value);
    }
    if (!empty($entity_reference_fields)) {
      foreach ($entity_reference_fields as $field_id => $field_label) {
        $field_id_key = ['content', 'internal', $field_id];
        if (isset($input['content']['internal'][$field_id]['enabled'])) {
          $value = $input['content']['internal'][$field_id]['enabled'] ? TRUE : FALSE;
          $form_state->setValue(array_merge($base_key, $field_id_key, ['enabled']), $value);
        }
        if (isset($input['content']['internal'][$field_id]['show_filter_operator'])) {
          $value = $input['content']['internal'][$field_id]['show_filter_operator'] ? TRUE : FALSE;
          $form_state->setValue(array_merge($base_key, $field_id_key, ['show_filter_operator']), $value);
        }
        if (isset($input['content']['internal'][$field_id]['default_values'])) {
          $value = $input['content']['internal'][$field_id]['default_values'] ? array_column(array_values(array_filter($input['content']['internal'][$field_id]['default_values'])), 'target_id') : [];
          $form_state->setValue(array_merge($base_key, $field_id_key, ['default_values']), $value);
        }
      }
    }
    if (isset($input['filters']['enable_keyword_selection'])) {
      $value = $input['filters']['enable_keyword_selection'] ? TRUE : FALSE;
      $form_state->setValue(array_merge($base_key, [
        'filters',
        'enable_keyword_selection',
      ]), $value);
    }
    if (isset($input['filters']['allowed_advanced_filters'])) {
      $value = $input['filters']['allowed_advanced_filters'] ? array_values(array_filter($input['filters']['allowed_advanced_filters'])) : [];
      $form_state->setValue(array_merge($base_key, [
        'filters',
        'allowed_advanced_filters',
      ]), $value);
    }
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
    $settings = $this->getSettings();
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
    $json_object = [];
    if (!empty($json)) {
      $json_object = json_decode($json, TRUE);
      if ($json_object === NULL) {
        $json_object = [];
      }
    }

    if (!empty($settings['content']['enable_call_to_action']) && $settings['content']['enable_call_to_action']) {
      $element['callToAction'] = [
        '#type' => 'details',
        '#title' => $this->t('Call To Action'),
        '#description' => $this->t('A link to another page.'),
        '#open' => TRUE,
        '#weight' => 3,
      ];
      $element['callToAction']['text'] = [
        '#type'  => 'textfield',
        '#title'  => $this->t('Text'),
        '#default_value' => $json_object['callToAction']['text'] ?? '',
        '#description' => $this->t('Display text of the link.'),
      ];
      $element['callToAction']['url'] = [
        '#type' => 'url',
        '#title'  => $this->t('URL'),
        '#type' => 'entity_autocomplete',
        '#link_type' => LinkItemInterface::LINK_GENERIC,
        '#target_type' => 'node',
        '#default_value' => (!empty($json_object['callToAction']['url']) && (\Drupal::currentUser()->hasPermission('link to any page'))) ? static::getUriAsDisplayableString($json_object['callToAction']['url']) : NULL,
        '#attributes' => [
          'data-autocomplete-first-character-blacklist' => '/#?',
        ],
        '#process_default_value' => FALSE,
        '#element_validate' => [[$this, 'validateUriElement']],
        '#description' => $this->t('Start typing the title of a piece of content to select it. You can also enter an internal path such as %add-node or an external URL such as %url. Enter %front to link to the front page. Enter %nolink to display link text only.', [
          '%front' => '<front>',
          '%add-node' => '/node/add',
          '%url' => 'http://example.com',
          '%nolink' => '<nolink>',
        ]),
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
    $this->buildFiltersTab($items, $delta, $element, $form, $form_state, $configuration, $json_object);
    $this->buildAdvancedTab($items, $delta, $element, $form, $form_state, $configuration, $json_object);

    return $element;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    $valid_list = ['/', '?', '#'];
    if (parse_url($uri, PHP_URL_SCHEME) === 'internal' && !in_array($element['#value'][0], $valid_list, TRUE) && substr($element['#value'], 0, 7) !== '<front>') {
      $form_state->setError($element, t('Manually entered paths should start with one of the following characters: / ? #'));
      return;
    }
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The human readable string display value.
   *
   * @see static::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
      if ($entity_type === 'node' && !empty($entity)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * This method is the inverse of ::getUriAsDisplayableString().
   *
   * @param string $string
   *   The user-entered string.
   *
   * @return string
   *   The URI, if a non-empty $uri was passed.
   *
   * @see static::getUriAsDisplayableString()
   */
  protected static function getUserEnteredStringAsUri($string) {
    // By default, assume the entered string is an URI.
    $uri = trim($string);

    // Detect entity autocomplete string, map to 'entity:' URI.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($string);
    if ($entity_id !== NULL) {
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      $uri = 'entity:node/' . $entity_id;
    }
    // Support linking to nothing.
    elseif (in_array($string, ['<nolink>', '<none>'], TRUE)) {
      $uri = 'route:' . $string;
    }
    // Detect a schemeless string, map to 'internal:' URI.
    elseif (!empty($string) && parse_url($string, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (strpos($string, '<front>') === 0) {
        $string = '/' . substr($string, strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
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
    $settings = $this->getSettings();
    $element['tabs']['content'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Content'),
      '#group_name' => 'tabs_content',
    ];

    if ($this->indexHelper->isNodeTypeIndexed($this->index) && !empty($settings['content']['internal']['contentTypes']['enabled'])) {
      $content_types_options = $this->indexHelper->getNodeTypes();
      $allowed_content_types = $settings['content']['internal']['contentTypes']['allowed_values'];
      if (!empty($allowed_content_types)) {
        $content_types_options = array_intersect_key($content_types_options,
          array_flip($allowed_content_types));
      }
      $element['tabs']['content']['contentTypes'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Select content types'),
        '#options' => $content_types_options,
        '#default_value' => $json_object['internal']['contentTypes'] ?? [],
        '#weight' => 1,
        '#required' => TRUE,
      ];
    }

    if ($this->indexHelper->isFieldTopicIndexed($this->index) && !empty($settings['content']['internal']['field_topic']['enabled'])) {
      $default_values = $json_object['internal']['contentFields']['field_topic']['values'] ?? [];
      $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_topic', $default_values);
      if ($field_filter) {
        $element['tabs']['content']['field_topic_wrapper'] = [
          '#type' => 'details',
          '#title' => $this->t('Select topics'),
          '#open' => FALSE,
          '#collapsible' => TRUE,
          '#group_name' => 'tabs_content_filters_field_topic_wrapper',
          '#weight' => 2,
          '#description' => $this->t('Add your choice of topics for this content collection. You can choose more than 1. Start typing to choose a topic. Type a comma between topics. If you leave this field blank, ALL topics will be included.'),
        ];
        $element['tabs']['content']['field_topic_wrapper']['field_topic'] = $field_filter;
        $element['tabs']['content']['field_topic_wrapper']['field_topic']['#title'] = $this->t('Select topics');
        if ($settings['content']['internal']['field_topic']['show_filter_operator']) {
          $element['tabs']['content']['field_topic_wrapper']['operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields']['field_topic']['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
        }
        if (isset($json_object['internal']['contentFields']['field_topic'])) {
          $element['tabs']['content']['field_topic_wrapper']['#open'] = TRUE;
        }
      }
    }

    if ($this->indexHelper->isFieldTagsIndexed($this->index)  && !empty($settings['content']['internal']['field_tags']['enabled'])) {
      $default_values = $json_object['internal']['contentFields']['field_tags']['values'] ?? [];
      $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, 'field_tags', $default_values);
      if ($field_filter) {
        $element['tabs']['content']['field_tags_wrapper'] = [
          '#type' => 'details',
          '#title' => $this->t('Select tags'),
          '#open' => FALSE,
          '#collapsible' => TRUE,
          '#group_name' => 'tabs_content_filters_field_tags_wrapper',
          '#weight' => 3,
          '#description' => $this->t('Add your choice of tags for this content collection. You can choose more than 1. Start typing to choose a tag. Type a comma between tags.'),
        ];
        $element['tabs']['content']['field_tags_wrapper']['field_tags'] = $field_filter;
        $element['tabs']['content']['field_tags_wrapper']['field_tags']['#title'] = $this->t('Select tags');
        if ($settings['content']['internal']['field_tags']['show_filter_operator']) {
          $element['tabs']['content']['field_tags_wrapper']['operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields']['field_tags']['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
        }
        if (isset($json_object['internal']['contentFields']['field_tags'])) {
          $element['tabs']['content']['field_tags_wrapper']['#open'] = TRUE;
        }
      }
    }

    $this->buildContentTabAdvancedFilters($items, $delta, $element, $form, $form_state, $configuration, $json_object, $settings);
    $this->buildContentTabDateFilters($items, $delta, $element, $form, $form_state, $configuration, $json_object, $settings);

  }

  /**
   * Build Content Tab Advanced Filters.
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
   * @param array $settings
   *   The settings of the listing.
   */
  protected function buildContentTabAdvancedFilters(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL, array $settings = []) {
    $element['tabs']['content']['advanced_filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced filters'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#access' => TRUE,
      '#group_name' => 'tabs_content_advanced_filters',
      '#weight' => 5,
    ];

    // Generate all entity reference filters.
    $entity_reference_fields = $this->getEntityReferenceFields();

    if (!empty($entity_reference_fields)) {
      foreach ($entity_reference_fields as $field_id => $field_label) {
        if (!empty($settings['content']['internal'][$field_id]['enabled'])) {
          $default_values = $json_object['internal']['contentFields'][$field_id]['values'] ?? [];
          $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, $field_id, $default_values);
          if ($field_filter) {
            $element['tabs']['content']['advanced_filters']['#access'] = TRUE;
            $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper'] = [
              '#type' => 'details',
              '#title' => $field_label,
              '#open' => FALSE,
              '#collapsible' => TRUE,
              '#group_name' => 'tabs_content_advanced_filters_' . $field_id . '_wrapper',
            ];
            $field_filter['#title'] = $this->t('Add @label filters', ['@label' => strtolower($field_label)]);
            $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper'][$field_id] = $field_filter;
            $module_handler = \Drupal::service('module_handler');
            if ($module_handler->moduleExists('tide_site')) {
              if ($field_id === 'field_node_site') {
                $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['#open'] = TRUE;
                $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['#description'] =
                  $this->t(
                    'Add @label section filters to display ONLY content from specific @label sections. Start typing to choose a @label section. Type a comma between @label sections. If you leave this field blank, ALL @label sections will be included.', [
                      '@label' => strtolower($field_label),
                    ]);
              }
            }
            if ($settings['content']['internal'][$field_id]['show_filter_operator']) {
              $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields'][$field_id]['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
            }
            if (isset($json_object['internal']['contentFields'][$field_id]['values'])) {
              $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['#open'] = TRUE;
            }
          }
        }
      }
    }
    // Extra filters added via hook.
    $this->buildContentTabAdvancedExtraFilters($items, $delta, $element, $form, $form_state, $configuration, $json_object, $settings);
  }

  /**
   * Build Content Tab Advanced Extra Filters.
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
   * @param array $settings
   *   The settings of the listing.
   */
  protected function buildContentTabAdvancedExtraFilters(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL, array $settings = []) {
    // Build internal extra filters.
    $internal_extra_filters = $this->moduleHandler->invokeAll('tide_content_collection_internal_extra_filters_build', [
      $this->index,
      clone $items,
      $delta,
      $json_object['internal']['contentFields'] ?? [],
    ]);
    $context = [
      'index' => clone $items,
      'delta' => $delta,
      'filters' => $json_object['internal']['contentFields'] ?? [],
    ];
    $this->moduleHandler->alter('tide_content_collection_internal_extra_filters_build', $internal_extra_filters, $this->index, $context);
    if (!empty($internal_extra_filters) && is_array($internal_extra_filters)) {
      foreach ($internal_extra_filters as $field_id => $field_filter) {
        // Skip entity reference fields in internal extra filters.
        if (isset($entity_reference_fields[$field_id])) {
          continue;
        }
        $index_field = $this->index->getField($field_id);
        if ($index_field) {
          $element['tabs']['content']['advanced_filters']['#access'] = TRUE;
          $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper'] = [
            '#type' => 'details',
            '#title' => $index_field->getLabel(),
            '#open' => FALSE,
            '#collapsible' => TRUE,
            '#group_name' => 'filters' . $field_id . '_wrapper',
          ];
          $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper'][$field_id] = $field_filter;
          if (empty($field_filter['#disable_filter_operator'])) {
            $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['operator'] = $this->buildFilterOperatorSelect($json_object['internal']['contentFields'][$field_id]['operator'] ?? 'OR', $this->t('This filter operator is used to combined all the selected values together.'));
          }
          unset($field_filter['#disable_filter_operator']);
          if (isset($json_object['internal']['contentFields'][$field_id]['values'])) {
            $element['tabs']['content']['advanced_filters'][$field_id . '_wrapper']['#open'] = TRUE;
          }
        }
      }
    }
  }

  /**
   * Build Content Tab Date Filters.
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
   * @param array $settings
   *   The settings of the listing.
   */
  protected function buildContentTabDateFilters(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL, array $settings = []) {
    $date_fields = $this->indexHelper->getIndexDateFields($this->index);
    if (!empty($date_fields)) {
      $element['tabs']['content']['show_dateFilter'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show date filter.'),
        '#default_value' => FALSE,
        '#weight' => 6,
        '#access' => FALSE,
      ];

      $element['tabs']['content']['dateFilter'] = [
        '#type' => 'details',
        '#title' => $this->t('Date filter'),
        '#open' => TRUE,
        '#collapsible' => TRUE,
        '#group_name' => 'tabs_content_dateFilter',
        '#weight' => 7,
        '#access' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getFormStatesElementName('tabs|content|show_dateFilter', $items, $delta, $element) . '"]' => ['checked' => TRUE],
          ],
        ],
      ];
      if (!empty($json_object['internal']['dateFilter']['startDateField']) || !empty($json_object['internal']['dateFilter']['endDateField'])) {
        $element['tabs']['content']['dateFilter']['#open'] = TRUE;
        $element['tabs']['content']['show_dateFilter']['#default_value'] = TRUE;
      }

      $element['tabs']['content']['dateFilter']['criteria'] = [
        '#type' => 'select',
        '#title' => $this->t('Criteria'),
        '#default_value' => $json_object['internal']['dateFilter']['criteria'] ?? 'today',
        '#options' => [
          'today' => $this->t('Today'),
          'this_week' => $this->t('This Week'),
          'this_month' => $this->t('This Month'),
          'this_year' => $this->t('This Year'),
          'today_and_future' => $this->t('Today And Future'),
          'past' => $this->t('Past'),
          'range' => $this->t('Range'),
        ],
        '#description' => $this->t('Choose criteria for displaying content. You can set a specific date range or choose other preset options.'),
      ];
      $default_filter_today_start_date = $json_object['internal']['dateFilter']['startDateField'] ?? '';
      if (!isset($date_fields[$default_filter_today_start_date])) {
        $default_filter_today_start_date = '';
      }
      $default_filter_today_end_date = $json_object['internal']['dateFilter']['endDateField'] ?? '';
      if (!isset($date_fields[$default_filter_today_end_date])) {
        $default_filter_today_end_date = '';
      }
      $element['tabs']['content']['dateFilter']['startDateField'] = [
        '#type' => 'select',
        '#title' => $this->t('Start field selection/s'),
        '#default_value' => $default_filter_today_start_date,
        '#options' => ['' => $this->t('- No mapping -')] + $date_fields,
        '#description' => $this->t('Choose the content start field/s that match your content type selection/s above. If you selected more than 1 content type, you should select matching fields'),
      ];
      $element['tabs']['content']['dateFilter']['endDateField'] = [
        '#type' => 'select',
        '#title' => $this->t('End field selection/s'),
        '#default_value' => $default_filter_today_end_date,
        '#options' => ['' => $this->t('- No mapping -')] + $date_fields,
        '#description' => $this->t('Choose the content end field/s that match your content type selection/s above. If you selected more than 1 content type, you should select matching fields'),
      ];
      $element['tabs']['content']['dateFilter']['dateRange'] = [
        '#type' => 'details',
        '#title' => $this->t('Date range'),
        '#open' => TRUE,
        '#collapsible' => TRUE,
        '#group_name' => 'tabs_content_dateRange',
        '#weight' => 7,
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getFormStatesElementName('tabs|content|dateFilter|criteria', $items, $delta, $element) . '"]' => ['value' => 'range'],
          ],
        ],
        '#description' => $this->t('Select from date range options in content types.'),
      ];
      $default_date_range_start = '';
      $default_date_range_end = '';
      if (!empty($json_object['internal']['dateFilter']['dateRangeStart'])) {
        $default_date_range_start = DrupalDateTime::createFromFormat('Y-m-d\TH:i:sP', $json_object['internal']['dateFilter']['dateRangeStart']);
      }
      if (!empty($json_object['internal']['dateFilter']['dateRangeEnd'])) {
        $default_date_range_end = DrupalDateTime::createFromFormat('Y-m-d\TH:i:sP', $json_object['internal']['dateFilter']['dateRangeEnd']);
      }
      $element['tabs']['content']['dateFilter']['dateRange']['dateRangeStart'] = [
        '#type' => 'datetime',
        '#title' => $this->t('Date range start'),
        '#default_value' => $default_date_range_start,
      ];
      $element['tabs']['content']['dateFilter']['dateRange']['dateRangeEnd'] = [
        '#type' => 'datetime',
        '#title' => $this->t('Date range end'),
        '#default_value' => $default_date_range_end,
      ];
    }
  }

  /**
   * Get all entity reference fields.
   *
   * Excluded the field_topic & field_tags as they are loaded manually.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param int $delta
   *   The current delta.
   * @param array $exclude_fields
   *   The list of entity reference fields to be excluded.
   *
   * @return array
   *   The reference fields.
   */
  protected function getEntityReferenceFields(FieldItemListInterface $items = NULL, $delta = NULL, array $exclude_fields = [
    'field_topic',
    'field_tags',
  ]) {
    $entity_reference_fields = $this->indexHelper->getIndexEntityReferenceFields($this->index, ['nid']);
    // Allow other modules to remove entity reference filters.
    $excludes = $this->moduleHandler->invokeAll('tide_content_collection_entity_reference_fields_exclude', [
      $this->index,
      $entity_reference_fields,
      !empty($items) ? clone $items : NULL,
      $delta,
    ]);
    if (!empty($exclude_fields)) {
      $excludes = array_merge($excludes, $exclude_fields);
    }
    if (!empty($excludes) && is_array($excludes)) {
      $entity_reference_fields = $this->indexHelper::excludeArrayKey($entity_reference_fields, $excludes);
    }
    return $entity_reference_fields;
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

    $element['tabs']['layout']['display']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select layout'),
      '#default_value' => $json_object['interface']['display']['resultComponent']['type'] ?? 'card',
      '#options' => [
        'card' => $this->t('Grid view'),
        'search-result' => $this->t('List view'),
      ],
      '#required' => TRUE,
    ];

    $element['tabs']['layout']['display']['resultComponent']['style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Card display style'),
      '#default_value' => $json_object['interface']['display']['resultComponent']['style'] ?? 'thumbnail',
      '#options' => [
        'noImage' => $this->t('No image'),
        'thumbnail' => $this->t('Thumbnail'),
      ],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|layout|display|type', $items, $delta, $element) . '"]' => ['value' => 'card'],
        ],
      ],
    ];

    $element['tabs']['layout']['display']['card_number'] = [
      '#type' => 'radios',
      '#title' => $this->t('Number of results'),
      '#description' => $this->t('Select the maximum number of cards to be shown in this collection.'),
      '#default_value' => $json_object['interface']['display']['resultComponent']['card_number'] ?? '3',
      '#options' => [
        '3' => $this->t('3'),
        '6' => $this->t('6'),
        '9' => $this->t('9'),
      ],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|layout|display|type', $items, $delta, $element) . '"]' => ['value' => 'card'],
        ],
      ],
    ];

    $internal_sort_options = [NULL => $this->t('Authored on')];
    $date_fields = $this->indexHelper->getIndexDateFields($this->index);
    if (!empty($date_fields)) {
      $internal_sort_options += $date_fields;
    }
    $string_fields = $this->indexHelper->getIndexStringFields($this->index);
    if (!empty($string_fields)) {
      $internal_sort_options += $string_fields;
    }
    $element['tabs']['layout']['internal']['sort']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort content collection by'),
      '#default_value' => $json_object['internal']['sort'][0]['field'] ?? 'title',
      '#options' => $internal_sort_options,
      '#access' => FALSE,
    ];

    $element['tabs']['layout']['internal']['sort']['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort order'),
      '#default_value' => $json_object['internal']['sort'][0]['direction'] ?? 'asc',
      '#options' => [
        'asc' => $this->t('Ascending (asc)'),
        'desc' => $this->t('Descending (desc)'),
      ],
      '#access' => FALSE,
    ];

  }

  /**
   * Build Filters Tab.
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
   * @param array $settings
   *   The settings of the listing.
   */
  protected function buildFiltersTab(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL, array $settings = []) {
    $settings = $this->getSettings();
    $element['tabs']['filters'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Filters'),
      '#group_name' => 'tabs_filters',
    ];

    $element['tabs']['filters']['show_interface_filters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable filtering'),
      '#default_value' => empty($json_object) ? TRUE : FALSE,
      '#weight' => 1,
    ];
    if (!empty($json_object['interface']['keyword']) || !empty($json_object['interface']['filters'])) {
      $element['tabs']['filters']['show_interface_filters']['#default_value'] = TRUE;
    }

    $element['tabs']['filters']['interface_filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#group_name' => 'tabs_filters_interface_filters',
      '#weight' => 2,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|filters|show_interface_filters', $items, $delta, $element) . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['tabs']['filters']['interface_filters']['keyword'] = [
      '#type' => 'details',
      '#title' => $this->t('Keyword'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#group_name' => 'tabs_filters_interface_filters_keyword',
      '#weight' => 1,
    ];
    $element['tabs']['filters']['interface_filters']['keyword']['allow_keyword_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow keyword search'),
      '#default_value' => !empty($json_object['interface']['keyword']) ? TRUE : FALSE,
      '#weight' => 1,
    ];
    if (empty($json_object)) {
      $element['tabs']['filters']['interface_filters']['keyword']['allow_keyword_search']['#default_value'] = TRUE;
    }
    $element['tabs']['filters']['interface_filters']['keyword']['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $json_object['interface']['keyword']['label'] ?? $this->t("Search by keywords"),
      '#weight' => 2,
      '#access' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|keyword|allow_keyword_search', $items, $delta, $element) . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['tabs']['filters']['interface_filters']['keyword']['placeholder'] = [
      '#title' => $this->t('Placeholder text'),
      '#type' => 'textfield',
      '#default_value' => $json_object['interface']['keyword']['placeholder'] ?? $this->t("Enter keyword(s)"),
      '#weight' => 3,
      '#access' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|keyword|allow_keyword_search', $items, $delta, $element) . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    if (!empty($settings['filters']['enable_keyword_selection']) && $settings['filters']['enable_keyword_selection']) {
      $keyword_fields_options = [];
      $string_fields = $this->indexHelper->getIndexStringFields($this->index);
      if (!empty($string_fields)) {
        $keyword_fields_options += $string_fields;
      }
      $text_fields = $this->indexHelper->getIndexTextFields($this->index);
      if (!empty($text_fields)) {
        $keyword_fields_options += $text_fields;
      }
      $element['tabs']['filters']['interface_filters']['keyword']['fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Keyword fields'),
        '#options' => $keyword_fields_options,
        '#default_value' => $json_object['interface']['keyword']['fields'] ?? [
          'title',
          'summary_processed',
          'field_paragraph_summary',
          'field_landing_page_summary',
          'body',
        ],
        '#weight' => 4,
        '#access' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|keyword|allow_keyword_search', $items, $delta, $element) . '"]' => ['checked' => FALSE],
          ],
        ],
      ];
    }

    $element['tabs']['filters']['interface_filters']['advanced_filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced filters'),
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#group_name' => 'tabs_filters_interface_filters_advanced_filters',
      '#description' => $this->t('Select additional fields to use as filters.'),
      '#weight' => 2,
      '#access' => FALSE,
    ];
    $entity_reference_fields = $this->getEntityReferenceFields(NULL, NULL, []);
    $validated_entity_reference_fields = $this->getValidatedIndexEntityReferenceFields();
    if (!empty($entity_reference_fields)) {
      $allowed_reference_fields = array_filter($settings['filters']['allowed_advanced_filters']);
      if (!empty($allowed_reference_fields)) {
        $entity_reference_fields = array_intersect_key($entity_reference_fields, array_flip($allowed_reference_fields));
      }
      $weight = 1;
      $field_data = [];
      if (!empty($json_object['interface']['filters']['fields'])) {
        $field_data = array_combine(array_column($json_object['interface']['filters']['fields'], 'elasticsearch-field'), $json_object['interface']['filters']['fields']);
      }
      $sort_weight = -count($field_data);
      $sorted_entity_reference_fields = [];
      if (!empty($field_data)) {
        foreach ($field_data as $field_id => $field_value) {
          $field_id = preg_match('/\_name$/', $field_id) ? preg_replace('/\_name$/', '', $field_id) : $field_id;
          if (!in_array($field_id, $entity_reference_fields)) {
            $sorted_entity_reference_fields[$field_id] = $entity_reference_fields[$field_id];
          }
        }
        if (!empty($sorted_entity_reference_fields)) {
          $entity_reference_fields = array_merge($sorted_entity_reference_fields, $entity_reference_fields);
        }
      }
      $element['tabs']['filters']['interface_filters']['advanced_filters']['items'] = [
        '#type' => 'table',
        '#tableselect' => FALSE,
        '#attributes' => ['class' => ['advanced-filters-drag']],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'group-order-weight',
          ],
        ],
      ];
      foreach ($entity_reference_fields as $field_id => $field_label) {
        if (!empty($validated_entity_reference_fields[$field_id])) {
          $field_id = preg_match('/\_name$/', $field_id) ? preg_replace('/\_name$/', '', $field_id) : $field_id;
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['#attributes']['class'][] = 'draggable';
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'] = [
            '#type' => 'container',
            '#open' => TRUE,
            '#collapsible' => FALSE,
          ];
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details']['allow'] = [
            '#type' => 'checkbox',
            '#title' => ucfirst(strtolower($field_label)),
            '#default_value' => isset($field_data[$field_id]) ? TRUE : (isset($field_data[$field_id . '_name']) ? TRUE : FALSE),
            '#weight' => $weight++,
            '#attributes' => ['class' => ['advanced-filters-allow']],
          ];
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_label'] = [
            '#title' => $this->t('Label'),
            '#type' => 'textfield',
            '#default_value' => $field_data[$field_id]['options']['label'] ?? $field_data[$field_id . '_name']['options']['label'] ?? ucfirst(strtolower($field_label)),
            '#weight' => $weight++,
            '#states' => [
              'visible' => [
                ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|advanced_filters|items|' . $field_id . '|details|allow', $items, $delta, $element) . '"]' => ['checked' => TRUE],
              ],
            ],
          ];
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_placeholder'] = [
            '#title' => $this->t('Placeholder text'),
            '#type' => 'textfield',
            '#default_value' => $field_data[$field_id]['options']['placeholder'] ?? $field_data[$field_id . '_name']['options']['placeholder'] ?? $this->t('Select %label', ['%label' => strtolower($field_label)]),
            '#weight' => $weight++,
            '#states' => [
              'visible' => [
                ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|advanced_filters|items|' . $field_id . '|details|allow', $items, $delta, $element) . '"]' => ['checked' => TRUE],
              ],
            ],
          ];
          $default_values = [];
          if (!empty($field_data[$field_id]['options']['values'])) {
            foreach ($field_data[$field_id]['options']['values'] as $value) {
              $default_values[] = $value['id'];
            }
          }
          $field_filter = $this->indexHelper->buildEntityReferenceFieldFilter($this->index, $field_id, $default_values);
          if ($field_filter) {
            $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options'] = $field_filter;
            $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options']['#weight'] = $weight++;
            $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options']['#title'] = $this->t('Start typing to add and select %label filters. Type a comma and start typing to add more.', ['%label' => strtolower($field_label)]);
            $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options']['#description'] = $this->t('You can allow the user to filter by a subset of tags. If no tag is selected, all available tags will be shown.
            Note this filtering is affected by the tags filtering you set in the Content tab.');
            $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options']['#states'] = [
              'visible' => [
                ':input[name="' . $this->getFormStatesElementName('tabs|filters|interface_filters|advanced_filters|items|' . $field_id . '|details|allow', $items, $delta, $element) . '"]' => ['checked' => TRUE],
              ],
            ];
          }
          // Weight used to sort filters on FE.
          $element['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id][$field_id . '_weight'] = [
            '#title' => $this->t('Weight'),
            '#type' => 'textfield',
            '#default_value' => isset($field_data[$field_id]) ? $sort_weight++ : (isset($field_data[$field_id . '_name']) ? $sort_weight++ : 0),
            '#weight' => $weight++,
            '#attributes' => ['class' => ['group-order-weight']],
            '#states' => [
              'visible' => [
                ':input[name="' . $this->getFormStatesElementName('tabs|filters|show_interface_filters', $items, $delta, $element) . '"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }
      }
    }
  }

  /**
   * Build Advanced Tab.
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
  protected function buildAdvancedTab(FieldItemListInterface $items, $delta, array &$element, array &$form, FormStateInterface $form_state, array $configuration = NULL, array $json_object = NULL) {
    $element['tabs']['advanced'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#collapsible' => TRUE,
      '#title' => $this->t('Advanced'),
      '#group_name' => 'advanced',
    ];

    $element['tabs']['advanced']['display']['options']['enableResultsCountText'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show total number of results'),
      '#default_value' => empty($json_object) ? TRUE : FALSE,
    ];
    if (!empty($json_object['interface']['display']['options']['resultsCountText'])) {
      $element['tabs']['advanced']['display']['options']['enableResultsCountText']['#default_value'] = TRUE;
    }
    $element['tabs']['advanced']['display']['options']['resultsCountText'] = [
      '#type' => 'textfield',
      '#description' => $this->t('
        Text to display above the results.<br/>
        This is read out to a screen reader when a search is performed.<br/>
        Supports 2 tokens:<br/>
        - {range} - The current range of results E.g. 1-12<br/>
        - {count} - The total count of results
      '),
      '#default_value' => $json_object['interface']['display']['options']['resultsCountText'] ?? $this->t('Displaying {range} of {count} results.'),
      '#access' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getFormStatesElementName('tabs|advanced|display|options|enableResultsCountText', $items, $delta, $element) . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['tabs']['advanced']['display']['options']['noResultsText'] = [
      '#title' => $this->t('No results message'),
      '#type' => 'textfield',
      '#description' => $this->t('Text that will display when no results were returned.'),
      '#default_value' => $json_object['interface']['display']['options']['noResultsText'] ?? $this->t("Sorry! We couldn't find any matches."),
      '#access' => FALSE,
    ];

    $element['tabs']['advanced']['display']['options']['loadingText'] = [
      '#title' => $this->t('Loading message'),
      '#type' => 'textfield',
      '#description' => $this->t('Text that will display when search results are loading.'),
      '#default_value' => $json_object['interface']['display']['options']['loadingText'] ?? $this->t('Loading'),
      '#access' => FALSE,
    ];

    $element['tabs']['advanced']['display']['options']['errorText'] = [
      '#title' => $this->t('Error message'),
      '#type' => 'textfield',
      '#description' => $this->t('Text to display when a search error occurs.'),
      '#default_value' => $json_object['interface']['display']['options']['errorText'] ?? $this->t("Search isn't working right now. Try again later."),
      '#access' => FALSE,
    ];

    $element['tabs']['advanced']['display']['sort'] = [
      '#type' => 'details',
      '#title' => $this->t('Exposed sort options'),
      '#open' => FALSE,
      '#collapsible' => TRUE,
      '#access' => FALSE,
    ];

    $internal_sort_options = [NULL => $this->t('Relevance')];
    $date_fields = $this->indexHelper->getIndexDateFields($this->index);
    if (!empty($date_fields)) {
      $internal_sort_options += $date_fields;
    }
    $string_fields = $this->indexHelper->getIndexStringFields($this->index);
    if (!empty($string_fields)) {
      $internal_sort_options += $string_fields;
    }
    $element['tabs']['advanced']['display']['sort']['criteria'] = [
      '#type' => 'select',
      '#title' => $this->t('Select sort criteria'),
      '#options' => $internal_sort_options,
    ];
    $field_name = $this->fieldDefinition->getName();
    $element['tabs']['advanced']['display']['sort']['add_more'] = [
      '#type' => 'submit',
      '#name' => 'display_sort_criteria_add_more',
      '#value' => $this->t('Add'),
      '#attributes' => ['class' => ['field-add-more-submit']],
      '#limit_validation_errors' => [array_merge($form['#parents'], [$field_name])],
      '#submit' => [[$this, 'addSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addAjax'],
        'wrapper' => 'display-sort-elements',
        'effect' => 'fade',
      ],
    ];
    $element['tabs']['advanced']['display']['sort']['elements'] = [
      '#type' => 'table',
      '#empty' => $this->t('No sort values set.'),
      '#header' => [
        $this->t('Field'),
        $this->t('Name'),
        $this->t('Direction'),
      ],
      '#prefix' => '<div id="display-sort-elements">',
      '#suffix' => '</div>',
    ];
    if (!empty($json_object['interface']['display']['options']['sort']['values'])) {
      $element['tabs']['advanced']['display']['sort']['#open'] = TRUE;
      foreach ($json_object['interface']['display']['options']['sort']['values'] as $key => $sort_element) {
        $value = !empty($sort_element['value']) ? reset($sort_element['value']) : [];
        $sort_field = [];
        $sort_field['field'] = [
          '#type' => 'textfield',
          '#default_value' => $value['field'] ?? NULL,
          '#disabled' => TRUE,
        ];
        $sort_field['name'] = [
          '#type' => 'textfield',
          '#default_value' => $sort_element['name'] ?? '',
        ];
        $sort_field['direction'] = [
          '#type' => 'select',
          '#default_value' => $value['direction'] ?? 'asc',
          '#options' => [
            'asc' => $this->t('Ascending'),
            'desc' => $this->t('Descending'),
          ],
        ];
        $element['tabs']['advanced']['display']['sort']['elements'][] = $sort_field;
      }
    }
    $element_add = $form_state->getValue('element_add') ?? FALSE;
    if ($element_add) {
      $input = $form_state->getValues();
      $field_name = $this->fieldDefinition->getName();
      $parents = $form['#parents'];
      $base_key = [
        $field_name,
        0,
      ];
      $input = $form_state->getValue(array_merge($parents, $base_key));
      $sort_field = [];
      $sort_field['field'] = [
        '#type' => 'textfield',
        '#default_value' => $input['tabs']['advanced']['display']['sort']['criteria'] ?? NULL,
        '#disabled' => TRUE,
      ];
      $sort_field['name'] = [
        '#type' => 'textfield',
      ];
      $sort_field['direction'] = [
        '#type' => 'select',
        '#options' => [
          'asc' => $this->t('Ascending'),
          'desc' => $this->t('Descending'),
        ],
      ];
      $element['tabs']['advanced']['display']['sort']['elements'][] = $sort_field;
      $form_state->setValue('element_add', FALSE);
    }
    $element['#attached']['library'][] = 'tide_content_collection/content_collection_ui_widget';

  }

  /**
   * Submit handler for the Add button.
   */
  public function addSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setValue('element_add', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the Add button.
   */
  public function addAjax(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    // Go one level up in the form, to the widgets container.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    return $element['elements'];

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
   * Get the element name for Form States API.
   *
   * @param string $element_name
   *   The name of the element.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param int $delta
   *   Delta.
   * @param array $element
   *   The element.
   *
   * @return string
   *   The final element name.
   */
  protected function getFormStatesElementName($element_name, FieldItemListInterface $items, $delta, array $element) {
    $name = '';
    foreach ($element['#field_parents'] as $index => $parent) {
      $name .= $index ? ('[' . $parent . ']') : $parent;
    }
    $name .= '[' . $items->getName() . ']';
    $name .= '[' . $delta . ']';
    foreach (explode('|', $element_name) as $path) {
      $name .= '[' . $path . ']';
    }
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    $settings = $this->getSettings();
    foreach ($values as $delta => &$value) {
      $config = [];
      $config['title'] = $value['title'] ?? '';
      if (!empty($value['description']) && is_array($value['description'])) {
        $config['description'] = $value['description']['value'] ?? '';
      }
      else {
        $config['description'] = $value['description'] ?? '';
      }
      $config['callToAction']['text'] = $value['callToAction']['text'] ?? '';
      $config['callToAction']['url'] = $value['callToAction']['url'] ?? '';
      if (!$settings['content']['internal']['contentTypes']['enabled'] && !empty($settings['content']['internal']['contentTypes']['default_values'])) {
        $config['internal']['contentTypes'] = $settings['content']['internal']['contentTypes']['default_values'] ? array_values(array_filter($settings['content']['internal']['contentTypes']['default_values'])) : [];
      }
      elseif (!empty($value['tabs']['content']['contentTypes'])) {
        $config['internal']['contentTypes'] = $value['tabs']['content']['contentTypes'] ? array_values(array_filter($value['tabs']['content']['contentTypes'])) : [];
      }
      if (!$settings['content']['internal']['field_topic']['enabled'] && !empty($settings['content']['internal']['field_topic']['default_values'])) {
        $value['tabs']['content']['field_topic_wrapper']['field_topic'] = $settings['content']['internal']['field_topic']['default_values'] ?? [];
      }
      if (!empty($value['tabs']['content']['field_topic_wrapper']['field_topic'])) {
        foreach ($value['tabs']['content']['field_topic_wrapper']['field_topic'] as $index => $reference) {
          if (!empty($reference['target_id'])) {
            $config['internal']['contentFields']['field_topic']['values'][] = (int) $reference['target_id'];
          }
        }
        $config['internal']['contentFields']['field_topic']['operator'] = $value['tabs']['content']['field_topic_wrapper']['operator'] ?? 'OR';
      }
      if (!$settings['content']['internal']['field_tags']['enabled'] && !empty($settings['content']['internal']['field_tags']['default_values'])) {
        $value['tabs']['content']['field_tags_wrapper']['field_tags'] = $settings['content']['internal']['field_tags']['default_values'] ?? [];
      }
      if (!empty($value['tabs']['content']['field_tags_wrapper']['field_tags'])) {
        foreach ($value['tabs']['content']['field_tags_wrapper']['field_tags'] as $index => $reference) {
          if (!empty($reference['target_id'])) {
            $config['internal']['contentFields']['field_tags']['values'][] = (int) $reference['target_id'];
          }
        }
        $config['internal']['contentFields']['field_tags']['operator'] = $value['tabs']['content']['field_tags_wrapper']['operator'] ?? 'OR';
      }

      $entity_reference_fields = $this->getEntityReferenceFields();
      foreach ($value['tabs']['content']['advanced_filters'] as $wrapper_id => $wrapper) {
        $field_id = str_replace('_wrapper', '', $wrapper_id);
        if (!empty($settings['content']['internal'][$field_id]) && !$settings['content']['internal'][$field_id]['enabled'] && !empty($settings['content']['internal'][$field_id]['default_values'])) {
          $wrapper[$field_id] = $settings['content']['internal'][$field_id]['default_values'] ?? [];
        }
        if (!empty($wrapper[$field_id])) {
          // Entity reference fields.
          if (isset($entity_reference_fields[$field_id])) {
            foreach ($wrapper[$field_id] as $index => $reference) {
              if (!empty($reference['target_id'])) {
                $config['internal']['contentFields'][$field_id]['values'][] = (int) $reference['target_id'];
              }
            }
            unset($entity_reference_fields[$field_id]);
          }
          // Internal Extra fields.
          else {
            $config['internal']['contentFields'][$field_id]['values'] = is_array($wrapper[$field_id]) ? array_values(array_filter($wrapper[$field_id])) : [$wrapper[$field_id]];
            $config['internal']['contentFields'][$field_id]['values'] = array_filter($config['internal']['contentFields'][$field_id]['values']);
          }

          if (!empty($wrapper['operator'])) {
            $config['internal']['contentFields'][$field_id]['operator'] = $wrapper['operator'];
          }

          if (empty($config['internal']['contentFields'][$field_id]['values'])) {
            unset($config['internal']['contentFields'][$field_id]);
          }
        }
      }

      if (!empty($entity_reference_fields)) {
        foreach ($entity_reference_fields as $field_id => $field_label) {
          if (!empty($settings['content']['internal'][$field_id]['enabled']) && !empty($settings['content']['internal'][$field_id]['default_values'])) {
            foreach ($settings['content']['internal'][$field_id]['default_values'] as $reference) {
              if (!empty($reference['target_id'])) {
                $config['internal']['contentFields'][$field_id]['values'][] = (int) $reference['target_id'];
              }
            }
          }
        }
      }

      // Date Filters.
      if (!empty($value['tabs']['content']['show_dateFilter']) && $value['tabs']['content']['show_dateFilter']) {
        if (!empty($value['tabs']['content']['dateFilter']['criteria'])) {
          if (!empty($value['tabs']['content']['dateFilter']['startDateField']) || !empty($value['tabs']['content']['dateFilter']['endDateField'])) {
            $config['internal']['dateFilter']['criteria'] = $value['tabs']['content']['dateFilter']['criteria'] ?? '';
            if ($value['tabs']['content']['dateFilter']['criteria'] == 'range') {
              $date_range_start = $value['tabs']['content']['dateFilter']['dateRange']['dateRangeStart'] ?? '';
              if ($date_range_start instanceof DrupalDateTime) {
                $config['internal']['dateFilter']['dateRangeStart'] = $date_range_start->format('c');
              }
              $date_range_end = $value['tabs']['content']['dateFilter']['dateRange']['dateRangeEnd'] ?? '';
              if ($date_range_end instanceof DrupalDateTime) {
                $config['internal']['dateFilter']['dateRangeEnd'] = $date_range_end->format('c');
              }
            }
          }
        }

        if (!empty($value['tabs']['content']['dateFilter']['startDateField'])) {
          $config['internal']['dateFilter']['startDateField'] = $value['tabs']['content']['dateFilter']['startDateField'] ?? '';
        }

        if (!empty($value['tabs']['content']['dateFilter']['endDateField'])) {
          $config['internal']['dateFilter']['endDateField'] = $value['tabs']['content']['dateFilter']['endDateField'] ?? '';
        }
      }

      // Display Layout.
      $config['interface']['display']['type'] = 'grid';
      // Required field Type.
      $config['interface']['display']['resultComponent']['type'] = $value['tabs']['layout']['display']['type'] ?? 'card';
      if ($config['interface']['display']['resultComponent']['type'] == 'card') {
        $config['interface']['display']['resultComponent']['style'] = $value['tabs']['layout']['display']['resultComponent']['style'] ?? 'thumbnail';
        $config['interface']['display']['options']['itemsToLoad']['type'] = 'field';
        $config['interface']['display']['options']['itemsToLoad']['values'] = [
          'name' => $value['tabs']['layout']['display']['card_number'] ?? '3',
          'value' => (int) $value['tabs']['layout']['display']['card_number'] ?? 3,
        ];
      }

      $internal_sort = [];
      if (!empty($value['tabs']['layout']['internal']['sort']['field'])) {
        $internal_sort['field'] = $value['tabs']['layout']['internal']['sort']['field'] ?? '';
      }

      if (!empty($value['tabs']['layout']['internal']['sort']['direction'])) {
        $internal_sort['direction'] = $value['tabs']['layout']['internal']['sort']['direction'] ?? '';
      }

      if (!empty($internal_sort)) {
        $config['internal']['sort'][] = $internal_sort;
      }

      // Filters Layout.
      if (!empty($value['tabs']['filters']['show_interface_filters']) && $value['tabs']['filters']['show_interface_filters']) {
        if (!empty($value['tabs']['filters']['interface_filters']['keyword']['allow_keyword_search']) && $value['tabs']['filters']['interface_filters']['keyword']['allow_keyword_search']) {
          // Required field Type.
          $config['interface']['keyword']['type'] = 'basic';
          $config['interface']['keyword']['label'] = $value['tabs']['filters']['interface_filters']['keyword']['label'] ?? '';
          $config['interface']['keyword']['placeholder'] = $value['tabs']['filters']['interface_filters']['keyword']['placeholder'] ?? '';
          if (!empty($settings['filters']['enable_keyword_selection']) && $settings['filters']['enable_keyword_selection']) {
            // Retrieve only the enabled values and keys from checkboxes.
            $config['interface']['keyword']['fields'] = $value['tabs']['filters']['interface_filters']['keyword']['fields'] ? array_values(array_filter($value['tabs']['filters']['interface_filters']['keyword']['fields'])) : [];
          }
          else {
            $config['interface']['keyword']['fields'] = [
              'title',
              'field_landing_page_summary',
            ];
          }
        }
        if (!empty($value['tabs']['filters']['interface_filters']['advanced_filters']['items'])) {
          $advanced_filters = array_keys($value['tabs']['filters']['interface_filters']['advanced_filters']['items']);
          if (!empty($advanced_filters)) {
            $sorted_advanced_filters = [];
            foreach ($advanced_filters as $field_id) {
              $sorted_advanced_filters[$field_id] = $value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id][$field_id . '_weight'] ?? 0;
            }
            asort($sorted_advanced_filters);
            $sorted_advanced_filters = array_keys($sorted_advanced_filters);
            $validated_entity_reference_fields = $this->getValidatedIndexEntityReferenceFields();
            foreach ($advanced_filters as $field_id) {
              if (!empty($validated_entity_reference_fields[$field_id])) {
                $referenced_field = $this->indexHelper->getEntityReferenceFieldInfo($this->index, $field_id);
                if (!empty($value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details']['allow']) && $value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details']['allow']) {
                  $field = [];
                  // Required field Type.
                  $field['type'] = "basic";
                  $field['elasticsearch-field'] = $field_id;
                  // Required field VFG: Model.
                  $field['options']['model'] = $field_id;
                  // Required field VFG: Type.
                  $field['options']['type'] = 'rplselect';
                  $field['options']['label'] = $value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_label'] ?? '';
                  $field['options']['placeholder'] = $value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_placeholder'] ?? '';
                  if (!empty($value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options'])) {
                    foreach ($value['tabs']['filters']['interface_filters']['advanced_filters']['items'][$field_id]['details'][$field_id . '_options'] as $index => $reference) {
                      if (!empty($reference['target_id'])) {
                        $target_id = (int) $reference['target_id'];
                        $entity = $this->entityTypeManager->getStorage($referenced_field['target_type'])->load($target_id);
                        if (!empty($entity)) {
                          $field['options']['values'][] = [
                            'id' => $target_id,
                            'name' => $entity->label(),
                          ];
                        }
                      }
                    }
                  }
                  else {
                    // No options set use ES Aggregation to show all options.
                    $field['elasticsearch-aggregation'] = TRUE;
                    $field['elasticsearch-field'] = $field_id . '_name';
                    $field['options']['model'] = $field_id . '_name';
                  }
                  $config['interface']['filters']['fields'][] = $field;
                }
              }
            }
            if (!empty($config['interface']['filters']['fields']) || !empty($config['interface']['keyword'])) {
              // Enable automatic column arrangement of advanced search filters.
              $config['interface']['filters']['defaultStyling'] = TRUE;
              // Enable Submit and clearForm.
              $config['interface']['filters']['submit'] = [
                'visibility' => 'visible',
                'label' => $this->t('Apply search filters'),
              ];
              $config['interface']['filters']['clearForm'] = [
                'visibility' => 'visible',
                'label' => $this->t('Clear search filters'),
              ];
            }
          }
        }
      }

      // Advanced sort.
      if (!empty($value['tabs']['advanced']['display']['sort']['elements'])) {
        foreach ($value['tabs']['advanced']['display']['sort']['elements'] as $element) {
          if (!empty($element['name'])) {
            $sort_value = [];
            $sort_value['name'] = $element['name'];
            $sort_value['value'] = NULL;
            if (!empty($element['field'])) {
              $sort_value['value'][] = [
                'field' => $element['field'] ?? NULL,
                'direction' => $element['direction'] ?? 'asc',
              ];
            }
            $config['interface']['display']['options']['sort']['values'][] = $sort_value;
          }
        }
      }
      if (!empty($config['interface']['display']['options']['sort']['values'])) {
        $config['interface']['display']['options']['sort']['type'] = 'field';
      }

      // Advanced Layout.
      if (!empty($value['tabs']['advanced']['display']['options']['enableResultsCountText']) && $value['tabs']['advanced']['display']['options']['enableResultsCountText']) {
        $config['interface']['display']['options']['resultsCountText'] = $value['tabs']['advanced']['display']['options']['resultsCountText'] ?? '';
      }
      $config['interface']['display']['options']['noResultsText'] = $value['tabs']['advanced']['display']['options']['noResultsText'] ?? '';
      $config['interface']['display']['options']['loadingText'] = $value['tabs']['advanced']['display']['options']['loadingText'] ?? '';
      $config['interface']['display']['options']['errorText'] = $value['tabs']['advanced']['display']['options']['errorText'] ?? '';
      // To enable pagination no ui feature yet.
      $config['interface']['display']['options']['pagination'] = ['type' => 'numbers'];

      $value['value'] = json_encode($config);
      $errors = $this->validateJson($value['value']);
      if (!empty($errors)) {
        $field_name = $this->fieldDefinition->getName();
        $form_state->setErrorByName($field_name, $this->t('JSON does not validate against the schema. Violations: @errors.', [
          '@errors' => implode(' - ', $errors),
        ]));
      }
    }
    return $values;
  }

  /**
   * Callback to validate the JSON.
   *
   * @param string $json
   *   The json string value.
   *
   * @return array
   *   The error array list if exists.
   */
  protected function validateJson(string $json) : array {
    $errors = [];
    if (!empty($json)) {
      $json_object = json_decode($json);
      if ($json_object === NULL) {
        $errors[] = $this->t('Invalid JSON.');
      }
      else {
        // Validate against the JSON Schema.
        $json_schema = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('schema');
        if (!empty($json_schema)) {
          $json_schema_object = json_decode($json_schema);
          $schema_storage = new SchemaStorage();
          $schema_storage->addSchema('file://content_collection_configuration_schema', $json_schema_object);
          $json_validator = new Validator(new Factory($schema_storage));
          $num_errors = $json_validator->validate($json_object, $json_schema_object);
          if ($num_errors) {
            foreach ($json_validator->getErrors() as $error) {
              $errors[] = $this->t('[@property] @message', [
                '@property' => $error['property'],
                '@message' => $error['message'],
              ]);
            }
          }
        }
      }
    }
    return $errors;
  }

  /**
   * Get all index entity reference fields along with name value fields.
   *
   * A UI helper function that populates the choose list with actual
   * names/titles instead of the referenced fields' ids.
   *
   * @return array|null
   *   The entity reference fields array, or NULL if the none have a name value.
   */
  protected function getValidatedIndexEntityReferenceFields() : ?array {
    // Retrieves all indexed string fields.
    $string_fields = $this->indexHelper->getIndexStringFields($this->index);
    // Retrieves all indexed entity referenced fields.
    $entity_reference_fields = $this->indexHelper->getIndexEntityReferenceFields($this->index);
    $validated_entity_reference_fields = [];
    if (!empty($entity_reference_fields) && !empty($string_fields)) {
      // Attempt to map to the associated string field,
      // by looping through the referenced fields.
      foreach ($entity_reference_fields as $field_id => $value) {
        if (!empty($string_fields[$field_id . '_name'])) {
          // Used to retrieve the indexed field property path.
          $field_property_path = $this->indexHelper->getIndexedFieldPropertyPath($this->index, $field_id . '_name');
          if ($field_property_path && strpos($field_property_path, $field_id . ':entity:') !== FALSE) {
            $validated_entity_reference_fields[$field_id] = $string_fields[$field_id . '_name'];
          }
        }
      }
    }
    return $validated_entity_reference_fields;
  }

}
