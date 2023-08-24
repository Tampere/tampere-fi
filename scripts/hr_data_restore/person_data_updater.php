<?php

/**
 * @file
 * This file reads updates nodes based on files generated by another script.
 *
 * Running this is as simple as `drush scr <full_path_to_file>`. The JSON files
 * are expected to be generated by person_data_gatherer. For this script to make
 * any changes, it is expected that the person_data_gatherer script be run on an
 * earlier backup of the DB and that this script is run against a fresh or live
 * DB.
 */

use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\node\NodeInterface;

function update_data_with_hr_id() {
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $json_text = file_get_contents(__DIR__ . "/partial_nodes_{$langcode}.txt");
    $json = json_decode($json_text, TRUE);

    $json = (array) $json;
    echo "JSON count for {$langcode}: ", count($json), PHP_EOL;

    if (empty($json)) {
      echo "JSON empty for {$langcode}, skipping.", PHP_EOL;
      continue;
    }

    foreach ($json as $hr_id => $data) {
      $query = $st->getQuery()->accessCheck(FALSE)
        ->condition('type', 'person')
        ->condition('langcode', $langcode)
        ->condition('default_langcode', 1)
        ->condition('field_hr_id', $hr_id, '=', $langcode);

      $result = $query->execute();

      $original_nodes = $st->loadMultiple($result);
      $original_nodes_as_arrays = array_map(function (NodeInterface $node) {
        $translation_languages = $node->getTranslationLanguages();
        $translations = [];
        foreach ($translation_languages as $lang) {
          $translations[$lang->getId()] = $node->getTranslation($lang->getId())->toArray();
        }
        return $translations;
      }, $original_nodes);

      $nodes = [];

      foreach ($result as $nid) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $st->load($nid);
        $node = $node->getTranslation($langcode);

        if (isset($data[$langcode])) {
          $nodes[$nid] = update_hr_node_data($node, $data[$langcode]);
        }

        if (isset($data['translations'])) {
          foreach ($data['translations'] as $translation_langcode => $field_data) {
            if (!$node->hasTranslation($translation_langcode)) {
              $nodes[$nid] = $node->addTranslation($translation_langcode, $field_data);
            }
            else {
              $translation = $node->getTranslation($translation_langcode);
              $nodes[$nid] = update_hr_node_data($translation, $field_data);
            }
          }
        }
      }

      foreach ($original_nodes_as_arrays as $original_node_nid => $original_node_array) {
        $original_serialized = serialize($original_node_array[$langcode]);
        $langcode_candidate = $langcode == 'fi' ? 'en' : 'fi';
        $original_translation_serialized = '';
        $new_translation_serialized = '';
        if (array_key_exists($langcode_candidate, $original_node_array)) {
          $original_translation_serialized = serialize($original_node_array[$langcode_candidate]);
        }
        $new_serialized = serialize($nodes[$original_node_nid]->toArray());

        if ($nodes[$original_node_nid]->hasTranslation($langcode_candidate)) {
          $translation = $nodes[$original_node_nid]->getTranslation($langcode_candidate);
          $new_translation_serialized = serialize($translation->toArray());
        }

        $hr_id = $nodes[$original_node_nid]->get('field_hr_id')->getString();
        $uuid = $nodes[$original_node_nid]->uuid();
        $label = $nodes[$original_node_nid]->label();
        if ($original_serialized != $new_serialized) {
          echo "Saved node with hr_id {$hr_id} ({$langcode}) (node {$original_node_nid}) ({$label}) (uuid: {$uuid}", PHP_EOL;
          $nodes[$original_node_nid]->save();
        }
        else {
          echo "Skipped saving node with hr_id {$hr_id} ({$langcode}) (node {$original_node_nid}) ({$label})", PHP_EOL;
        }
        if ($original_translation_serialized != $new_translation_serialized) {
          if ($nodes[$original_node_nid]->hasTranslation($langcode_candidate)) {
            $label = $translation->label();
            echo "Saved node translation (existing node) ({$langcode_candidate}) with hr_id {$hr_id} ({$langcode}) (node {$original_node_nid}) ({$label}) (uuid: {$uuid})", PHP_EOL;
            $translation->save();
          }
        }
      }
    }
  }

}

function update_data_without_hr_id() {
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $json_text = file_get_contents(__DIR__ . "/full_nodes_{$langcode}.txt");
    $json = json_decode($json_text, TRUE);

    $json = (array) $json;
    echo "Full node count in {$langcode}: ", count($json), PHP_EOL;

    if (empty($json)) {
      echo "JSON empty for {$langcode}, skipping.", PHP_EOL;
      continue;
    }

    foreach ($json as $node_data) {
      $node_title = $node_data[$langcode]['title'][0]['value'];
      $query = $st->getQuery()->accessCheck(FALSE)
        ->condition('title', $node_title);

      $result = $query->execute();

      if (count($result) > 0) {
        echo "Existing node(s) found for {$node_title} (", implode(", ", $result), "); skipping...", PHP_EOL;
        continue;
      }
      $translations = [];

      if (isset($node_data[$langcode])) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $st->create($node_data[$langcode]);
        if (isset($node_data['translations'])) {
          foreach ($node_data['translations'] as $translation_langcode => $translated_data) {
            $node->addTranslation($translation_langcode, $translated_data);
          }
          $translations[$translation_langcode] = $translation_langcode;
        }
      }
      elseif (isset($node_data['translations'])) {
        $counter = 0;
        foreach ($node_data['translations'] as $translation_langcode => $translation_data) {
          if ($counter == 0) {
            $node = $st->create($translation_data);
          }
          else {
            $translation = $node->addTranslation($translation_langcode, $translation_data);
          }
          $counter++;
        }
      }

      $node->setPublished();
      $node->save();
      echo "Saved new node, ", $node->label(), ", nid: ", $node->id(), PHP_EOL;
      foreach ($node->getTranslationLanguages(FALSE) as $translation_language) {
        $translation_langcode = $translation_language->getId();
        $translation = $node->getTranslation($translation_langcode);
        $translation->setPublished()->save();
        echo "Saved translation ({$translation_langcode}) ", $node->label(), ", nid: ", $node->id(), PHP_EOL;
      }
    }
  }
}

function update_hr_node_data(NodeInterface $node, array $new_data) {
  foreach ($new_data as $field_name => $value) {
    try {
      $node->get($field_name)->setValue($value);
    }
    catch (ReadOnlyException $e) {
      // Nothing to do.
    }
  }

  $node->setPublished();

  return $node;
}

update_data_with_hr_id();
update_data_without_hr_id();
