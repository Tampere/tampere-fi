<?php

namespace Drupal\tre_contact_search\Plugin\search_api\processor;

use Drupal\node\NodeInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds all language translations of the HR title to the search index.
 *
 * The HR title is a taxonomy term (field_hr_title) that may have translations.
 * By default, the index only stores the term name in the language of the indexed
 * node item. For Finnish-only persons this means only the Finnish title is
 * indexed. This processor runs at preprocess_index and appends the names from
 * all available translations of the HR title term to the 'name' field so that
 * the person can be found regardless of which language the visitor searches in.
 *
 * @SearchApiProcessor(
 *   id = "tre_hr_title_multilingual_indexer",
 *   label = @Translation("Tampere HR Title Multilingual Indexer"),
 *   description = @Translation("Adds all available language translations of the HR title taxonomy term to the index so persons can be found in any language."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   }
 * )
 */
class HrTitleMultilingualIndexer extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    foreach ($items as $item) {
      $entity = $item->getOriginalObject()->getValue();

      if (!$entity instanceof NodeInterface || $entity->bundle() !== 'person') {
        continue;
      }

      if ($entity->get('field_hr_title')->isEmpty()) {
        continue;
      }

      /** @var \Drupal\taxonomy\TermInterface|null $term */
      $term = $entity->get('field_hr_title')->entity;

      if (!$term) {
        continue;
      }

      $name_field = $item->getField('name');
      if (!$name_field) {
        continue;
      }

      $values = $name_field->getValues();

      // Append the term name from every available translation so a search in
      // any language can match this person's title.
      $existing_strings = array_map(
        fn($v) => $v instanceof TextValue ? $v->getText() : (string) $v,
        $values
      );
      foreach ($term->getTranslationLanguages() as $langcode => $language) {
        $translated_name = $term->getTranslation($langcode)->getName();
        if (!in_array($translated_name, $existing_strings, TRUE)) {
          $values[] = new TextValue($translated_name);
        }
      }

      $name_field->setValues($values);
    }
  }

}
