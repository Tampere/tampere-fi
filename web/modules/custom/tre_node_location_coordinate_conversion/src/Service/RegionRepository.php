<?php

namespace Drupal\tre_node_location_coordinate_conversion\Service;

use Drupal\Core\Database\Connection;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

/**
 * The region repository.
 */
final class RegionRepository implements RegionRepositoryInterface {

  public const ETRS_TM35FIN = '3067';
  public const ETRS_GK24 = '3878';

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $databaseConnection;

  /**
   * A Proj4php instance to use in projections and transformations.
   *
   * @var \proj4php\Proj4php
   */
  private Proj4php $proj4;

  /**
   * An instance for the EPSG:3067 projection.
   *
   * @var \proj4php\Proj
   */
  private Proj $projEpsg3067;

  /**
   * An instance for the EPSG:3878 projection.
   *
   * @var \proj4php\Proj
   */
  private Proj $projEpsg3878;

  /**
   * Constructs the RegionRepository service.
   */
  public function __construct(Connection $connection) {
    $this->databaseConnection = $connection;
    $this->proj4 = new Proj4php();
    $this->projEpsg3067 = new Proj('EPSG:' . self::ETRS_TM35FIN, $this->proj4);
    $this->projEpsg3878 = new Proj('EPSG:' . self::ETRS_GK24, $this->proj4);
  }

  /**
   * {@inheritdoc}
   */
  public function convertEpsgPolygon(string $polygon_string, string $source_srid, string $destination_srid): string {
    $source_points = $this->parsePolygonStringToIndividualPoints($polygon_string);
    $destination_points = $this->transformPoints($source_points, $this->projEpsg3878, $this->projEpsg3067);
    $transformed_wkt = sprintf('POLYGON((%s))', implode(', ', $destination_points));
    // Idea from https://drupal.stackexchange.com/a/89097.
    $conversion_query = "SELECT ST_PolygonFromText(:region, :destination_srid) AS result";
    $args = [
      ':region' => $transformed_wkt,
      ':destination_srid' => $destination_srid,
    ];
    $conversion_result = $this->databaseConnection->query($conversion_query, $args)->fetchAssoc();

    return $conversion_result['result'];
  }

  /**
   * {@inheritdoc}
   */
  public function storeCsvRows(array $rows): void {
    $upsert_statement = $this->databaseConnection->upsert('tre_node_location_geo_areas');
    $upsert_statement->key('name');
    foreach ($rows as $row) {
      $polygon_wkt = $row['GEOMETRY'];
      $polygon_3067 = $this->convertEpsgPolygon($polygon_wkt, self::ETRS_GK24, self::ETRS_TM35FIN);
      $insert_row = [
        'name' => $row['NIMI'],
        'region' => $polygon_3067,
      ];

      $upsert_statement->fields(['name', 'region'])->values($insert_row);
    }
    $upsert_statement->execute();
  }

  /**
   * Parses a POLYGON well-known-text (wkt) string to an array of coordinates.
   *
   * @param string $polygon_wkt
   *   The POLYGON wkt. The format is POLYGON(([coordinates], [coordinates])).
   *
   * @return string[]
   *   An array of strings denoting coordinate pairs separated by a space.
   */
  private function parsePolygonStringToIndividualPoints(string $polygon_wkt): array {
    $string = trim($polygon_wkt);
    $string = substr($string, strlen('POLYGON (('));
    $string = substr($string, 0, -1 * strlen('))'));

    $source_points = explode(', ', $string);

    return $source_points;
  }

  /**
   * Transforms an array of point strings from one projection to another.
   *
   * @param string[] $source_points
   *   An array of coordinate pair strings separated by a single space.
   * @param \proj4php\Proj $source_projection
   *   The projection to transform from.
   * @param \proj4php\Proj $destination_projection
   *   The projection to transform to.
   *
   * @return string[]
   *   An array of coordinate pair strings transformed into the destination
   *   projection.
   */
  private function transformPoints(array $source_points, Proj $source_projection, Proj $destination_projection): array {
    $destination_points = [];
    foreach ($source_points as $source_point_string) {
      $source_point_array = explode(' ', $source_point_string);
      $source_point = new Point($source_point_array[0], $source_point_array[1]);
      $destination_point = $this->proj4->transform($source_projection, $destination_projection, $source_point);
      $destination_points[] = $destination_point->toShortString();
    }

    return $destination_points;
  }

}
