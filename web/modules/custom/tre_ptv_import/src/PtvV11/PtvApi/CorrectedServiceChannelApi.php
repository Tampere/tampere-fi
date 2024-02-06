<?php

// phpcs:ignoreFile

namespace Drupal\tre_ptv_import\PtvV11\PtvApi;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Tampere\PtvV11\ApiException;
use Tampere\PtvV11\ObjectSerializer;
use Tampere\PtvV11\PtvApi\ServiceChannelApi;

/**
 * Overridden ServiceChannelApi implementation for correct behavior with API.
 */
class CorrectedServiceChannelApi extends ServiceChannelApi {

  /**
   * {@inheritdoc}
   */
  public function apiV11ServiceChannelIdGetWithHttpInfo($id, $showHeader = false, string $contentType = self::contentTypes['apiV11ServiceChannelIdGet'][0]) {
    $request = $this->apiV11ServiceChannelIdGetRequest($id, $showHeader, $contentType);

    try {
      $options = $this->createHttpClientOption();
      try {
        $response = $this->client->send($request, $options);
      } catch (RequestException $e) {
        throw new ApiException(
          "[{$e->getCode()}] {$e->getMessage()}",
          (int) $e->getCode(),
          $e->getResponse() ? $e->getResponse()->getHeaders() : null,
          $e->getResponse() ? (string) $e->getResponse()->getBody() : null
        );
      } catch (ConnectException $e) {
        throw new ApiException(
          "[{$e->getCode()}] {$e->getMessage()}",
          (int) $e->getCode(),
          null,
          null
        );
      }

      $statusCode = $response->getStatusCode();

      if ($statusCode < 200 || $statusCode > 299) {
        throw new ApiException(
          sprintf(
            '[%d] Error connecting to the API (%s)',
            $statusCode,
            (string) $request->getUri()
          ),
          $statusCode,
          $response->getHeaders(),
          (string) $response->getBody()
        );
      }

      switch($statusCode) {
        case 200:
          if ('\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels' !== 'string') {
              $content = json_decode($content);
              $content = $this->mapServicesTypeObjectFromSingleServiceChannelObject($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 400:
          if ('array<string,string[]>' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('array<string,string[]>' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, 'array<string,string[]>', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 404:
          if ('\Tampere\PtvV11\PtvModel\IVmError' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\IVmError' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\IVmError', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 500:
          if ('\Tampere\PtvV11\PtvModel\IVmError' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\IVmError' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\IVmError', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
      }

      $returnType = '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels';
      if ($returnType === '\SplFileObject') {
        $content = $response->getBody(); //stream goes to serializer
      } else {
        $content = (string) $response->getBody();
        if ($returnType !== 'string') {
          $content = json_decode($content);
        }
      }

      return [
        ObjectSerializer::deserialize($content, $returnType, []),
        $response->getStatusCode(),
        $response->getHeaders()
      ];

    } catch (ApiException $e) {
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
            'array<string,string[]>',
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
  public function apiV11ServiceChannelListGetWithHttpInfo($guids = NULL, $showHeader = FALSE, string $contentType = self::contentTypes['apiV11ServiceChannelListGet'][0]) {
    $request = $this->apiV11ServiceChannelListGetRequest($guids, $showHeader, $contentType);

    try {
      $options = $this->createHttpClientOption();
      try {
        $response = $this->client->send($request, $options);
      } catch (RequestException $e) {
        throw new ApiException(
          "[{$e->getCode()}] {$e->getMessage()}",
          (int) $e->getCode(),
          $e->getResponse() ? $e->getResponse()->getHeaders() : null,
          $e->getResponse() ? (string) $e->getResponse()->getBody() : null
        );
      } catch (ConnectException $e) {
        throw new ApiException(
          "[{$e->getCode()}] {$e->getMessage()}",
          (int) $e->getCode(),
          null,
          null
        );
      }

      $statusCode = $response->getStatusCode();

      if ($statusCode < 200 || $statusCode > 299) {
        throw new ApiException(
          sprintf(
            '[%d] Error connecting to the API (%s)',
            $statusCode,
            (string) $request->getUri()
          ),
          $statusCode,
          $response->getHeaders(),
          (string) $response->getBody()
        );
      }

      switch($statusCode) {
        case 200:
          if ('\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]' !== 'string') {
              $content = json_decode($content);
              $replacement_content = [];
              foreach ($content as $key => $value) {
                $replacement_content[$key] = $this->mapServicesTypeObjectFromSingleServiceChannelObject($value);
              }
              $content = $replacement_content;
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 400:
          if ('array<string,string[]>' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('array<string,string[]>' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, 'array<string,string[]>', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 404:
          if ('\Tampere\PtvV11\PtvModel\IVmError' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\IVmError' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\IVmError', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
        case 500:
          if ('\Tampere\PtvV11\PtvModel\IVmError' === '\SplFileObject') {
            $content = $response->getBody(); //stream goes to serializer
          } else {
            $content = (string) $response->getBody();
            if ('\Tampere\PtvV11\PtvModel\IVmError' !== 'string') {
              $content = json_decode($content);
            }
          }

          return [
            ObjectSerializer::deserialize($content, '\Tampere\PtvV11\PtvModel\IVmError', []),
            $response->getStatusCode(),
            $response->getHeaders()
          ];
      }

      $returnType = '\Tampere\PtvV11\PtvModel\V11VmOpenApiServiceChannels[]';
      if ($returnType === '\SplFileObject') {
        $content = $response->getBody(); //stream goes to serializer
      } else {
        $content = (string) $response->getBody();
        if ($returnType !== 'string') {
          $content = json_decode($content);
        }
      }

      return [
        ObjectSerializer::deserialize($content, $returnType, []),
        $response->getStatusCode(),
        $response->getHeaders()
      ];

    } catch (ApiException $e) {
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
            'array<string,string[]>',
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
