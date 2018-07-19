<?php

/**
 * Trait TideTrait.
 */
trait TideCommonTrait {

  /**
   * @Then I am in the :path path
   */
  public function assertCurrentPath($path) {
    $current_path = $this->getSession()->getCurrentUrl();
    $current_path = parse_url($current_path, PHP_URL_PATH);
    $current_path = ltrim($current_path, '/');
    $current_path = $current_path == '' ? '<front>' : $current_path;

    if ($current_path != $path) {
      throw new \Exception(sprintf('Current path is "%s", but expected is "%s"', $current_path, $path));
    }
  }

  /**
   * Creates and authenticates a user with the given role(s).
   *
   * @Given I am logged in as a user with the :role role(s)
   * @Given I am logged in as a/an :role
   */
  public function assertAuthenticatedByRole($role) {
    // Override parent assertion to allow using 'anonymous user' role without
    // actually creating a user with role. By default,
    // assertAuthenticatedByRole() will create a user with 'authenticated role'
    // even if 'anonymous user' role is provided.
    if ($role == 'anonymous user') {
      if (!empty($this->loggedInUser)) {
        $this->logout();
      }
    }
    else {
      parent::assertAuthenticatedByRole($role);
    }
  }

  /**
   * @Then I wait for :sec second(s)
   */
  public function waitForSeconds($sec) {
    sleep($sec);
  }

  /**
   * @Given no :type content type
   */
  public function removeContentType($type) {
    $content_type_entity = \Drupal::entityManager()->getStorage('node_type')->load($type);
    if ($content_type_entity) {
      $content_type_entity->delete();
    }
  }

}
