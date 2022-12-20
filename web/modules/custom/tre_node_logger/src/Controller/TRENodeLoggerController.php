<?php

namespace Drupal\tre_node_logger\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains the BaseController controller.
 */
class TRENodeLoggerController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * Constructs the class instance.
   */
  public function __construct(Connection $database) {
    $this->db = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Service function for creating and returning the log table.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to send.
   */
  public function getOutput(NodeInterface $node) {
    $query = $this->db->query("SELECT id, message, logging_date FROM tre_node_logger WHERE nid = :nid ORDER BY id DESC", [':nid' => $node->id()]);
    $result = $query->fetchAll();

    $arrayCollection = [];
    foreach ($result as $item) {
      $arrayCollection[] = [
        $item->id,
        $item->message,
        $item->logging_date,
      ];
    }

    $header = ['ID', 'Message', 'Logging Date'];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $arrayCollection,
    ];

    return [
      $build,
    ];
  }

}
