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
    $data = Yaml::decode($data);
    $markup_text = $data['markup']['#markup'];
    $processed_text = $data['processed_text']['#text'];

    if (!empty($processed_text)) {
      $data['processed_text']['#text'] = $this->processText($processed_text);
    }
    if (!empty($markup_text)) {
      $data['markup']['#markup'] = $this->processText($markup_text);
    }

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
   * @param string $text
   *   The string value of the field.
   *
   * @return string
   *   The processed text.
   */
  public function processText($text) {
    $result = $text;
    if (strpos($text, 'data-entity-type') !== FALSE && strpos($text, 'data-entity-uuid') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//a[@data-entity-type and @data-entity-uuid]') as $element) {
        /** @var \DOMElement $element */
        try {
          $entity_type = $element->getAttribute('data-entity-type');
          if ($entity_type == 'node') {
            $href = $element->getAttribute('href');
            $aliasByPath = \Drupal::service('path_alias.manager')->getAliasByPath($href);
            $alias_helper = \Drupal::service('tide_site.alias_storage_helper');
            $url = $alias_helper->getPathAliasWithoutSitePrefix(['alias' => $aliasByPath]);
            $element->setAttribute('href', $url);
          }
        }
        catch (\Exception $e) {
          watchdog_exception('YamlEnhancer_processText', $e);
        }
      }
      $result = Html::serialize($dom);
    }
    return $result;
  }

}
