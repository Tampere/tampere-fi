<?php

namespace Drupal\tre_ptv_import\PtvV11\PtvApi;

use GuzzleHttp\Exception\RequestException;
use Tampere\PtvV11\ApiException;
use Tampere\PtvV11\ObjectSerializer;
use Tampere\PtvV11\PtvApi\ServiceChannelApi;

/**
 * Overridden ServiceChannelApi implementation for correct behavior with API.
 */
class CorrectedServiceChannelApi extends ServiceChannelApi {

  /**
   * Operation apiV11ServiceChannelIdGetWithHttpInfo.
   *
   * Fetches all the information related to a single service channel.
   *
   * @param string $id
   *   Guid (required)
   * @param bool $showHeader
   *   Whether to include headers in data (optional, default to false).
   *
   * @return array
   *   of \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels, HTTP status
   *   code, HTTP response headers (array of strings)
   *
   * @throws \Tampere\PtvV11\ApiException
   *   On non-2xx response.
   */
  public function apiV11ServiceChannelIdGetWithHttpInfo($id, $showHeader = 'false') {
    $returnType = '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels';
    $request = $this->apiV11ServiceChannelIdGetRequest($id, $showHeader);

    try {
      $options = $this->createHttpClientOption();
      try {
        $response = $this->client->send($request, $options);
      }
      catch (RequestException $e) {
        throw new ApiException(
              "[{$e->getCode()}] {$e->getMessage()}",
              $e->getCode(),
              $e->getResponse() ? $e->getResponse()->getHeaders() : NULL,
              $e->getResponse() ? $e->getResponse()->getBody()->getContents() : NULL
                );
      }

      $statusCode = $response->getStatusCode();

      if ($statusCode < 200 || $statusCode > 299) {
        throw new ApiException(
              sprintf(
                  '[%d] Error connecting to the API (%s)',
                  $statusCode,
                  $request->getUri()
              ),
              $statusCode,
              $response->getHeaders(),
              $response->getBody()
          );
      }

      $responseBody = $response->getBody();
      $content = $responseBody->getContents();
      $content = json_decode($content);

      $content = $this->mapServicesTypeObjectFromSingleServiceChannelObject($content);

      return [
        ObjectSerializer::deserialize($content, $returnType, []),
        $response->getStatusCode(),
        $response->getHeaders(),
      ];

    }
    catch (ApiException $e) {
      switch ($e->getCode()) {
        case 200:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 400:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                'map[string,string[]]',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 404:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\IVmError',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 500:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\IVmError',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;
      }
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function apiV11ServiceChannelListGetWithHttpInfo($guids = NULL, $showHeader = 'false') {
    $returnType = '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]';
    $request = $this->apiV11ServiceChannelListGetRequest($guids, $showHeader);

    try {
      $options = $this->createHttpClientOption();
      try {
        $response = $this->client->send($request, $options);
      }
      catch (RequestException $e) {
        throw new ApiException(
              "[{$e->getCode()}] {$e->getMessage()}",
              $e->getCode(),
              $e->getResponse() ? $e->getResponse()->getHeaders() : NULL,
              $e->getResponse() ? $e->getResponse()->getBody()->getContents() : NULL
                );
      }

      $statusCode = $response->getStatusCode();

      if ($statusCode < 200 || $statusCode > 299) {
        throw new ApiException(
              sprintf(
                  '[%d] Error connecting to the API (%s)',
                  $statusCode,
                  $request->getUri()
              ),
              $statusCode,
              $response->getHeaders(),
              $response->getBody()
          );
      }

      $responseBody = $response->getBody();

      $content = $responseBody->getContents();
      $content = json_decode($content);
      $replacement_content = [];
      foreach ($content as $key => $value) {
        $replacement_content[$key] = $this->mapServicesTypeObjectFromSingleServiceChannelObject($value);
      }
      $content = $replacement_content;

      return [
        ObjectSerializer::deserialize($content, $returnType, []),
        $response->getStatusCode(),
        $response->getHeaders(),
      ];

    }
    catch (ApiException $e) {
      switch ($e->getCode()) {
        case 200:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 400:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                'map[string,string[]]',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 404:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\IVmError',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;

        case 500:
          $data = ObjectSerializer::deserialize(
                $e->getResponseBody(),
                '\Tampere\PtvV11\PtvModel\IVmError',
                $e->getResponseHeaders()
            );
          $e->setResponseObject($data);
          break;
      }
      throw $e;
    }
  }

  /**
   * Wraps the response in an object for ObjectSerializer::deserialize to use.
   *
   * @param mixed $service_channel_object
   *   The json_decode'd response object.
   *
   * @return object|null
   *   When given an object with a serviceChannelType property, that object is
   *   wrapped in another object with a property whose name corresponds to the
   *   mapping in V11VmOpenApiServiceChannels::$swaggerTypes.
   *
   * @see \Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels::$swaggerTypes
   */
  private function mapServicesTypeObjectFromSingleServiceChannelObject($service_channel_object) {
    $content_type_to_mapping_type_mapping = [
      'ServiceLocation' => 'locationChannel',
      'PrintableForm' => 'printableFormChannel',
      'Phone' => 'phoneChannel',
      'WebPage' => 'webPageChannel',
      'EChannel' => 'electronicChannel',
    ];

    if (is_object($service_channel_object) && isset($service_channel_object->serviceChannelType)) {
      $response_type = $service_channel_object->serviceChannelType;
    }
    else {
      return NULL;
    }

    if (isset($content_type_to_mapping_type_mapping[$response_type])) {
      $new_content = new \stdClass();
      $new_content->{$content_type_to_mapping_type_mapping[$response_type]} = $service_channel_object;

      return $new_content;
    }

    return $service_channel_object;
  }

}
