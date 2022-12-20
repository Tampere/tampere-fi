<?php

namespace Drupal\tre_ptv_import\Plugin\migrate\source;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin class for source plugin for PTV Service ontology.
 *
 * Available configuration keys:
 * - vocabulary: The name of the vocabulary for which the source data is being
 *   requested. This class contains a mapping for the source table.
 * - language: The language code for the language for which the import is being
 *   made.
 *
 * @MigrateSource(
 *   id = "ptv_service_ontology"
 * )
 */
class PtvServiceOntology extends SqlBase {

  /**
   * The database table to use in the source data query.
   */
  const TABLE_MAPPING = [
    'life_situations' => 'tre_ptv_import_target_groups_and_life_event',
    'topics' => 'tre_ptv_import_service_class',
    'keywords' => 'tre_ptv_import_ontology_term',
  ];

  /**
   * The name of the table to use in the query.
   *
   * @var string
   */
  protected string $table;

  /**
   * The language to get the information in.
   *
   * @var string
   */
  public string $contentLanguage;

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select($this->table, 'ontology_table')
      ->fields('ontology_table', $this->fields());

    $query->where('ontology_table.name_' . $this->contentLanguage . ' IS NOT NULL');
    $query->where('ontology_table.name_' . $this->contentLanguage . " <> ''");

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'id',
      'name_' . $this->contentLanguage,
    ];
    return array_combine($fields, $fields);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => [
        'type' => 'string',
        'max_length' => 1024,
        'is_ascii' => TRUE,
        'alias' => 'ontology_table',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Creates a uniform name for the source field regardless of language.
    $row->setSourceProperty('name', $row->getSourceProperty('name_' . $this->contentLanguage));
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->contentLanguage = $configuration['language'];
    if (isset(self::TABLE_MAPPING[$configuration['vocabulary']])) {
      $instance->table = self::TABLE_MAPPING[$configuration['vocabulary']];
    }
    else {
      $valid_vocabularies = implode(", ", array_keys(self::TABLE_MAPPING));
      throw new MigrateException("Can only use one of following vocabularies: $valid_vocabularies");
    }

    return $instance;
  }

}
