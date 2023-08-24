<?php

namespace Drupal\tide_content_collection\Plugin\Field\FieldWidget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementation of the 'content_collection_configuration_widget_raw' widget.
 *
 * @FieldWidget(
 *   id = "content_collection_configuration_raw",
 *   label = @Translation("Content Collection Configuration (Raw JSON)"),
 *   field_types = {
 *     "content_collection_configuration"
 *   }
 * )
 */
class ContentCollectionConfigurationWidgetRaw extends StringTextareaWidget {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ModuleHandlerInterface $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->moduleHandler = $module_handler;
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
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'schema_validation' => FALSE,
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
    return $element;
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

    return $element;
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
    $cc_json_validation = (isset(getenv()['CONTENT_COLLECTION_JSON_VALIDATION'])) ? getenv()['CONTENT_COLLECTION_JSON_VALIDATION'] : FALSE;
    $json = $element['#value'];
    if (!empty($json)) {
      $json_object = json_decode($json);
      if ($json_object === NULL) {
        $form_state->setError($element, t('Invalid JSON.'));
      }
      elseif ($this->getSetting('schema_validation') && $cc_json_validation == TRUE) {
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
