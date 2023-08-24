<?php

namespace Drupal\tre_display_external_eventz_today\Plugin\ExternalDataSource;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\external_data_source\Plugin\ExternalDataSourceBase;
use Drupal\external_data_source\Plugin\ExternalDataSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin base for Eventz Today External Data Source.
 */
abstract class EventzTodayExternalDataSourcePluginBase extends ExternalDataSourceBase implements ExternalDataSourceInterface, ContainerFactoryPluginInterface {

  use MessengerTrait;

  /**
   * The Settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected Settings $settings;

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
    Settings $settings
  ) {
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('settings')
    );
  }

}
