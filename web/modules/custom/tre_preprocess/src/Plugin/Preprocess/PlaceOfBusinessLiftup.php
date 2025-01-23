<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Place of business liftup paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__place_of_business_liftup",
 *   hook = "paragraph__place_of_business_liftup"
 * )
 */
class PlaceOfBusinessLiftup extends TrePreProcessPluginBase {

  /**
   * The view mode to use for the Person liftup.
   */
  const LIFTUP_VIEW_MODE = 'contact_information_liftup_view_mode';

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if ($paragraph instanceof ParagraphInterface) {
      /** @var \Drupal\paragraphs\ParagraphInterface $translated_pob_liftup */
      $translated_pob_liftup = $this->entityRepository->getTranslationFromContext($paragraph);

      $parent = $paragraph->getParentEntity();
      if ($parent instanceof ParagraphInterface) {
        $grand_parent_entity = $parent->getParentEntity();

        if ($grand_parent_entity instanceof ParagraphInterface) {
          $bundle = $grand_parent_entity->bundle();
          $variables['inside_accordion'] = $bundle == 'accordion_item' || $bundle == 'process_accordion_item';
        }
      }

      if (!$translated_pob_liftup->get('field_place_of_business')->isEmpty()) {
        $referenced_entities = $translated_pob_liftup->get('field_place_of_business')->referencedEntities();
        $pob_node = reset($referenced_entities);

        if ($pob_node instanceof NodeInterface) {
          /** @var \Drupal\node\NodeInterface $translated_pob_node */
          $translated_pob_node = $this->entityRepository->getTranslationFromContext($pob_node);

          $node_id = $translated_pob_node->id();
          $variables['#cache']['tags'][] = "node:{$node_id}";

          $node_is_published = $translated_pob_node->isPublished();
          if (!$node_is_published) {
            return $variables;
          }

          $displayed_fields_field_values = $translated_pob_liftup->get('field_pob_displayed_fields')->getValue();
          $fields_selected_for_display = $this->helperFunctions->getListFieldValues($displayed_fields_field_values);

          if (in_array('name', $fields_selected_for_display, TRUE)) {
            $variables['place_of_business_title'] = $translated_pob_node->label();
            $display_as_link = $this->helperFunctions->getFieldValueString($translated_pob_liftup, 'field_link_to_pob_content');
            if (!$translated_pob_node->get('field_links')->isEmpty() && $display_as_link == HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
              $variables['place_of_business_url'] = $this->helperFunctions->getExternalLinkFieldUrl($translated_pob_node->get('field_links'));
            }
          }

          if (in_array('alternative_name', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_alternative_name')->isEmpty()) {
              $variables['place_of_business_alt_name'] = $translated_pob_node->get('field_alternative_name')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('organisation', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_hr_organizational_unit')->isEmpty()) {
              $variables['place_of_business_organisation'] = $translated_pob_node->get('field_hr_organizational_unit')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('summary', $fields_selected_for_display, TRUE)) {
            $variables['place_of_business_summary'] = $this->helperFunctions->getFieldValueString($translated_pob_node, 'field_summary');
          }

          if (in_array('description', $fields_selected_for_display, TRUE)) {
            $variables['place_of_business_description'] = $translated_pob_node->get('field_body_md')->view(self::LIFTUP_VIEW_MODE);
          }

          if (in_array('address', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_addresses')->isEmpty()) {
              $variables['place_of_business_addresses'] = $translated_pob_node->get('field_addresses')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('postaddress', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_address_postal')->isEmpty()) {
              $variables['place_of_business_postaddress'] = $translated_pob_node->get('field_address_postal')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('region', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_geographical_areas')->isEmpty()) {
              $variables['place_of_business_region'] = $translated_pob_node->get('field_geographical_areas')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('links', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_links')->isEmpty()) {
              $variables['place_of_business_links'] = $translated_pob_node->get('field_links')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('phone', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_additional_phones')->isEmpty()) {
              $variables['place_of_business_phones'] = $translated_pob_node->get('field_additional_phones')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('email', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_email')->isEmpty()) {
              $variables['place_of_business_email'] = $translated_pob_node->get('field_email')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          $variables['right_column_content'] = [];

          if (in_array('opening_hours', $fields_selected_for_display, TRUE)) {
            $hours_field_name = 'field_opening_hours';
            $hours_info_field_name = 'field_opening_hours_info';
            $variables['right_column_content'][] = static::getOpeningHoursRendered($translated_pob_node, $hours_field_name, $hours_info_field_name);
          }

          if (in_array('opening_hours_2', $fields_selected_for_display, TRUE)) {
            $hours_field_name = 'field_opening_hours_2';
            $hours_info_field_name = 'field_opening_hours_info_2';
            $variables['right_column_content'][] = static::getOpeningHoursRendered($translated_pob_node, $hours_field_name, $hours_info_field_name);
          }

          if (in_array('opening_hours_3', $fields_selected_for_display, TRUE)) {
            $hours_field_name = 'field_opening_hours_3';
            $hours_info_field_name = 'field_opening_hours_info_3';
            $variables['right_column_content'][] = static::getOpeningHoursRendered($translated_pob_node, $hours_field_name, $hours_info_field_name);
          }

          if (in_array('exception_hours', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_exception_hours')->isEmpty()) {
              $variables['right_column_content'][] = $translated_pob_node->get('field_exception_hours')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          // Make sure the elements get ordered correctly by assigning a weight
          // to each of the rendered elements.
          foreach ($variables['right_column_content'] as $weight => $renderable) {
            $variables['right_column_content'][$weight]['#weight'] = $weight;
          }

          $variables['right_column_modifier'] = ['right'];

          if (in_array('additional_information', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_additional_information')->isEmpty()) {
              $variables['place_of_business_additional_information'] = $translated_pob_node->get('field_additional_information')->view(self::LIFTUP_VIEW_MODE);
            }
          }

          if (in_array('accessibility_information', $fields_selected_for_display, TRUE)) {
            if (!$translated_pob_node->get('field_accessibility_and_services')->isEmpty()) {
              $title_label = $translated_pob_node->field_accessibility_info_title->getSetting('allowed_values')[$translated_pob_node->field_accessibility_info_title->value];
              $variables['place_of_business_accessibility_title'] = $title_label;

              $taxonomies = $translated_pob_node->get('field_accessibility_and_services')->view('default');
              $variables['place_of_business_accessibility_taxonomies'] = $taxonomies;
            }
          }

          if (in_array('map', $fields_selected_for_display, TRUE)) {
            $place_of_business_locations = $this->getPlaceOfBusinessLocations($translated_pob_node);

            if (!empty($place_of_business_locations)) {
              $variables['place_of_business_map'] = $this->helperFunctions->getNodeLiftupMap($translated_pob_node, 'liftup-map');
              $variables['#attached']['drupalSettings']['tampere']['nodeLiftupLocations'][$node_id] = $place_of_business_locations;
              $variables['#attached']['drupalSettings']['tampere']['mmlMapIframeDomain'] = Settings::get('mml_map_iframe_domain');
            }
          }
        }
      }
    }

    return $variables;
  }

  /**
   * Returns an array of location coordinates for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get the locations for.
   *
   * @return array
   *   The location coordinates as an array when they exist,
   *   empty array otherwise.
   */
  protected function getPlaceOfBusinessLocations(NodeInterface $node) {
    $locations = [];

    if (!($node instanceof NodeInterface)) {
      return $locations;
    }

    if (!$node->hasField('field_addresses') || $node->get('field_addresses')->isEmpty()) {
      return $locations;
    }

    // The 'field_addresses' field holds references to 'map_point' content
    // that store information about locations.
    $location_nodes = $node->get('field_addresses')->referencedEntities();
    foreach ($location_nodes as $location_node) {
      if (!($location_node instanceof NodeInterface)) {
        continue;
      }

      $node_has_latitude_field_value = $location_node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->isEmpty();
      $node_has_longitude_field_value = $location_node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME) && !$location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->isEmpty();
      if ($node_has_latitude_field_value && $node_has_longitude_field_value) {
        $latitude = $location_node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->getString();
        $longitude = $location_node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->getString();
        $locations[] = [
          'latitude' => $latitude,
          'longitude' => $longitude,
        ];
      }
    }

    return $locations;
  }

  /**
   * Produces the render array for an opening_hours field in the node.
   *
   * The logic is to use a separate field's content as the label of the hours
   * field if the separate field has a value. If it doesn't, the label is hidden
   * altogether.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose opening hours fields are being rendered.
   * @param string $hours_field_name
   *   The name of the actual hours field, preferably of type office_hours.
   * @param string $hours_heading_field
   *   The name of the heading/info field to use as the label text.
   *
   * @return array
   *   The rendered result when the hours field has a value, empty array
   *   otherwise.
   */
  protected static function getOpeningHoursRendered(NodeInterface $node, string $hours_field_name, string $hours_heading_field) {
    $view = [];

    $heading_field_is_filled_in = $node->hasField($hours_heading_field) && !$node->get($hours_heading_field)->isEmpty();
    if (!$node->get($hours_field_name)->isEmpty()) {
      $view = $node->get($hours_field_name)
        ->view(self::LIFTUP_VIEW_MODE);

      // By default show no label.
      $opening_hours_label = '';

      // If there is a heading for the opening hours in PTV, replace the
      // empty label with that heading.
      if ($heading_field_is_filled_in) {
        $opening_hours_label = $node->get($hours_heading_field)->getString();
      }

      if (empty($opening_hours_label)) {
        $view['#label_display'] = 'hidden';
      }
      $view['#title'] = $opening_hours_label;

    }
    elseif ($heading_field_is_filled_in) {
      // The hard-coded classes are the same as field known as $hours_heading_field
      // would have on its output, were it rendered with field $hours_field_name.
      $view = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['field', 'field-name-field-opening-hours', 'field-label-above'],
        ],
        '#value' => nl2br($node->get($hours_heading_field)->getString()),
      ];
    }

    return $view;
  }

}
