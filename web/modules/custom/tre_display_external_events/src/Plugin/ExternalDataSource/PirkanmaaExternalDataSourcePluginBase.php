<?php

namespace Drupal\tre_display_external_events\Plugin\ExternalDataSource;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\external_data_source\Plugin\ExternalDataSourceBase;
use Drupal\external_data_source\Plugin\ExternalDataSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin base for Pirkanmaa External Data Source.
 */
abstract class PirkanmaaExternalDataSourcePluginBase extends ExternalDataSourceBase implements ExternalDataSourceInterface, ContainerFactoryPluginInterface {

  use MessengerTrait;

  /**
   * The Settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected Settings $settings;

  /**
   * The HTTP client.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * The plugin implementation definition.
   *
   * @var array
   */
  protected $pluginDefinition = [];

  /**
   * Constructor.
   */
  final public function __construct(
    Settings $settings,
    ClientFactory $http_client_factory
  ) {
    $this->settings = $settings;
    $this->httpClientFactory = $http_client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * @{inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('settings'),
      $container->get('http_client_factory')
    );
  }

}
