<?php

namespace Drupal\tide_api\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Serialization\Yaml;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

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
    return Yaml::decode($data);
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

}
