<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Topical content pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_topical_content",
 *   hook = "pattern_topical_content"
 * )
 */
class TopicalContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $node = $pattern_context->getProperty('entity');

    if (!($node instanceof NodeInterface)
      || !$node->hasField('field_main_image')
      || $node->get('field_main_image')->isEmpty()
    ) {
      return $variables;
    }

    // Some nodes have a field for toggling the visibility of the page main
    // image.
    if (
      $node->hasField('field_display_main_image_on_page') &&
      $node->get('field_display_main_image_on_page')->getString() !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE
    ) {
      $variables['hide_topical_content_main_image'] = TRUE;
    }

    $main_image_id = $node->get('field_main_image')->getString();

    if (empty($main_image_id)) {
      return $variables;
    }

    $main_image_media = $this->entityTypeManager->getStorage('media')->load($main_image_id);

    if (empty($main_image_media)) {
      return $variables;
    }

    /** @var \Drupal\media\MediaInterface $translated_media_entity */
    $translated_media_entity = $this->entityRepository->getTranslationFromContext($main_image_media);

    $main_image_file_id = $translated_media_entity->get('field_media_image')[0]->target_id;
    $main_image_file_name = $translated_media_entity->name->getString();
    $main_image_file_alt = $translated_media_entity->get('field_media_image')[0]->alt;
    $main_image_file = $this->entityTypeManager->getStorage('file')->load($main_image_file_id);
    $main_image_file_uri = $main_image_file->uri->getString();
    $main_image_file_url = $this->fileUrlGenerator->generateAbsoluteString($main_image_file_uri);
    $variables['topical_content__main_image__url'] = $main_image_file_url;
    $variables['topical_content__main_image__name'] = $main_image_file_name;
    $variables['topical_content__main_image__alt'] = $main_image_file_alt;

    return $variables;
  }

}
