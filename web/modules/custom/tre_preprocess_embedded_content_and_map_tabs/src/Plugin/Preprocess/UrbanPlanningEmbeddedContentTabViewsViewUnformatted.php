<?php

namespace Drupal\tre_preprocess_embedded_content_and_map_tabs\Plugin\Preprocess;

/**
 * Urban planning embedded content tab unformatted views view preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.view__ub_embedded_content_tab",
 *   hook = "views_view_unformatted__urban_planning_embedded_content_tab"
 * )
 */
class UrbanPlanningEmbeddedContentTabViewsViewUnformatted extends EmbeddedContentTabViewsViewUnformatted {

  protected const DISPLAY_IDS = [
    "content_listing_block",
    "listing_without_area",
    "listing_without_type",
    "listing_without_status",
    "listing_without_visibility",
    "listing_without_area_type",
    "listing_without_area_status",
    "listing_without_status_type",
    "listing_without_area_visibility",
    "listing_without_type_visibility",
    "listing_without_status_visibility",
    "listing_without_area_status_type",
    "listing_without_area_type_visibility",
    "listing_without_area_status_visibility",
    "listing_without_status_type_visibility",
    "listing_without_area_status_type_visibility",
  ];

}
