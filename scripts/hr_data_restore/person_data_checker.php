<?php

function check_data_with_hr_id() {
  echo "Statistics for data WITH field_hr_id:", PHP_EOL;
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $json_text = file_get_contents(__DIR__ . "/partial_nodes_{$langcode}.txt");
    $json = json_decode($json_text, TRUE);

    $json = (array) $json;
    echo "JSON count for enriched {$langcode}: ", count($json), PHP_EOL;

    if (!empty($json)) {
      $query = $st->getQuery()->accessCheck(FALSE)
        ->condition('type', 'person')
        ->condition('langcode', $langcode)
        ->condition('default_langcode', 1)
        ->condition('field_hr_id', array_keys($json), 'IN', $langcode);

      $result = $query->execute();

      $nodes = [];

      foreach ($result as $nid) {
        $node = $st->load($nid);
        $nodes[$node->get('field_hr_id')
          ->getString()] = $node->get('field_hr_id')
          ->getString();
      }

      echo "Query count for {$langcode}: ", count($result), PHP_EOL;
      echo "Unduplicated count for {$langcode}: ", count($nodes), PHP_EOL;
    }
  }
}

function check_data_without_hr_id() {
  echo "Statistics for data WITHOUT field_hr_id:", PHP_EOL;
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $json_text = file_get_contents(__DIR__ . "/full_nodes_{$langcode}.txt");
    $json = json_decode($json_text, TRUE);

    $json = (array) $json;
    echo "JSON count for full nodes in {$langcode}: ", count($json), PHP_EOL;

    $full_names = array_map(function ($item) use ($langcode) {
      return $item[$langcode]['title'][0]['value'];
    }, $json);

    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->notExists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->condition('title', $full_names, 'NOT IN', $langcode);

    echo "Full node count after query in {$langcode}: ", $query->count()->execute(), PHP_EOL;
  }
}

function check_node_data_with_hr_id() {
  echo "Statistics of published nodes WITH field_hr_id", PHP_EOL;
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->exists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->condition('status', 1);

    echo "Enricheable PUBLISHED node count in DB in {$langcode}: ", $query->count()->execute(), PHP_EOL;

    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->exists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->condition('status', 0);

    echo "Enricheable UNPUBLISHED node count in DB in {$langcode}: ", $query->count()->execute(), PHP_EOL;
  }
}

function check_node_data_without_hr_id() {
  echo "Statistics of published nodes WITHOUT field_hr_id", PHP_EOL;
  $st = \Drupal::entityTypeManager()->getStorage('node');

  foreach (['fi', 'en'] as $langcode) {
    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->notExists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->condition('status', 1);

    echo "PUBLISHED hr_id'less node count in DB in {$langcode}: ", $query->count()->execute(), PHP_EOL;

    $query = $st->getQuery()->accessCheck(FALSE)
      ->condition('type', 'person')
      ->notExists('field_hr_id', $langcode)
      ->condition('langcode', $langcode)
      ->condition('default_langcode', 1)
      ->condition('status', 0);

    echo "UNPUBLISHED hr_id'less node count in DB in {$langcode}: ", $query->count()->execute(), PHP_EOL;
  }
}

check_data_with_hr_id();
check_data_without_hr_id();
check_node_data_with_hr_id();
check_node_data_without_hr_id();
