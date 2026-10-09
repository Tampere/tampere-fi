<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Api;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OpenAPI\Client\Api\ConnectionApi;
use OpenAPI\Client\Api\OrganizationApi;
use OpenAPI\Client\Api\ServiceApi;
use OpenAPI\Client\Api\ServiceChannelApi;
use OpenAPI\Client\Configuration;
use Psr\Http\Message\RequestInterface;
use OpenAPI\Client\Api\MunicipalitiesApi;

/**
 * Creates configured clients for the PTV API.
 */
final class PtvApiClientFactory {

  /**
   * Constructs the client factory.
   */
  public function __construct(
    private readonly PtvApiSettings $settings,
  ) {}

  /**
  * Creates the generated Municipalities API client.
  */
  public function createMunicipalitiesApi(
    ClientInterface $httpClient,
  ): MunicipalitiesApi {
    return new MunicipalitiesApi(
      $httpClient,
      $this->createConfiguration(),
    );
  }

  /**
   * Creates the HTTP client used for PTV requests.
   */
  public function createHttpClient(): Client {
    $apiKey = $this->settings->getApiKey();
    $handlerStack = HandlerStack::create();

    $handlerStack->push(
      Middleware::mapRequest(
        static fn (RequestInterface $request): RequestInterface => $request
          ->withHeader('x-api-key', $apiKey)
      )
    );

    return new Client([
      'handler' => $handlerStack,
      'headers' => [
        'Accept' => 'application/json',
      ],
      'timeout' => $this->settings->getRequestTimeout(),
      'connect_timeout' => 10,
    ]);
  }

  /**
   * Creates the generated Service API client.
   */
  public function createServiceApi(ClientInterface $httpClient): ServiceApi {
    return new ServiceApi(
      $httpClient,
      $this->createConfiguration(),
    );
  }

  /**
   * Creates the generated Connection API client.
   */
  public function createConnectionApi(
    ClientInterface $httpClient,
  ): ConnectionApi {
    return new ConnectionApi(
      $httpClient,
      $this->createConfiguration(),
    );
  }

  /**
   * Creates the generated Service Channel API client.
   */
  public function createServiceChannelApi(
    ClientInterface $httpClient,
  ): ServiceChannelApi {
    return new ServiceChannelApi(
      $httpClient,
      $this->createConfiguration(),
    );
  }

  /**
   * Creates the generated Organization API client.
   */
  public function createOrganizationApi(
    ClientInterface $httpClient,
  ): OrganizationApi {
    return new OrganizationApi(
      $httpClient,
      $this->createConfiguration(),
    );
  }

  /**
   * Creates the generated client configuration.
   */
  private function createConfiguration(): Configuration {
    $configuration = new Configuration();
    $configuration->setHost($this->settings->getApiHost());
    $configuration->setUserAgent('tre_ptv_import/ptv-api');

    return $configuration;
  }

}