<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\tre_node_location_coordinate_conversion\Service\RegionRepository' "modules/custom/tre_node_location_coordinate_conversion/src".
 */

namespace Drupal\tre_node_location_coordinate_conversion\ProxyClass\Service {

    /**
     * Provides a proxy class for \Drupal\tre_node_location_coordinate_conversion\Service\RegionRepository.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class RegionRepository implements \Drupal\tre_node_location_coordinate_conversion\Service\RegionRepositoryInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\tre_node_location_coordinate_conversion\Service\RegionRepository
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
        public function convertEpsgPolygon(string $polygon_string, string $source_srid, string $destination_srid): string
        {
            return $this->lazyLoadItself()->convertEpsgPolygon($polygon_string, $source_srid, $destination_srid);
        }

        /**
         * {@inheritdoc}
         */
        public function storeCsvRows(array $rows): void
        {
            $this->lazyLoadItself()->storeCsvRows($rows);
        }

    }

}
