<?php

namespace Drupal\tide_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Serialization\Yaml;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Drupal\Component\Utility\Html;

/**
 * Decode YAML content.
 *
 * @ResourceFieldEnhancer(
 *   id = "yaml",
 *   label = @Translation("YAML"),
 *   description = @Translation("Decode YAML content.")
 * )
 */
class YamlEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $data = $this->processText($data);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return Yaml::encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'anyOf' => [
        ['type' => 'array'],
        ['type' => 'boolean'],
        ['type' => 'null'],
        ['type' => 'number'],
        ['type' => 'object'],
        ['type' => 'string'],
      ],
    ];
  }

  /**
   * Helper function to convert node urls to path alias.
   *
   * @param string $data
   *   Data from a webform.
   *
   * @return formattted data
   */
  public function processText($data) {
    $formatted_data = Yaml::decode($data);
    $processed_text = $formatted_data['processed_text']['#text'];
    if (strpos($processed_text, 'data-entity-type') !== FALSE && strpos($processed_text, 'data-entity-uuid') !== FALSE) {
      $dom = Html::load($processed_text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//a[@data-entity-type and @data-entity-uuid]') as $element) {
        /** @var \DOMElement $element */
        try {
          $entity_type = $element->getAttribute('data-entity-type');
          if ($entity_type == 'node') {
            $href = $element->getAttribute('href');
            $aliasByPath = \Drupal::service('path.alias_manager')->getAliasByPath($href);
            $alias_helper = \Drupal::service('tide_site.alias_storage_helper');
            $url = $alias_helper->getPathAliasWithoutSitePrefix(['alias' => $aliasByPath]);
            $element->setAttribute('href', $url);
          }
        }
        catch (\Exception $e) {
          watchdog_exception('YamlEnhancer_processText', $e);
        }
      }
      $formatted_data['processed_text']['#text'] = Html::serialize($dom);
    }
    return $formatted_data;
  }

}
