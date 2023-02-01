<?php

namespace Drupal\tide_content_collection\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\BasicStringFormatter;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementation of the 'content_collection_configuration_raw' formatter.
 *
 * @FieldFormatter(
 *   id = "content_collection_configuration_raw",
 *   label = @Translation("Content Collection Configuration (Raw JSON)"),
 *   field_types = {
 *     "content_collection_configuration",
 *   }
 * )
 */
class ContentCollectionConfigurationFormatterRaw extends BasicStringFormatter {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ModuleHandlerInterface $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition,
      $settings, $label, $view_mode, $third_party_settings);
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      if ($this->getSetting('raw_json')) {
        // Use CodeMirror to display the JSON.
        if ($this->moduleHandler->moduleExists('webform')) {
          $elements[$delta] = [
            '#type' => 'webform_codemirror',
            '#mode' => 'javascript',
            '#skip_validation' => TRUE,
            '#value' => $item->value,
            '#readonly' => TRUE,
            '#attributes' => [
              'readonly' => TRUE,
              'disabled' => TRUE,
              'style' => 'max-height: 500px;',
            ],
          ];
        }
        else {
          $elements[$delta] = [
            '#type' => 'inline_template',
            '#template' => '<pre>{{ value }}</pre>',
            '#context' => ['value' => $item->value],
          ];
        }
      }
      else {
        $value = Json::decode($item->value);
        if ($value !== NULL) {
          $elements[$delta] = [
            '#type' => 'markup',
            '#markup' => '',
          ];
          if (!empty($value['title'])) {
            $title_label = $this->t('Title');
            $elements[$delta]['#markup'] .= '<div><strong>' . $title_label . '</strong>: ' . $value['title'] . '</div>';
          }
          if (!empty($value['description'])) {
            $desc_label = $this->t('Description');
            $elements[$delta]['#markup'] .= '<div><strong>' . $desc_label . '</strong>: ' . $value['description'] . '</div>';
          }
          if (!empty($value['callToAction']['url'])) {
            $cta_label = $this->t('Call-to-action');
            $elements[$delta]['#markup'] .= '<div><strong>' . $cta_label . '</strong>: ' . $value['callToAction']['url'] . '</div>';
          }
        }
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return ['raw_json' => FALSE] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['raw_json'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display raw JSON'),
      '#default_value' => $this->getSetting('raw_json'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if ($this->getSetting('raw_json')) {
      $summary[] = $this->t('Display raw JSON: YES');
    }
    return $summary;
  }

}
