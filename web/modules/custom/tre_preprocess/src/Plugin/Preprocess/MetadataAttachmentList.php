<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

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
        $formatted_file_size = format_size($file_entity->getSize());
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
    $media_file_query = $this->entityTypeManager->getStorage('media')->getQuery();
    $media_file_query
      ->condition('bundle', 'file')
      ->condition('status', 1)
      ->condition('langcode', $current_language_id)
      ->sort('name', 'ASC')
      ->range(0, 100);

    foreach ($taxonomy_values as $taxonomy => $terms) {
      if (!empty($terms)) {
        foreach ($terms as $term) {
          $media_file_query->condition($media_file_query->andConditionGroup()
            ->condition("field_{$taxonomy}", $term, '=', $current_language_id));
        }
      }
    }

    return $media_file_query->execute();
  }

}
