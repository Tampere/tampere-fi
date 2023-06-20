<?php

namespace Drupal\tre_node_json_api_static\Commands;

use Drupal\Component\Serialization\Json;
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
  public function writeFileForContent(string $content_type, string $langcode, ?string $basepath = NULL) {
    assert(in_array($content_type, ['person', 'place', 'place_of_business'], TRUE), dt("Only person, place, or place_of_business content supported."));
    assert(in_array($langcode, ['fi', 'en'], TRUE), "Only fi or en language supported");

    $node_query = $this->nodeStorage->getQuery()->accessCheck(FALSE);
    $node_query->condition('type', $content_type)
      ->condition('status', NodeInterface::PUBLISHED, '=', $langcode)
      ->condition('langcode', $langcode)
      ->sort('changed', 'DESC');
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
      // See https://github.com/mglaman/phpstan-drupal/issues/147
      // @phpstan-ignore-next-line
      'field_additional_information' => $node->get('field_additional_information')->processed,
      'field_address_street' => $node->get('field_address_street')->first()?->getValue(),
      'field_address_postal' => $node->get('field_address_postal')->first()?->getValue(),
      'field_hr_cost_center' => ['name' => $this->getReferenceLabel($node, 'field_hr_cost_center')],
      'field_hr_organizational_unit' => ['name' => $this->getReferenceLabel($node, 'field_hr_organizational_unit')],
      'field_place' => ['title' => $this->getReferenceLabel($node, 'field_place')],
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
