<?php

namespace Drupal\tre_contact_search\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Removes duplicate language variants of person nodes from query results.
 *
 * Most person nodes only have a Finnish translation. To ensure Finnish-only
 * persons still appear in the English-language contact search, the index
 * language filter is configured to return both the current-interface-language
 * variant and the site default (Finnish) as a fallback. This means a person
 * with an English translation produces two result items — one per language —
 * which would cause them to appear twice. This processor deduplicates those
 * results at query time: for each node ID that appears in multiple language
 * variants, it keeps the current-interface-language version and discards the
 * rest. If no current-language variant exists (i.e. no translation available),
 * the default-language (Finnish) version is kept instead.
 *
 * @SearchApiProcessor(
 *   id = "tre_person_language_deduplicator",
 *   label = @Translation("Tampere Person Language Deduplicator"),
 *   description = @Translation("Removes duplicate language variants of person nodes, keeping the current-language version or falling back to the default language."),
 *   stages = {
 *     "postprocess_query" = 0,
 *   }
 * )
 */
class PersonLanguageDeduplicator extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(\Drupal\search_api\Query\ResultSetInterface $results) {
    $query = $results->getQuery();

    // Only act on queries for the contacts_search index.
    if ($query->getIndex()->id() !== 'contacts_search') {
      return;
    }

    $current_langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Group result items by node ID, mapping langcode => item.
    // Item IDs are in the form "entity:node/NID:LANGCODE".
    $by_nid = [];
    foreach ($results->getResultItems() as $item_id => $item) {
      $parts = explode('/', $item_id, 2);
      if (empty($parts[1])) {
        continue;
      }
      [$nid, $langcode] = array_pad(explode(':', $parts[1], 2), 2, NULL);
      if (!$nid) {
        continue;
      }
      $by_nid[$nid][$langcode] = $item_id;
    }

    $to_remove = [];
    foreach ($by_nid as $nid => $variants) {
      if (count($variants) <= 1) {
        continue;
      }
      // Prefer the current-interface-language variant, otherwise keep the first.
      $keep_langcode = array_key_exists($current_langcode, $variants)
        ? $current_langcode
        : array_key_first($variants);

      foreach ($variants as $langcode => $item_id) {
        if ($langcode !== $keep_langcode) {
          $to_remove[] = $item_id;
        }
      }
    }

    if (empty($to_remove)) {
      return;
    }

    $items = $results->getResultItems();
    foreach ($to_remove as $item_id) {
      unset($items[$item_id]);
    }
    $results->setResultItems($items);
    $results->setResultCount(count($items));
  }

}
