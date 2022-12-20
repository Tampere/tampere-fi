<?php

namespace Drupal\tre_external_publish_permissions\Plugin\Validation\Constraint;

use Drupal\taxonomy\Entity\Term;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Permissions constraint.
 */
class CrossSitePublicationConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $current_user = \Drupal::currentUser()->id();

    foreach ($items as $term_item) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $term_item */
      if (!$this->hasPermissions($term_item->getString(), $current_user)) {
        // Current user does not have a permission to select the this particular
        // taxonomy term, generate a violation.
        $term_name = Term::load($term_item->getString())->getName() . " (term id: {$term_item->getString()})";
        $this->context->addViolation($constraint->permissions, ['%value' => $term_name]);
      }
    }
  }

  /**
   * Check if user is eglible to select a site to publish a news article to.
   *
   * @param string $site_id
   *   Taxonomy term id corresponding to the external site.
   * @param int $user_uid
   *   Uid of the user trying to save the value.
   *
   * @return bool
   *   Whether the user is eglible.
   */
  private function hasPermissions(string $site_id, int $user_uid): bool {
    $term = Term::load($site_id);
    $term_fields = $term->getFields();

    $user_ids_with_permissions_to_this_site = $term_fields["field_user_reference"]->getValue();

    $current_user_present = FALSE;
    foreach ($user_ids_with_permissions_to_this_site as $value) {
      if ($value["target_id"] == $user_uid) {
        $current_user_present = TRUE;
        break;
      }
    }
    return $current_user_present;
  }

}
