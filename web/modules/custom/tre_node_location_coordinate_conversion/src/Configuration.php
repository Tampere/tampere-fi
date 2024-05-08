<?php

namespace Drupal\tre_node_location_coordinate_conversion;

/**
 * Configuration constants for the module.
 */
class Configuration {

  /**
   * The content types to be placed in geographical areas by this module.
   *
   * @var string[]
   */
  public const CONTENT_TYPES_WITH_FIELD_GEOGRAPHICAL_AREAS_TO_AUTOMATE = [
    'place_of_business',
    'city_planning_and_constructions',
    'place',
    'zoning_information',
  ];

}
