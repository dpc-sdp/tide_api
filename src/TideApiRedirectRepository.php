<?php

namespace Drupal\tide_api;

use Drupal\redirect\RedirectRepository;
use Drupal\Core\Language\Language;
use Drupal\redirect\Exception\RedirectLoopException;

/**
 * Class TideApiRedirectRepository.
 *
 * @package Drupal\tide_api
 */
class TideApiRedirectRepository extends RedirectRepository {

  /**
   * Gets a redirect for given wildcard path, query and language.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param array $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   *
   * @return \Drupal\redirect\Entity\Redirect
   *   The matched redirect entity.
   *
   * @throws \Drupal\redirect\Exception\RedirectLoopException
   */
  public function findMatchingWildcardRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED) {
    $source_path = ltrim($source_path, '/');

    // Load redirects by hash. A direct query is used to improve performance.
    $rid = $this->connection->query('SELECT rid FROM {redirect} WHERE :source_path LIKE redirect_source__path ORDER BY LENGTH(redirect_source__query) DESC', [':source_path' => $source_path])->fetchField();

    if (!empty($rid)) {
      // Check if this is a loop.
      if (in_array($rid, $this->foundRedirects)) {
        throw new RedirectLoopException('/' . $source_path, $rid);
      }
      $this->foundRedirects[] = $rid;

      $redirect = $this->load($rid);

      // Find chained redirects.
      if ($recursive = $this->findByRedirect($redirect, $language)) {
        // Reset found redirects.
        $this->foundRedirects = [];
        return $recursive;
      }

      return $redirect;
    }

    return NULL;
  }

}
