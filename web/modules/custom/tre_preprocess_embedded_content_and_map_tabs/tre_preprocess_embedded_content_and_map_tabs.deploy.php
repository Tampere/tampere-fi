<?php

/**
 * @file
 * Deploy hook implementations for tre_preprocess_embedded_content_and_map_tabs.
 */

use Drupal\node\NodeInterface;

/**
 * Resaves all the nodes that have the listing_search_content field.
 */
function tre_preprocess_embedded_content_and_map_tabs_deploy_0001_resave_nodes(&$sandbox) {
  $content_types_has_listing_search_content_field = [
    'place',
    'place_of_business',
    'city_planning_and_constructions',
    'zoning_information',
  ];

  foreach ($content_types_has_listing_search_content_field as $content_type) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => $content_type,
      'status' => 1,
    ]);

    foreach ($nodes as $node) {
      $node->save();
    }
  }
}

/**
 * Updates listing paragraphs to use content type field instead of taxonomy.
 */
function tre_preprocess_embedded_content_and_map_tabs_deploy_0002_update_listing_paragraphs(&$sandbox) {
  $term_to_content_type_map = [
    'Asemakaava' => 'zoning_information',
    'Yleiskaava' => 'comprehensive_plan',
  ];

  /** @var \Drupal\node\NodeStorageInterface $storage */
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  $query = $storage->getQuery()->accessCheck(FALSE);
  $query->exists('field_paragraphs');

  $results = $query->execute();

  $updated = [];
  foreach ($results as $nid) {
    $node = $storage->load($nid);
    if (!($node instanceof NodeInterface)) {
      continue;
    }
    foreach ($node->getTranslationLanguages() as $node_lang) {
      foreach ($node->getTranslation($node_lang->getId())->get('field_paragraphs')->referencedEntities() as $paragraph) {
        if (!$paragraph->hasField('field_zoning_information_types')) {
          continue;
        }
        if ($paragraph->bundle() === 'generic_listing') {
          $field_name = 'field_gl_displayed_content_types';
        }
        else {
          $field_name = 'field_tabs_displayed_content_tps';
        }

        foreach ($paragraph->getTranslationLanguages() as $language) {
          /** @var \Drupal\paragraphs\ParagraphInterface $translation */
          $translation = $paragraph->getTranslation($language->getId());
          $type_terms = $translation->get('field_zoning_information_types')
            ->referencedEntities();
          $selected_types = [];
          foreach ($type_terms as $type_term) {
            $term_name = $type_term->getTranslation('fi')->label();
            if (isset($term_to_content_type_map[$term_name])) {
              $selected_types[$term_name] = $term_to_content_type_map[$term_name];
            }
          }
          $current_value = (array) $translation->get($field_name)->getValue();
          $new_value = $current_value;
          foreach ($selected_types as $type) {
            foreach ($current_value as $value) {
              if ($value['value'] === $type) {
                // Move on to next selected type.
                continue 2;
              }
            }
            $new_value[] = ['value' => $type];
          }
          if (count($new_value) > count($current_value)) {
            $translation->set($field_name, $new_value);
            $translation->save();
            $updated[$translation->language()->getId() . $translation->getRevisionId()] = $translation->id() . " (" . $translation->language()->getId() . ")";
          }
        }
      }
    }
  }

  if (count($updated) > 0) {
    return t("Updated @count paragraphs: @updated", ['@updated' => implode(", ", $updated), '@count' => count($updated)]);
  }

  return t("No paragraphs updated.");
}

/**
 * Updates geographical_areas taxonomy terms with their correct center points.
 */
function tre_preprocess_embedded_content_and_map_tabs_deploy_0003_add_location_coordinates_for_areas(&$sandbox) {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $term_coordinates = [
    'Keskusta' => [
      'longitude' => 23.753627539451866,
      'latitude' => 61.49784589900919,
    ],
    'Itä' => [
      'longitude' => 23.87546324196528,
      'latitude' => 61.506208367857965,
    ],
    'Etelä' => [
      'longitude' => 23.816021229457764,
      'latitude' => 61.45757238770822,
    ],
    'Pohjoinen' => [
      'longitude' => 23.91185828756818,
      'latitude' => 61.67380371606438,
    ],
    'Länsi' => [
      'longitude' => 23.664968345981432,
      'latitude' => 61.510903342649335,
    ],
  ];

  foreach ($term_coordinates as $term_name => $coordinates) {
    $terms = $storage->loadByProperties(['vid' => 'geographical_areas', 'name' => $term_name]);
    foreach ($terms as $term) {
      $value = sprintf("POINT(%s %s)", $coordinates['longitude'], $coordinates['latitude']);
      $term->get('field_location')->setValue($value);
      $term->save();
    }
  }
}

/**
 * Resaves the nodes that have the field_epsg_3067_point_strings field.
 */
function tre_preprocess_embedded_content_and_map_tabs_deploy_0004_resave_nodes(&$sandbox) {
  // The place_of_business nodes are only handled in their corresponding
  // migrations, resaving them will do nothing.
  $content_types_has_listing_search_content_field = [
    'city_planning_and_constructions',
    'comprehensive_plan',
    'zoning_information',
  ];

  $counter = 0;
  foreach ($content_types_has_listing_search_content_field as $content_type) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => $content_type,
    ]);

    foreach ($nodes as $node) {
      $node->save();
      $counter++;
    }
  }

  return t("Resaved @count nodes. Note that place_of_business nodes need to be updated by rerunning the migrations.", ['@count' => $counter]);
}
