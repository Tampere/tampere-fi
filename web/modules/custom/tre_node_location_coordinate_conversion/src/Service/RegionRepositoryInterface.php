<?php

namespace Drupal\tre_node_location_coordinate_conversion\Service;

/**
 * The region repository.
 */
interface RegionRepositoryInterface {

  /**
   * Converts a WKT polygon between projections.
   *
   * The projections are known by their SRID. The main ones of importance here
   * are 3067, used in PTV (ETRS-TM35FIN) and 3878, used in the Oskari maps
   * application (ETRS-GK24).
   *
   * @param string $polygon_string
   *   The polygon to convert in WKT format.
   * @param string $source_srid
   *   The source SRID.
   * @param string $destination_srid
   *   The destination SRID.
   *
   * @return string
   *   The converted polygon as binary string.
   */
  public function convertEpsgPolygon(string $polygon_string, string $source_srid, string $destination_srid): string;

  /**
   * Stores an array of geoCSV rows into a database table in correct projection.
   *
   * The source projection in this method is set to ETRS-GK24 (EPSG 3878) and
   * the destination projection to ETRS-TM35FIN (EPSG 3067). The lines in the
   * CSV are assumed to be in a WKT format.
   *
   * @param array[] $rows
   *   An array of associated arrays with (at least) the following keys:
   *   - GEOMETRY: contains the polygon as a WKT string.
   *   - NIMI: contains the name of the region corresponding to the geometry.
   */
  public function storeCsvRows(array $rows): void;

}
