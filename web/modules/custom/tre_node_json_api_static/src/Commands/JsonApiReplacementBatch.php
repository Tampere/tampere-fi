<?php

namespace Drupal\tre_node_json_api_static\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;

/**
 * Drush commands for generating static JSON listings of nodes.
 */
final class JsonApiReplacementBatch extends DrushCommands {

  private const PUBLIC_FILES_BASEPATH = 'api_json';

  private const API_IMAGE_STYLE_NAME = 'api_image_style';

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  private NodeStorageInterface $nodeStorage;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private FileSystemInterface $fileSystem;

  /**
   * The image style storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  private ImageStyleStorageInterface $imageStyleStorage;

  /**
   * The File URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  private FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs the drush command class.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, FileUrlGeneratorInterface $generator, ModuleHandlerInterface $module_handler) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');

    $this->imageStyleStorage = $entity_type_manager->getStorage('image_style');

    $this->fileSystem = $file_system;

    $this->fileUrlGenerator = $generator;

    $this->moduleHandler = $module_handler;
  }

  /**
   * Generates a static JSON file for a content type in given language.
   *
   * For the image URLs to generate correctly, the correct --uri (or -l) switch
   * must be used when invoking drush.
   *
   * @param string $content_type
   *   The content type. The supported ones are: person, place,
   *   place_of_business.
   * @param string $langcode
   *   The language code. One of fi, en.
   * @param string|null $basepath
   *   The base path for the result file, optional.
   *   Defaults to public://api_json.
   * @param array $options
   *   The options for the command.
   *
   * @command tre_node_json_api_static:writefile
   *
   * @usage drush -l https://www.tampere.fi write_jsonapi_file person fi
   *   Generate a listing of Finnish language person nodes using
   *   https://www.tampere.fi as the base URL for the image URLs.
   *
   * @throws \Drush\Exceptions\CommandFailedException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @aliases write_jsonapi_file
   */
  public function writeFileForContent(string $content_type, string $langcode, ?string $basepath = NULL, array $options = ['orderby' => 'changed:DESC']) {
    $content_types = [
      'person',
      'place',
      'place_of_business',
      'ptv_service',
      'service_channel',
      'map_point',
    ];
    assert(in_array($content_type, $content_types, TRUE), dt("Only person, place, or place_of_business content supported."));
    assert(in_array($langcode, ['fi', 'en'], TRUE), "Only fi or en language supported");

    assert(str_contains($options['orderby'], ':'), "The --orderby options is malformatted. The correct format is <column>:<DIRECTION>.");
    [$orderby_column, $orderby_direction] = explode(':', $options['orderby']);
    assert(in_array($orderby_direction, ['ASC', 'DESC'], TRUE), "Only ASC or DESC directions allowed for orderby parameter!");
    assert(in_array($orderby_column, ['title', 'changed', 'uuid'], TRUE), "Only uuid, title,com or changed columns allowed for orderby parameter!");

    $node_query = $this->nodeStorage->getQuery()->accessCheck(FALSE);
    $node_query->condition('type', $content_type)
      ->condition('status', NodeInterface::PUBLISHED, '=', $langcode)
      ->condition('langcode', $langcode)
      ->sort($orderby_column, $orderby_direction);
    $result = $node_query->execute();
    $count = count($result);

    $base_path = $basepath ?? self::PUBLIC_FILES_BASEPATH;
    $base_path = 'public://' . $base_path;
    if (!$this->fileSystem->prepareDirectory($base_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new CommandFailedException("Failed to prepare $base_path for writing.");
    }

    $temp_file = $this->fileSystem->tempnam($base_path, $content_type);

    $handle = fopen($temp_file, 'w');

    if ($handle === FALSE) {
      throw new CommandFailedException("Failed to open $temp_file for writing.");
    }

    $chunks = array_chunk($result, 50);
    $counter = 0;

    fwrite($handle, "[\n");

    $context = [
      'count' => $count,
      'content_type' => $content_type,
      'langcode' => $langcode,
    ];
    $this->logger()->success("Found {count} published nodes of type {content_type} in language {langcode}.", $context);
    foreach ($chunks as $chunk) {
      $nodes = $this->nodeStorage->loadMultiple($chunk);

      foreach (array_values($nodes) as $node) {
        if (!($node instanceof NodeInterface) || !$node->hasTranslation($langcode)) {
          throw new CommandFailedException("Node unavailable for export as it has no translation for $langcode.");
        }
        $translation = $node->getTranslation($langcode);
        if (!($translation instanceof NodeInterface)) {
          throw new CommandFailedException("Node translation unavailable for export as it has no translation for $langcode.");
        }
        $jsonapi_result = match ($node->bundle()) {
          'person' => $this->getDataForPerson($translation),
          'place_of_business' => $this->getDataForPlaceOfBusiness($translation),
          'place' => $this->getDataForPlace($translation),
          'ptv_service' => $this->getDataForService($translation),
          'service_channel' => $this->getDataForServiceChannel($translation),
          'map_point' => $this->getDataForMapPoint($translation),
          default => [],
        };
        fwrite($handle, Json::encode($jsonapi_result));
        if (++$counter < $count) {
          fwrite($handle, ",");
        }
        fwrite($handle, "\n");
      }
    }
    fwrite($handle, "]");

    fclose($handle);

    $destination = $base_path . '/' . $content_type . '_' . $langcode . '.json';
    $this->fileSystem->move($temp_file, $destination, FileSystemInterface::EXISTS_REPLACE);

    $absolute_destination = $this->fileUrlGenerator->generate($destination)->setAbsolute()->toString();
    $this->logger()->success("Output written to file {destination}!", ['destination' => $absolute_destination]);

    if ($this->moduleHandler->moduleExists('purge_drush')) {
      $queue_add_args = [
        [
          'type' => 'url',
          'expression' => $absolute_destination,
        ],
      ];
      drush_op($this->getPurgeQueueCommands(), $queue_add_args);
      $this->logger()->success("Cache purge request sent for {destination}!", ['destination' => $absolute_destination]);
    }
    return self::EXIT_SUCCESS;
  }

  /**
   * Helper to get serializable data for a person node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The person node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForPerson(NodeInterface $node): array {
    assert($node->bundle() == 'person', "Node must of type 'person'");
    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'field_image' => $this->getImageApiUrlFromMediaField($node, 'field_image'),
      'field_first_names' => $node->get('field_first_names')->getString(),
      'field_last_name' => $node->get('field_last_name')->getString(),
      'field_hr_title' => ['name' => $this->getReferenceLabel($node, 'field_hr_title')],
      'field_email' => $node->get('field_email')->getString(),
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_additional_information' => $node->get('field_additional_information')->processed,
      'field_address_street' => $node->get('field_address_street')->first()?->getValue(),
      'field_address_postal' => $node->get('field_address_postal')->first()?->getValue(),
      'field_hr_cost_center' => ['name' => $this->getReferenceLabel($node, 'field_hr_cost_center')],
      'field_hr_organizational_unit' => ['name' => $this->getReferenceLabel($node, 'field_hr_organizational_unit')],
      'field_place' => ['title' => $this->getReferenceLabel($node, 'field_place')],
      'field_phone' => $node->get('field_phone')->getString(),
      'field_phone_supplementary' => $node->get('field_phone_supplementary')->isEmpty() ? NULL : $node->get('field_phone_supplementary')->getString(),
      'field_additional_phones' => $node->get('field_additional_phones')->isEmpty() ? NULL : $node->get('field_additional_phones')->getValue(),
    ];
  }

  /**
   * Helper to get serializable data for a place_of_business node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place_of_business node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForPlaceOfBusiness(NodeInterface $node): array {
    assert($node->bundle() == 'place_of_business', "Node must be of type 'place_of_business'");
    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'title' => $node->label(),
      'field_summary' => $node->get('field_summary')->getString(),
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_body_md' => $node->get('field_body_md')->processed,
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_additional_information' => $node->get('field_additional_information')->processed,
      'field_email' => $node->get('field_email')->getString(),
      'field_additional_phones' => $node->get('field_additional_phones')->getValue(),
      'field_address_postal' => $node->get('field_address_postal')->first()?->getValue(),
    ];
  }

  /**
   * Helper to get serializable data for a place node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForPlace(NodeInterface $node): array {
    assert($node->bundle() == 'place', "Node must be of type 'place'");
    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'title' => $node->label(),
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_additional_information' => $node->get('field_additional_information')->processed,
      'field_description' => $node->get('field_description')->getString(),
      'field_address' => $node->get('field_address')->getString(),
      'field_main_image' => $this->getImageApiUrlFromMediaField($node, 'field_main_image'),
    ];
  }

  /**
   * Helper to get serializable data for a ptv_service node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForService(NodeInterface $node): array {
    assert($node->bundle() == 'ptv_service', "Node must be of type 'ptv_service'");

    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'title' => $node->label(),
      'field_form_service_channels' => array_map([$this, 'entityLabel'], $node->get('field_form_service_channels')->referencedEntities()),
      'field_phone_service_channels' => array_map([$this, 'entityLabel'], $node->get('field_phone_service_channels')->referencedEntities()),
      'field_eservice_channels' => array_map([$this, 'entityLabel'], $node->get('field_eservice_channels')->referencedEntities()),
      'field_web_page_channels' => array_map([$this, 'entityLabel'], $node->get('field_web_page_channels')->referencedEntities()),
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_user_instruction_md' => $node->get('field_user_instruction_md')->processed,
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_chargeability_info_md' => $node->get('field_chargeability_info_md')->processed,
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_body_md' => $node->get('field_body_md')->processed,
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_requirements_md' => $node->get('field_requirements_md')->processed,
      'field_area_text' => $node->get('field_area_text')->getString(),
      'field_service_vouchers_in_use' => $node->get('field_service_vouchers_in_use')->getString(),
      'field_service_voucher_links' => $node->get('field_service_voucher_links')->getString(),
      'field_service_responsible' => $node->get('field_service_responsible')->getString(),
      'field_service_other_responsible' => $node->get('field_service_other_responsible')->getString(),
      'field_service_charge_type' => $node->get('field_service_charge_type')->getString(),
      'field_available_languages' => $node->get('field_available_languages')->getString(),
    ];
  }

  /**
   * Helper to get serializable data for a service_channel node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForServiceChannel(NodeInterface $node): array {
    assert($node->bundle() == 'service_channel', "Node must be of type 'service_channel'");

    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'title' => $node->label(),
      'field_area_text' => $node->get('field_area_text')->getString(),
      'field_service_channel_type' => $node->get('field_service_channel_type')->getString(),
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_body_md' => $node->get('field_body_md')->processed,
      'field_service_attachments' => $node->get('field_service_attachments')->getString(),
      'field_form_links' => $node->get('field_form_links')->getString(),
      'field_form_receiver' => $node->get('field_form_receiver')->getString(),
      'field_service_hours_daily' => $node->get('field_service_hours_daily')->getString(),
      'field_service_hours_overnight' => $node->get('field_service_hours_overnight')->getString(),
      'field_available_languages' => $node->get('field_available_languages')->getString(),
      'field_exception_hours' => $node->get('field_exception_hours')->getString(),
      'field_address_postal' => $node->get('field_address_postal')->getString(),
      'field_additional_phones' => $node->get('field_additional_phones')->getString(),
      'field_accessibility_links' => $node->get('field_accessibility_links')->getString(),
      'field_summary' => $node->get('field_summary')->getString(),
      'field_delivery_details' => $node->get('field_delivery_details')->getString(),
      'field_support_phones' => $node->get('field_support_phones')->getString(),
      'field_support_emails' => $node->get('field_support_emails')->getString(),
      'field_electronic_signature_rqd' => $node->get('field_electronic_signature_rqd')->getString(),
      'field_electronic_id_required' => $node->get('field_electronic_id_required')->getString(),
      'field_organization' => $node->get('field_organization')->getString(),
      'field_links' => $node->get('field_links')->getString(),
    ];
  }

  /**
   * Helper to get serializable data for a map_point node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The place node.
   *
   * @return array
   *   The data in a format similar to JSON:API's serialized format.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getDataForMapPoint(NodeInterface $node): array {
    assert($node->bundle() == 'map_point', "Node must be of type 'map_point'");

    return [
      'id' => $node->get('uuid')->getString(),
      'langcode' => $node->language()->getId(),
      'title' => $node->label(),
      'field_address_street' => $node->get('field_address_street')->getString(),
      'field_description' => $node->get('field_description')->getString(),
      'field_address_hash' => $node->get('field_address_hash')->getString(),
    ];
  }

  /**
   * Simple callback to return the label of a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose label is needed.
   *
   * @return string
   *   The label of the entity.
   */
  private function entityLabel(ContentEntityInterface $entity): string {
    return $entity->label();
  }

  /**
   * Gets the label of a reference field value in the language of the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node housing the reference field.
   * @param string $field_name
   *   The name of the reference field.
   *
   * @return string|null
   *   The label of the referenced entity in the language of the housing node,
   *   if available.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getReferenceLabel(NodeInterface $node, string $field_name): ?string {
    if ($node->get($field_name)->isEmpty()) {
      return NULL;
    }

    if (!($node->get($field_name) instanceof EntityReferenceFieldItemListInterface)) {
      return NULL;
    }

    $entities = $node->get($field_name)->referencedEntities();
    $entity = reset($entities);

    if (!($entity instanceof EntityInterface)) {
      return NULL;
    }

    $translation = $entity;
    if ($entity instanceof TranslatableInterface && $entity->hasTranslation($node->language()->getId())) {
      $translation = $entity->getTranslation($node->language()->getId());
    }

    return $translation->label();
  }

  /**
   * Gets the absolute URL to an image in a media field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node housing the media field.
   * @param string $field_name
   *   The field name in the node for the media field.
   * @param string $image_style
   *   The name of the image style. Optional; defaults to 'api_image_style'.
   *
   * @return string|null
   *   The absolute URL to the image in the desired style.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  private function getImageApiUrlFromMediaField(NodeInterface $node, string $field_name, string $image_style = self::API_IMAGE_STYLE_NAME): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }

    if (!isset($node->get($field_name)->first()->entity) || !($node->get($field_name)->first()->entity instanceof Media)) {
      return NULL;
    }

    if ($node->get($field_name)->first()->entity->bundle() !== 'image') {
      return NULL;
    }

    $file_item_list = $node->get($field_name)->first()->entity->get('field_media_image');

    if ($file_item_list->isEmpty() || !isset($file_item_list->first()->entity)) {
      return NULL;
    }

    if (!($file_item_list->first()->entity instanceof File)) {
      return NULL;
    }

    $file = $file_item_list->first()->entity;
    $file_uri = $file->getFileUri();
    if (empty($file_uri)) {
      return NULL;
    }
    $style = $this->imageStyleStorage->load($image_style);
    if (!($style instanceof ImageStyle)) {
      return NULL;
    }

    return $style->buildUrl($file_uri);
  }

  /**
   * Proxy method for getting a callable for invalidating Varnish cache.
   *
   * Since the module for Varnish purging may not be available in all
   * environments, the service cannot be injected directly.
   *
   * @return array
   *   The callable for drush p:queue-add.
   *
   * @see \Drupal\purge_drush\Commands\QueueCommands::queueAdd()
   */
  private function getPurgeQueueCommands() {
    // phpcs:ignore
    $service = \Drupal::service('purge_drush.queue_commands'); // @phpstan-ignore-line

    return [$service, 'queueAdd'];
  }

}
