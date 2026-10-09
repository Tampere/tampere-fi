<?php

declare(strict_types=1);

namespace Drupal\tre_ptv_import\Service\Api;

use OpenAPI\Client\Model\EServiceChannelResponse;
use OpenAPI\Client\Model\ModelInterface;
use OpenAPI\Client\Model\PhoneChannelResponse;
use OpenAPI\Client\Model\PrintableFormChannelResponse;
use OpenAPI\Client\Model\ServiceLocationChannelResponse;
use OpenAPI\Client\Model\WebPageChannelResponse;
use OpenAPI\Client\ObjectSerializer;

/**
 * Deserializes stored channel data into concrete generated response models.
 *
 * The generated OpenAPI client does not reliably resolve the polymorphic
 * anyOf channel response into the correct model class. This class reads the
 * serviceChannelType value, selects the corresponding generated model class
 * and delegates the actual deserialization to ObjectSerializer.
 */
final class PtvChannelResponseDeserializer {

  /**
   * Generated model classes by API channel type.
   */
  private const MODEL_CLASSES = [
    'EService' => EServiceChannelResponse::class,
    'TelephoneService' => PhoneChannelResponse::class,
    'PrintableForm' => PrintableFormChannelResponse::class,
    'ServiceLocation' => ServiceLocationChannelResponse::class,
    'WebPage' => WebPageChannelResponse::class,
  ];

  /**
   * Deserializes channel data into the matching concrete generated model.
   *
   * The concrete model class is selected from serviceChannelType because the
   * generated OpenAPI anyOf deserialization does not resolve it reliably.
   *
   * @param array<string, mixed> $data
   *   Decoded channel payload from intermediate storage.
   *
   * @return \OpenAPI\Client\Model\ModelInterface
   *   The generated concrete channel model.
   */
  public static function deserialize(array $data): ModelInterface {
    $channelType = $data['serviceChannelType'] ?? NULL;

    if (!is_string($channelType) || trim($channelType) === '') {
      throw new \RuntimeException(
        'PTV channel response does not contain serviceChannelType.'
      );
    }

    $channelType = trim($channelType);
    $modelClass = self::MODEL_CLASSES[$channelType] ?? NULL;

    if ($modelClass === NULL) {
      throw new \RuntimeException(
        'Unsupported PTV channel type "' . $channelType . '".'
      );
    }

    $model = ObjectSerializer::deserialize(
      (object) $data,
      $modelClass
    );

    if (!$model instanceof ModelInterface) {
      throw new \RuntimeException(
        'PTV channel type "' . $channelType . '" did not deserialize into a model.'
      );
    }

    return $model;
  }

}