<?php

namespace Drupal\tre_node_location_coordinate_conversion\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\feeds\Component\CsvParser;
use Drupal\feeds\Entity\Feed;
use Drupal\feeds\Entity\FeedType;
use Drupal\feeds\Feeds\State\CleanState;
use Drupal\feeds\Plugin\Type\FeedsPluginManager;
use Drupal\node\NodeInterface;
use Drupal\tre_node_location_coordinate_conversion\Configuration;
use Drupal\tre_node_location_coordinate_conversion\Service\RegionRepositoryInterface;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A Drush commandfile.
 */
final class TreGeographicalAreaMappingCommands extends DrushCommands {

  use AutowireTrait;

  public const GEODATA_CSV_URL = 'https://geodata.tampere.fi/geoserver/hallinnolliset_yksikot/ows?service=WFS&version=1.0.0&request=GetFeature&typeName=hallinnolliset_yksikot%3AKH_ALUEJAKO_GSVIEW&outputFormat=csv';

  /**
   * Constructs the command file.
   */
  public function __construct(
    #[Autowire(service: 'plugin.manager.feeds.fetcher')]
    private FeedsPluginManager $feedsPluginManager,
    #[Autowire(service: 'tre_node_location_coordinate_conversion.storage')]
    private RegionRepositoryInterface $regionRepository,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Imports geographical areas from geodata.tampere.fi to database.
   */
  #[CLI\Command(name: 'tre_node_location_coordinate_conversion:import-areas')]
  public function importAreas() {
    // Dummy values for id and label.
    $feed_type = FeedType::create([
      'id' => 'tre_geographical_area_mapping',
      'label' => 'TRE Geographical Area Mapping',
    ]);

    // Dummy values for id, title, and type.
    $csv_feed = Feed::create([
      'id' => 'tre_geographical_area_mapping_csv',
      'title' => 'test feed csv',
      'type' => 'tre_geographical_area_mapping',
      'source' => self::GEODATA_CSV_URL,
    ]);

    /** @var \Drupal\feeds\Feeds\Fetcher\HttpFetcher $fetcher */
    $fetcher = $this->feedsPluginManager->createInstance('http', ['feed_type' => $feed_type]);

    // Dummy state to be able to use the fetch method from $fetcher.
    $state_csv = new CleanState(0);

    // Use Feeds fetcher plugin to get the CSV from the URL.
    $fetcher_result = $fetcher->fetch($csv_feed, $state_csv);

    // Use Feeds CSV parser to parse the files.
    $parser_csv = CsvParser::createFromFilePath($fetcher_result->getFilePath());
    $parser_csv->setHasHeader(TRUE);
    // The header is assumed to contain at least the values 'GEOMETRY' and
    // 'NIMI'.
    $header = $parser_csv->getHeader();
    $rows = [];
    foreach ($parser_csv as $row) {
      $row_data = [];
      foreach ($header as $header_key => $header_value) {
        $row_data[$header_value] = $row[$header_key];
      }
      $rows[] = $row_data;
    }

    $row_chunks = array_chunk($rows, 100);
    foreach ($row_chunks as $chunk) {
      $this->regionRepository->storeCsvRows($chunk);
    }

    // Clean up the downloaded file.
    $fetcher_result->cleanUp();

  }

  /**
   * Fills in empty field_geographical_area fields for nodes with coordinates.
   */
  #[CLI\Command(name: 'tre_node_location_coordinate_conversion:fill-in-nodes', aliases: ['tre-coordinates-fill-in'])]
  #[CLI\Option(name: 'print-urls', description: 'Whether to print out URLs of the modified nodes (yes/no). Best used with the --uri switch.')]
  #[CLI\Usage(name: 'tre-coordinates-fill-in', description: 'Fills in empty field_geographical_areas fields on nodes with geographical coordinates filled in, without output.')]
  #[CLI\Usage(name: 'tre-coordinates-fill-in --uri=https://example.com --print-urls=yes', description: 'Fills in empty field_geographical_areas fields on nodes with geographical coordinates filled in, outputting the edit URL of each node.')]
  public function fillInGeoRegionValues($options = ['print-urls' => 'no']) {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()->accessCheck(FALSE);

    // Find all nodes which have values in the coordinate fields but none in
    // field_geographical_areas. The map_point content type gets handled in the
    // PTV migrations.
    $query
      ->exists(HelperFunctionsInterface::LONGITUDE_FIELD_NAME)
      ->exists(HelperFunctionsInterface::LATITUDE_FIELD_NAME)
      ->condition('type', 'map_point', '<>')
      ->condition('type', Configuration::CONTENT_TYPES_WITH_FIELD_GEOGRAPHICAL_AREAS_TO_AUTOMATE, 'IN');

    $nids_with_no_area = $query->execute();

    foreach ($nids_with_no_area as $nid) {
      $node = $node_storage->load($nid);

      if (!($node instanceof NodeInterface)) {
        continue;
      }

      _tre_node_location_coordinate_conversion_store_geo_area_term_by_epsg_3067_coordinates($node);
      $node->save();
      if ($options['print-urls'] !== 'no') {
        $this->io()->writeln($node->toUrl('edit-form', ['absolute' => TRUE])->toString());
      }
    }
  }

}
