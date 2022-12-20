<?php

namespace Drupal\tre_node_logger\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Database\Connection;

/**
 * Service class to do logging via tre_node_logger.
 */
final class NodeLogger {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $db;

  /**
   * Constructs the class instance.
   */
  public function __construct(Connection $database) {
    $this->db = $database;
  }

  /**
   * Service function for saving the log in the database.
   */
  public function saveLog(String $message, String $node_id, DrupalDateTime $logging_date = NULL) {
    // If there is no logging date available, set the current time as logging_date.
    if (is_null($logging_date)) {
      $date = new DrupalDateTime('now');
      $logging_date = $date;
    }

    $logging_date_string = $logging_date->format('Y-m-d H:i:s');
    $this->db->insert('tre_node_logger')
      ->fields([
        'message' => $message,
        'nid' => $node_id,
        'logging_date' => $logging_date_string,
      ])
      ->execute();
  }

}
