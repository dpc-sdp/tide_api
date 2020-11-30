<?php

namespace Drupal\tide_share_link;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Share Link Token entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class ShareLinkTokenHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();

    $settings_form_route = $this->getSettingsFormRoute($entity_type);
    if ($settings_form_route) {
      $collection->add("$entity_type_id.settings", $settings_form_route);
    }

    $node_collection_route = $this->getNodeCollectionRoute($entity_type);
    if ($node_collection_route) {
      $collection->add("entity.$entity_type_id.node_collection", $node_collection_route);
    }

    $revision_collection_route = $this->getRevisionCollectionRoute($entity_type);
    if ($revision_collection_route) {
      $collection->add("entity.$entity_type_id.revision_collection", $revision_collection_route);
    }

    return $collection;
  }

  /**
   * Gets the settings form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getSettingsFormRoute(EntityTypeInterface $entity_type) {
    if (!$entity_type->getBundleEntityType()) {
      $route = new Route("/admin/structure/{$entity_type->id()}/settings");
      $route
        ->setDefaults([
          '_form' => 'Drupal\tide_share_link\Form\ShareLinkTokenSettingsForm',
          '_title' => "{$entity_type->getLabel()} settings",
        ])
        ->setRequirement('_permission', $entity_type->getAdminPermission())
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCanonicalRoute($entity_type);
    if ($route) {
      $this->addNodeToRouteParameters($route);
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getEditFormRoute($entity_type);
    if ($route) {
      $route->setDefault('_title_callback', '_tide_share_link_get_edit_title');
      $this->addNodeToRouteParameters($route);
      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getDeleteFormRoute($entity_type);
    if ($route) {
      $this->addNodeToRouteParameters($route);
      return $route;
    }
  }

  /**
   * Gets the node collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getNodeCollectionRoute(EntityTypeInterface $entity_type) {
    // If the entity type does not provide an admin permission, there is no way
    // to control access, so we cannot provide a route in a sensible way.
    if ($entity_type->hasLinkTemplate('node-collection') && $entity_type->hasListBuilderClass()) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
      $label = $entity_type->getCollectionLabel();

      $route = new Route($entity_type->getLinkTemplate('node-collection'));
      $route
        ->addDefaults([
          '_entity_list' => $entity_type->id(),
          '_title' => $label->getUntranslatedString(),
          '_title_arguments' => $label->getArguments(),
          '_title_context' => $label->getOption('context'),
          '_title_callback' => '_tide_share_link_get_list_title',
        ])
        ->setRequirement('_permission', 'access share link token entities listing');
      $this->addNodeToRouteParameters($route);

      return $route;
    }
  }

  /**
   * Gets the node collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionCollectionRoute(EntityTypeInterface $entity_type) {
    // If the entity type does not provide an admin permission, there is no way
    // to control access, so we cannot provide a route in a sensible way.
    if ($entity_type->hasLinkTemplate('revision-collection') && $entity_type->hasListBuilderClass()) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
      $label = $entity_type->getCollectionLabel();

      $route = new Route($entity_type->getLinkTemplate('revision-collection'));
      $route
        ->addDefaults([
          '_entity_list' => $entity_type->id(),
          '_title' => $label->getUntranslatedString(),
          '_title_arguments' => $label->getArguments(),
          '_title_context' => $label->getOption('context'),
          '_title_callback' => '_tide_share_link_get_list_title',
        ])
        ->setRequirement('_permission', 'access share link token entities listing');
      $this->addNodeToRouteParameters($route);

      return $route;
    }
  }

  /**
   * Add node to route params.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The modified route.
   */
  protected function addNodeToRouteParameters(Route $route) {
    $parameters = $route->getOption('parameters');
    if (!isset($parameters['node'])) {
      $parameters['node'] = [
        'type' => 'entity:node',
      ];
      $route->setOption('parameters', $parameters);
    }
    return $route;
  }

}
