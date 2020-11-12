<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * View builder for Share Link Token entities.
 *
 * @package Drupal\tide_share_link\Entity
 */
class ShareLinkTokenViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface[] $entities */
    if (empty($entities)) {
      return;
    }

    parent::buildComponents($build, $entities, $displays, $view_mode);

    foreach ($entities as $id => $entity) {
      $bundle = $entity->bundle();
      $display = $displays[$bundle];

      if ($display->getComponent('api_info')) {
        $build[$id]['api_info'] = [
          '#lazy_builder' => [
            get_called_class() . '::renderApiInformation',
            [$entity->id()],
          ],
        ];
      }
    }
  }

  /**
   * Build the API Information for a share link token.
   *
   * @param int $entity_id
   *   The share link token ID to render API information.
   *
   * @return array
   *   The API Information render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function renderApiInformation($entity_id) {
    /** @var \Drupal\tide_share_link\Entity\ShareLinkTokenInterface $token */
    $token = \Drupal::entityTypeManager()->getStorage('share_link_token')->load($entity_id);
    if (!$token->isActive()) {
      return [];
    }
    $node = $token->getSharedNode();
    if (!$node) {
      return [];
    }

    $api_info = [
      '#theme' => 'details',
      '#title' => t('API Information'),
      '#attributes' => [
        'open' => TRUE,
      ],
      '#summary_attributes' => [],
      '#children' => [
        '#theme' => 'container',
        '#children' => [],
        '#has_parent' => TRUE,
      ],
    ];

    /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_repository */
    $resource_repository = \Drupal::service('jsonapi.resource_type.repository');

    // Build the Share Link Token endpoint.
    $share_link_token_resource = $resource_repository->get('share_link_token', 'share_link_token');
    $endpoint = Url::fromRoute('jsonapi.' . $share_link_token_resource->getTypeName() . '.individual', [
      'entity' => $token->getToken(),
      'node' => $node->id(),
    ], [
      'absolute' => TRUE,
      'attributes' => ['target' => 'blank'],
    ]);
    $api_info['#children']['#children']['endpoint'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="api-information-endpoint field--label-inline"><span class="field__label">{{ label }}</span> <span class="field__item">{{ endpoint }}</span></div>',
      '#context' => [
        'label' => t('Endpoint'),
        'endpoint' => Link::fromTextAndUrl($endpoint->toString(), $endpoint),
      ],
    ];

    // Build the JSON:API endpoint to retrieve the shared node.
    $node_resource = $resource_repository->get('node', $node->getType());
    $node_endpoint_options = [
      'absolute' => TRUE,
      'attributes' => ['target' => 'blank'],
    ];
    if (!$node->isDefaultRevision()) {
      $node_endpoint_options['query']['resourceVersion'] = 'id:' . $node->getLoadedRevisionId();
    }
    $node_endpoint = Url::fromRoute('jsonapi.' . $node_resource->getTypeName() . '.individual', [
      'entity' => $node->uuid(),
    ], $node_endpoint_options);
    $api_info['#children']['#children']['node_endpoint'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="api-information-jsonapi-call"><div class="field__label">{{ label }}</div><div class="field__item color-success"><p><code class="api-information-jsonapi-call-rest">GET {{ endpoint }} <br/> X-Share-Link-Token: {{ token }}</code></p></div></div>',
      '#context' => [
        'label' => t('Subsequent JSON:API call'),
        'endpoint' => Link::fromTextAndUrl($node_endpoint->toString(), $node_endpoint),
        'token' => $token->getToken(),
      ],
    ];

    return $api_info;
  }

}
