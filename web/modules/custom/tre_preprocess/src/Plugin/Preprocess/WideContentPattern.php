<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Wide content pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_wide_content",
 *   hook = "pattern_wide_content"
 * )
 */
class WideContentPattern extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];
    $node = $pattern_context->getProperty('entity');

    if ($node instanceof NodeInterface) {
      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);

      // Check if we are using the Wave Hero layout
      $is_wave_hero = $translated_node->hasField('field_is_main_front_page') && (bool) $translated_node->get('field_is_main_front_page')->value;

      // Pass this flag to Twig so we can do {% if is_wave_hero %}
      $variables['is_wave_hero'] = $is_wave_hero;

      if ($is_wave_hero) {
        // Process the Fixed CTA Link
        if ($translated_node->hasField('field_main_hero_target') && !$translated_node->get('field_main_hero_target')->isEmpty()) {
          $link_item = $translated_node->get('field_main_hero_target')->first();
          try {
            $variables['cta_link_url'] = $link_item->getUrl()->toString();
            $variables['cta_link_text'] = $link_item->title;
          } catch (\Exception $e) {
          }
        }

        // Process the News Links
        $extracted_news_links = [];
        if ($translated_node->hasField('field_hero_news_links') && !$translated_node->get('field_hero_news_links')->isEmpty()) {

          foreach ($translated_node->get('field_hero_news_links')->referencedEntities() as $paragraph) {
            $link_url = '';
            $link_text = '';
            $icon_name = 'arrow';

            if ($this->helperFunctions->isInternalLinkParagraph($paragraph)) {
              $details = $this->helperFunctions->getInternalLinkParagraphContents($paragraph);
              if ($details) {
                [$link_url, $node_id, $link_text] = $details;
              }
            } else {
              $details = $this->helperFunctions->getExternalLinkParagraphContents($paragraph);
              if ($details) {
                [$link_url, $link_text] = $details;
              }
              
              if ($paragraph->bundle() === 'login_link_with_text') {
                $icon_name = 'service-arrow-thick'; 
              } else {
                $icon_name = 'external';
              }
            }

            if (!empty($link_url)) {
              $extracted_news_links[] = [
                'url' => $link_url,
                'text' => $link_text,
                'icon' => $icon_name, 
              ];
            }
          }
        }

        $variables['extracted_news_links'] = $extracted_news_links;

        // Override the Main Image with the "Wave Hero" view mode
        if ($translated_node->hasField('field_main_image') && !$translated_node->get('field_main_image')->isEmpty()) {
          $media_entity = $translated_node->get('field_main_image')->entity;

          if ($media_entity) {
            $view_builder = \Drupal::entityTypeManager()->getViewBuilder('media');
            $variables['main_image'] = $view_builder->view($media_entity, 'hero_wave');
          }
        }
      } else {
        // Process the Paragraph link (field_main_image_link)
        if ($translated_node->hasField('field_main_image_link') && !$translated_node->get('field_main_image_link')->isEmpty()) {
          /** @var \Drupal\paragraphs\ParagraphInterface $link_paragraph */
          $link_paragraph = $translated_node->get('field_main_image_link')->entity;

          $is_internal_link_paragraph = $this->helperFunctions->isInternalLinkParagraph($link_paragraph);

          $link_url = '';
          $link_text = '';
          if ($is_internal_link_paragraph) {
            $variables['cta_link_is_internal'] = TRUE;
            $internal_link_details = $this->helperFunctions->getInternalLinkParagraphContents($link_paragraph);

            if ($internal_link_details) {
              [$link_url, $node_id, $link_text] = $internal_link_details;
              $variables['#cache']['tags'][] = "node:{$node_id}";
            }
          } else {
            [$link_url, $link_text] = $this->helperFunctions->getExternalLinkParagraphContents($link_paragraph);

            $link_paragraph_bundle = $link_paragraph->bundle();
            if ($link_paragraph_bundle == 'login_link_with_text') {
              $variables['cta_link_requires_login'] = TRUE;
            }
          }

          if (!empty($link_url)) {
            $variables['cta_link_url'] = $link_url;
            $variables['cta_link_text'] = $link_text;
          }
        }
      }
    }

    return $variables;
  }
}
