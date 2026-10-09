<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\StringTranslation\ByteSizeMarkup;

/**
 * Metadata attachment list preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__metadata_attachment_list",
 *   hook = "paragraph__metadata_attachment_list"
 * )
 */
class MetadataAttachmentList extends TrePreProcessPluginBase {

  /**
   * The taxonomy vocabularies to use when selecting the attachments.
   */
  const AVAILABLE_TAXONOMY_VOCABULARIES = [
    'topics',
    'keywords',
    'life_situations',
    'geographical_areas',
    'record_numbers',
    'plan_numbers',
    'other_identifiers',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    $paragraph_taxonomy_values = $this->helperFunctions->getParagraphTaxonomyTerms($paragraph, self::AVAILABLE_TAXONOMY_VOCABULARIES);

    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    $media_file_ids = $this->getAttachmentListMediaFileIds($current_language_id, $paragraph_taxonomy_values);

    // Prevent system from loading all media due to empty argument.
    if (empty($media_file_ids)) {
      return $variables;
    }

    $attachments = [];
    $media_entities = $this->entityTypeManager->getStorage('media')->loadMultiple($media_file_ids);
    foreach ($media_entities as $media_entity) {
      if ($media_entity instanceof EntityInterface) {
        /** @var \Drupal\media\Entity\Media $translated_media_entity */
        $translated_media_entity = $this->entityRepository->getTranslationFromContext($media_entity);

        $media_name = $translated_media_entity->label();

        /** @var \Drupal\file\Entity\File $file_entity */
        $file_entity = $translated_media_entity->get('field_media_file')->entity;

        $file_uri = $file_entity->getFileUri();
        $file_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
        $formatted_file_size = ByteSizeMarkup::create($file_entity->getSize());
        $file_extension = $this->helperFunctions->getFileExtensionFromUrl($file_url);

        $attachments[] = [
          'name' => "{$media_name} ($file_extension) ({$formatted_file_size})",
          'link_url' => $file_url,
          'icon_name' => 'download',
        ];
      }
    }

    $variables['#cache']['tags'][] = 'media_list:file';

    $variables['attachments'] = $attachments;

    return $variables;
  }

  /**
   * Returns media file IDs that match the taxonomy terms given as parameter.
   *
   * @param string $current_language_id
   *   The ID for the currently active user interface language.
   * @param array $taxonomy_values
   *   An array of taxonomy terms key'd by taxonomy vocabulary.
   *
   * @return array|null
   *   An array of media file IDs if successful. Null otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getAttachmentListMediaFileIds(string $current_language_id, array $taxonomy_values): ?array {
    $database = \Drupal::database();

    // 1. Initialize base query on media_field_data
    $query = $database->select('media_field_data', 'm')
      ->fields('m', ['mid'])
      ->condition('m.bundle', 'file')
      ->condition('m.status', 1)
      ->condition('m.langcode', $current_language_id);

      // 2. Add an EXISTS subquery per term
      foreach ($taxonomy_values as $taxonomy => $terms) {
        if (!empty($terms)) {
          foreach ($terms as $term) {
            $subquery = $database->select("media__field_{$taxonomy}", 'f')
            ->fields('f', ['entity_id'])
            ->where("f.entity_id = m.mid")
            ->condition("f.field_{$taxonomy}_target_id", $term)
            ->condition('f.langcode', $current_language_id);

          $query->exists($subquery);
        }
      }
    }

    // 3. Get matching IDs
    $mids = $query->orderBy('m.name', 'ASC')
      ->range(0, 100)
      ->execute()
      ->fetchCol();

    return $mids;
  }

}
