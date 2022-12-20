<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Involvement opportunity card paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__involvement_opportunity_card",
 *   hook = "paragraph__involvement_opportunity_card"
 * )
 */
class InvolvementOpportunityCard extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the Involvement opportunity card.
   */
  const CARD_VIEW_MODE = 'involvement_opportunity_card_view_mode';

  /**
   * The machine name for the field that holds names for fields to display.
   */
  const DISPLAYED_FIELDS_FIELD_NAME = 'field_displayed_opp_fields';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_card_paragraph */
    $translated_card_paragraph = $this->entityRepository->getTranslationFromContext($paragraph);
    if (!$translated_card_paragraph->hasField('field_involvement_opportunity') || $translated_card_paragraph->get('field_involvement_opportunity')->isEmpty()) {
      return $variables;
    }

    $referenced_entities = $translated_card_paragraph->field_involvement_opportunity->referencedEntities();
    $involvement_opportunity_node = reset($referenced_entities);
    if (!($involvement_opportunity_node instanceof NodeInterface)) {
      return $variables;
    }

    /** @var \Drupal\node\NodeInterface $translated_involvement_opportunity_node */
    $translated_involvement_opportunity_node = $this->entityRepository->getTranslationFromContext($involvement_opportunity_node);

    $involvement_opportunity_node_id = $translated_involvement_opportunity_node->id();
    $variables['#cache']['tags'][] = "node:{$involvement_opportunity_node_id}";

    $node_is_published = $translated_involvement_opportunity_node->isPublished();
    if (!$node_is_published) {
      return $variables;
    }

    $node_has_participate_phase_field = $translated_involvement_opportunity_node->hasField('field_participate_phase');
    $node_participate_phase_not_empty = !$translated_involvement_opportunity_node->get('field_participate_phase')->isEmpty();
    if ($node_has_participate_phase_field && $node_participate_phase_not_empty) {
      $label = $translated_involvement_opportunity_node->field_participate_phase->getSetting('allowed_values')[$translated_involvement_opportunity_node->field_participate_phase->value];
      $variables['involvement_opportunity_phase'] = $label;
    }

    $node_has_participate_type_field = $translated_involvement_opportunity_node->hasField('field_participate_type');
    $node_participate_type_not_empty = !$translated_involvement_opportunity_node->get('field_participate_type')->isEmpty();
    if ($node_has_participate_type_field && $node_participate_type_not_empty) {
      $label = $translated_involvement_opportunity_node->field_participate_type->getSetting('allowed_values')[$translated_involvement_opportunity_node->field_participate_type->value];
      $variables['involvement_opportunity_type'] = $label;
    }

    $node_start_time_date = $this->helperFunctions->getNodeFieldDate($translated_involvement_opportunity_node, 'field_start_time');
    $node_end_time_date = $this->helperFunctions->getNodeFieldDate($translated_involvement_opportunity_node, 'field_end_time');

    if (!empty($node_start_time_date)) {
      $formatted_date_string = $this->helperFunctions->getFormattedInvolvementOpportunityDate($node_start_time_date, $node_end_time_date);
      $variables['involvement_opportunity_date'] = $formatted_date_string;
    }

    $node_has_sign_up_link_field = $translated_involvement_opportunity_node->hasField('field_sign_up_link');
    $node_sign_up_link_not_empty = !$translated_involvement_opportunity_node->get('field_sign_up_link')->isEmpty();
    if ($node_has_sign_up_link_field && $node_sign_up_link_not_empty) {
      $variables['involvement_opportunity_sign_up_link'] = $translated_involvement_opportunity_node->get('field_sign_up_link')->view(self::CARD_VIEW_MODE);
    }

    $paragraph_container_paragraph = $paragraph->getParentEntity();
    if ($paragraph_container_paragraph instanceof ParagraphInterface) {
      $paragraph_parent_node_id = $paragraph_container_paragraph->getParentEntity()->id();

      if ($paragraph_parent_node_id != $involvement_opportunity_node_id) {
        $variables['involvement_opportunity_node_url'] = $translated_involvement_opportunity_node->toUrl()->toString(TRUE)->getGeneratedUrl();
      }
    }

    // Some fields from the referenced node are rendered only if they
    // have been selected for display in the paragraph.
    if (!$translated_card_paragraph->hasField(self::DISPLAYED_FIELDS_FIELD_NAME) || $translated_card_paragraph->get(self::DISPLAYED_FIELDS_FIELD_NAME)->isEmpty()) {
      return $variables;
    }

    $displayed_fields_field_values = $translated_card_paragraph->get(self::DISPLAYED_FIELDS_FIELD_NAME)->getValue();
    $fields_selected_for_display = [];
    foreach ($displayed_fields_field_values as $key => $field_value) {
      $fields_selected_for_display[$key] = $field_value['value'];
    }

    if (in_array('name', $fields_selected_for_display, TRUE)) {
      $variables['involvement_opportunity_title'] = $translated_involvement_opportunity_node->getTitle();
    }

    if (in_array('sign_up_information', $fields_selected_for_display, TRUE)) {
      $variables['involvement_opportunity_sign_up_information'] = $this->entityTypeManager->getViewBuilder('node')->view($translated_involvement_opportunity_node, 'involvement_opp_sign_up_information_view_mode');
    }

    if (in_array('participate_summary', $fields_selected_for_display, TRUE)) {
      $node_has_summary_field = $translated_involvement_opportunity_node->hasField('field_participate_summary');
      $node_summary_not_empty = !$translated_involvement_opportunity_node->get('field_participate_summary')->isEmpty();
      if ($node_has_summary_field && $node_summary_not_empty) {
        $variables['involvement_opportunity_lead_paragraph'] = $translated_involvement_opportunity_node->get('field_participate_summary')->getString();
      }
    }

    if (in_array('address_street', $fields_selected_for_display, TRUE)) {
      $node_has_street_address_field = $translated_involvement_opportunity_node->hasField('field_address_street');
      $node_street_address_not_empty = !$translated_involvement_opportunity_node->get('field_address_street')->isEmpty();
      if ($node_has_street_address_field && $node_street_address_not_empty) {
        $variables['involvement_opportunity_street_address'] = $translated_involvement_opportunity_node->get('field_address_street')->view(self::CARD_VIEW_MODE);
      }
    }

    if (in_array('link_single', $fields_selected_for_display, TRUE)) {
      $node_has_website_field = $translated_involvement_opportunity_node->hasField('field_link_single');
      $node_website_not_empty = !$translated_involvement_opportunity_node->get('field_link_single')->isEmpty();
      if ($node_has_website_field && $node_website_not_empty) {
        $variables['involvement_opportunity_website'] = $translated_involvement_opportunity_node->get('field_link_single')->view(self::CARD_VIEW_MODE);
      }
    }

    if (in_array('additional_single_link', $fields_selected_for_display, TRUE)) {
      $node_has_additional_link_field = $translated_involvement_opportunity_node->hasField('field_single_additional_link');
      $node_additional_link_not_empty = !$translated_involvement_opportunity_node->get('field_single_additional_link')->isEmpty();
      if ($node_has_additional_link_field && $node_additional_link_not_empty) {
        $variables['involvement_opportunity_additional_link'] = $translated_involvement_opportunity_node->get('field_single_additional_link')->view(self::CARD_VIEW_MODE);
      }
    }

    if (in_array('location', $fields_selected_for_display, TRUE)) {
      $involvement_opportunity_location_coordinates = $this->helperFunctions->getNodeLiftupLocation($translated_involvement_opportunity_node);
      if (!empty($involvement_opportunity_location_coordinates)) {
        $variables['involvement_opportunity_map'] = $this->helperFunctions->getNodeLiftupMap($translated_involvement_opportunity_node, 'liftup-map');
        $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$involvement_opportunity_node_id] = $involvement_opportunity_location_coordinates;
        $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
      }
    }

    return $variables;
  }

}
