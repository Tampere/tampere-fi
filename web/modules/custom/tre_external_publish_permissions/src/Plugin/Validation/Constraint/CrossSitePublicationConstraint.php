<?php

namespace Drupal\tre_external_publish_permissions\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Plugin implementation of the 'cross_site_publication_constraint'.
 *
 * @Constraint(
 *   id = "cross_site_publication_constraint",
 *   label = @Translation("Cross site publication constraint", context = "Validation"),
 * )
 */
class CrossSitePublicationConstraint extends Constraint {

  /**
   * The error message to be shown.
   *
   * @var string
   */
  public $permissions = 'You do not have the required permissions to enable ' .
  'publishing to the external site: %value. Uncheck the item to proceed.';

}
