<?php

/**
 * @file
 * Deploy hook implementations for tre_preprocess_embedded_content_and_map_tabs.
 */

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
