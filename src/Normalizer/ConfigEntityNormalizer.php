<?php

namespace Drupal\tide_api\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\Normalizer\ConfigEntityNormalizer as JsonapiExtrasConfigEntityNormalizer;

/**
 * Override ConfigEntityNormalizer to to add alter hook.
 */
class ConfigEntityNormalizer extends JsonapiExtrasConfigEntityNormalizer {

  /**
   * The Module Handle service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(LinkManager $link_manager, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($link_manager, $resource_type_repository, $entity_type_manager);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\serialization\Normalizer\EntityNormalizer::normalize()
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // If the fields to use were specified, only output those field values.
    $context['resource_type'] = $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $resource_type_name = $resource_type->getTypeName();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    $bundle = $resource_type->getBundle();
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = $this->getFieldNames($entity, $bundle, $resource_type);
    }
    /* @var \Drupal\jsonapi\Normalizer\Value[] $normalizer_values */
    $normalizer_values = [];
    foreach ($this->getFields($entity, $bundle, $resource_type) as $field_name => $field) {
      if (!in_array($field_name, $field_names)) {
        continue;
      }
      $normalized_field = $this->serializeField($field, $context, $format);
      assert($normalized_field instanceof FieldNormalizerValueInterface);
      $normalizer_values[$field_name] = $normalized_field;
    }

    $context2 = [
      'entity' => $entity,
      'format' => $format,
    ];
    $this->moduleHandler->alter('config_entity_normalizer_values', $normalizer_values, $context, $context2);

    $link_context = ['link_manager' => $this->linkManager];

    return new EntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
  }

}
