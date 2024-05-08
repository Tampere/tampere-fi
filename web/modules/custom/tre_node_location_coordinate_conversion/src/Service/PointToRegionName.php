<?php

namespace Drupal\tre_node_location_coordinate_conversion\Service;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Point-to-region-name service.
 */
final class PointToRegionName {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $databaseConnection;

  /**
   * Constructs the PointToRegionName service.
   */
  public function __construct(Connection $connection) {
    $this->databaseConnection = $connection;
  }

  /**
   * Converts a node's coordinates to a name of a geographical region.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose coordinates are being converted.
   *
   * @return string
   *   The name of the geographical area that corresponds to the coordinates in
   *   the node.
   */
  public function convertNodePointToName(NodeInterface $node): string {
    if (
      !$node->hasField(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)
      || !$node->hasField(HelperFunctionsInterface::LATITUDE_FIELD_NAME)) {
      throw new \InvalidArgumentException("Passed in node has no source data fields.");
    }

    $easting = $node->get(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)->getString();
    $northing = $node->get(HelperFunctionsInterface::LATITUDE_FIELD_NAME)->getString();

    if (empty($easting) || empty($northing)) {
      return '';
    }

    return $this->convertPointToName((float) $easting, (float) $northing);
  }

  /**
   * Converts a pair of coordinates into an area name.
   *
   * @param float $easting
   *   The easting value in EPSG 3067 projection.
   * @param float $northing
   *   The northing value in EPSG 3067 projection.
   *
   * @return string
   *   The area name matching the coordinates. If an area is not found matching
   *   the coordinates, an empty string is returned.
   */
  public function convertPointToName(float $easting, float $northing): string {
    $point_wkt = "POINT($easting $northing)";
    $conversion_query = "SELECT ST_PointFromText(:point, 3067) AS point";
    $cres = $this->databaseConnection->query($conversion_query, [':point' => $point_wkt])->fetchAssoc();
    $contains_query = "SELECT name FROM tre_node_location_geo_areas WHERE ST_Contains(region, :point)";
    $contains_result = $this->databaseConnection->query($contains_query, [':point' => $cres['point']])->fetchAssoc();
    $region_name = is_array($contains_result) ? ($contains_result['name'] ?? '') : '';

    return $region_name;
  }

}
