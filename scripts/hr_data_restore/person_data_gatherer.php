<?php

/**
 * @file
 * This file creates JSON files of person nodes with and without field_hr_id.
 *
 * Running this is as simple as `drush scr <full_path_to_file>`. The JSON files
 * will get generated alongside this script in the same directory.
 */

use Drupal\node\NodeInterface;

/**
 * Generates a file per language for person nodes with field_hr_id populated.
 */
function get_data_with_hr_id() {
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {

    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->condition('status', NodeInterface::PUBLISHED, '=', $langcode)
      ->exists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->sort('changed', 'ASC', $langcode);

    $published_person_nids = $query->execute();

    $result = [];
    foreach (array_chunk($published_person_nids, 100) as $chunk) {
      foreach ($chunk as $nid) {
        /** @var \Drupal\node\NodeInterface $person */
        $person = $st->load($nid);
        $person_data = NULL;
        $person_translation_fi_to_en = NULL;
        $person_translation_en_to_fi = NULL;

        if ($person->hasTranslation($langcode)) {
          /** @var \Drupal\node\NodeInterface $person */
          $person = $person->getTranslation($langcode);
        }
        else {
          continue;
        }

        $person_data = get_field_data($person);
        if ($langcode == 'fi' && $person->isDefaultTranslation() && $person->hasTranslation('en')) {
          $person_translation_fi_to_en = get_field_data($person->getTranslation('en'));
        }

        if ($langcode == 'en' && $person->isDefaultTranslation() && $person->hasTranslation('fi')) {
          $person_translation_en_to_fi = get_field_data($person->getTranslation('fi'));
        }
        if (is_null($person_data) && is_null($person_translation_fi_to_en) && is_null($person_translation_en_to_fi)) {
          continue;
        }
        if (!is_null($person_data)) {
          $result[$person->get('field_hr_id')
            ->getString()][$langcode] = $person_data;
        }
        if (!is_null($person_translation_fi_to_en)) {
          $result[$person->get('field_hr_id')
            ->getString()]['translations']['en'] = $person_translation_fi_to_en;
        }
        if (!is_null($person_translation_en_to_fi)) {
          $result[$person->get('field_hr_id')
            ->getString()]['translations']['fi'] = $person_translation_en_to_fi;
        }
      }
    }

    $filename = "partial_nodes_{$langcode}.txt";
    echo "Outputting ", count($result), " items into {$filename}...", PHP_EOL;
    file_put_contents(__DIR__ . "/{$filename}", json_encode($result, JSON_PRETTY_PRINT));
    $translation_count = array_filter($result, function ($item) {
      return array_key_exists('translations', $item);
    });
    echo "$langcode has ", count($translation_count), " translations.", PHP_EOL;
  }
}

/**
 * Generates a file per language for person nodes with field_hr_id ubpopulated.
 */
function get_data_without_hr_id() {
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {

    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->condition('status', NodeInterface::PUBLISHED, '=', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->notExists('field_hr_id', $langcode)
      ->sort('changed', 'ASC', $langcode);

    $published_person_nids = $query->execute();

    $result = [];
    foreach ($published_person_nids as $nid) {
      /** @var \Drupal\node\NodeInterface $person */
      $person = $st->load($nid);

      $person_data = NULL;
      $person_translation_fi_to_en = NULL;
      $person_translation_en_to_fi = NULL;

      if ($person->hasTranslation($langcode)) {
        /** @var \Drupal\node\NodeInterface $person */
        $person = $person->getTranslation($langcode);
      }
      else {
        continue;
      }

      $person_data = get_manual_field_data($person);
      if ($langcode == 'fi' && $person->isDefaultTranslation() && $person->hasTranslation('en')) {
        $person_translation_fi_to_en = get_manual_field_data($person->getTranslation('en'));
      }

      if ($langcode == 'en' && $person->isDefaultTranslation() && $person->hasTranslation('fi')) {
        $person_translation_en_to_fi = get_manual_field_data($person->getTranslation('fi'));
      }
      if (is_null($person_data) && is_null($person_translation_fi_to_en) && is_null($person_translation_en_to_fi)) {
        continue;
      }
      if (!is_null($person_data)) {
        $result[$nid][$langcode] = $person_data;
      }
      if (!is_null($person_translation_fi_to_en)) {
        $result[$nid]['translations']['en'] = $person_translation_fi_to_en;
      }
      if (!is_null($person_translation_en_to_fi)) {
        $result[$nid]['translations']['fi'] = $person_translation_en_to_fi;
      }
    }

    $filename = "full_nodes_{$langcode}.txt";
    echo "Outputting ", count($result), " items into {$filename}...", PHP_EOL;
    file_put_contents(__DIR__ . "/{$filename}", json_encode($result, JSON_PRETTY_PRINT));
    $translation_count = array_filter($result, function ($item) {
      return array_key_exists('translations', $item);
    });
    echo "$langcode has ", count($translation_count), " translations.", PHP_EOL;
  }
}

function get_manual_field_data(NodeInterface $node) {
  $fields = $node->getFields(FALSE);
  $manual_fields = array_filter(array_keys($fields), function ($field_name) {
    return str_starts_with($field_name, 'field_');
  });

  $extra_fields = [
    'langcode',
    'migrate_override_data',
    'published_at',
    'title',
    'uid',
    'type',
  ];

  return get_field_data($node, array_merge($manual_fields, $extra_fields));
}
function get_field_data(NodeInterface $node, $fields = NULL) {
  if (is_null($fields)) {
    $fields = [
      'field_topics',
      'field_search_keywords',
      'field_image',
      'field_additional_phones',
      'field_additional_information',
      'field_keywords',
      'field_address_street',
      'field_address_postal',
      'field_phone_supplementary',
      'field_place',
      'migrate_override_data',
    ];

    $overridden_fields_value = $node->get('migrate_override_data')->getString();
    if (!empty($overridden_fields_value)) {
      $overridden_fields = unserialize($overridden_fields_value);
      foreach (['field_hr_title', 'field_first_names', 'field_last_name'] as $overrideable_field) {
        if (array_key_exists($overrideable_field, $overridden_fields) && $overridden_fields[$overrideable_field]) {
          $fields[] = $overrideable_field;
        }
      }
    }
  }

  $result = [];
  $all_fields_empty = TRUE;
  foreach ($fields as $field) {
    $field_item_list = $node->get($field);
    $type = $field_item_list->getFieldDefinition()->getType();
    $value = NULL;
    $field_is_empty = FALSE;
    switch ($type) {
      case 'boolean':
      case 'datetime':
      case 'email':
      case 'entity_reference':
      case 'language':
      case 'published_at':
      case 'string':
      case 'telephone':
      case 'text_long':
        $field_is_empty = $field_item_list->isEmpty();
        $value = $field_item_list->getValue();
        break;

      case 'address':
        foreach ($field_item_list as $field_item) {
          $address_empty = empty(trim($field_item->get('address_line1')->getString()));
          $postal_empty = empty(trim($field_item->get('postal_code')->getString()));
          $locality_empty = empty(trim($field_item->get('locality')->getString()));
          $field_is_empty = $address_empty && $postal_empty && $locality_empty;
        }
        $value = $field_item_list->getValue();
        break;

      case 'migrate_override_field_item':
        $default_value = $field_item_list->getFieldDefinition()->getDefaultValue($node);
        $value = $field_item_list->getValue();
        $default_value_value = unserialize($default_value[0]['value']);
        $value_value = unserialize($value[0]['value']);

        $individual_empty = TRUE;
        foreach ($default_value_value as $fieldname => $override_value) {
          // There are old saved values in the DB from a time when
          // field_hr_title was not an option to be overridden yet. That's why
          // we need to check each override option individually.
          $individual_empty = !array_key_exists($fieldname, $value_value) || $value_value[$fieldname] == $override_value;
          if (!$individual_empty) {
            break;
          }
        }

        $field_is_empty = $individual_empty;
        break;

      case 'telephone_plus_field':
        $field_is_empty = $field_item_list->isEmpty();
        // Most of the time when isEmpty is true the list is empty as well,
        // but not always, so double-checking for non-empty subfield values.
        foreach ($field_item_list as $field_item) {
          $field_is_empty = $field_is_empty && empty($field_item->get('telephone_supplementary'));
          $field_is_empty = $field_is_empty && empty($field_item->get('telephone_title'));
        }
        $value = $field_item_list->getValue();
        break;

    }
    $all_fields_empty = $all_fields_empty && $field_is_empty;

    if (!$field_is_empty) {
      $result[$field] = $value;
    }
    else {
      unset($result[$field]);
    }
  }

  if ($all_fields_empty) {
    return NULL;
  }

  return $result;
}

get_data_without_hr_id();
get_data_with_hr_id();
