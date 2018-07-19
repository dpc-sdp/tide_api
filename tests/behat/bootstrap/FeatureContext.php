<?php

/**
 * @file
 * MYSITE Drupal context for Behat testing.
 */

use Drupal\DrupalExtension\Context\DrupalContext;
use IntegratedExperts\BehatSteps\FieldTrait;
use IntegratedExperts\BehatSteps\PathTrait;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends DrupalContext {

  use PathTrait;
  use FieldTrait;

}
