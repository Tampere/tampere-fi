<?php

namespace Drupal\tre_hr_import\Plugin\migrate\process;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Make sure we don't override fixed term contracts with open-ended ones.
 *
 * @MigrateProcessPlugin(
 *   id = "contract_end_date_check"
 * )
 */
final class ContractEndDateCheck extends ProcessPluginBase implements ContainerFactoryPluginInterface {
  /**
   * The entity_type.manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $db, LoggerChannelFactoryInterface $factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->db = $db;
    $this->loggerFactory = $factory;
  }

  /**
   * Stops processing the current property when value is not set.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if the test was not a 404.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Thrown if the source property is not set and the row should be skipped,
   *   records with STATUS_IGNORED status in the map.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $incomingContractId = $row->getSourceProperty('contract_nbr');
    $incomingContractStartDate = $row->getSourceProperty('contract_begin_date');
    $incomingContractEndDate = $value;

    $query = $this->db->select('hr_import_temp_data', 'hitd');
    $query->fields('hitd', ['user_id', 'start_time', 'end_time']);
    $query->condition('user_id', $incomingContractId);
    $result = $query->execute()->fetchAll();

    // If nothing in the db, insert the contract.
    if (empty($result)) {
      $query = $this->db->insert('hr_import_temp_data');
      $query->fields([
        'user_id' => $incomingContractId,
        'start_time' => $incomingContractStartDate,
        'end_time' => $incomingContractEndDate,
      ]);
      $query->execute();
    }
    else {
      $existingEntryEndTime = $result[0]->end_time;

      // If contract already exists, let's check if it's open ended.
      // If open-ended and incoming is not, let's replace.
      if ($existingEntryEndTime == '99991231' && $incomingContractEndDate != '99991231') {
        $query = $this->db->update('hr_import_temp_data');
        $query->fields([
          'start_time' => $incomingContractStartDate,
          'end_time' => $incomingContractEndDate,
        ]);
        $query->condition('user_id', $incomingContractId);
        $query->execute();
      }
      // If existing contract is not open ended
      // and incoming one is, let's skip row.
      if ($existingEntryEndTime != '99991231' && $incomingContractEndDate == '99991231') {
        $this->loggerFactory->get('tre_hr_import')
          ->info("Personnel with contract number {$incomingContractId} was skipped to avoid overriding fixed term contract with open ended one.");
        throw new MigrateSkipRowException("Skipped updating contract number {$incomingContractId}.");
      }
    }
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('logger.factory'),
    );
  }

}
