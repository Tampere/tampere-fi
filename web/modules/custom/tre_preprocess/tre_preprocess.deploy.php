<?php

/**
 * @file
 * Deploy hook implementations for tre_preprocess.
 */

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Moves old links to new link field in 'Balanced content liftup' paragraphs.
 */
function tre_preprocess_deploy_0001_balanced_content_liftup_new_link_field_automation(&$sandbox) {
  $liftup_paragraph_type = 'balanced_content_liftup';
  $link_paragraph_type = 'internal_link';

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $node_query = $node_storage->getQuery();
  $node_query
    ->accessCheck(FALSE)
    ->condition('field_paragraphs.entity.type', $liftup_paragraph_type);
  $node_ids = $node_query->execute();
  $node_id_chunks = array_chunk($node_ids, 50);

  foreach ($node_id_chunks as $chunk) {
    $nodes_to_edit = $node_storage->loadMultiple($chunk);
    foreach ($nodes_to_edit as $node) {
      if (!($node instanceof NodeInterface)) {
        continue;
      }

      $parent_node_id = $node->id();

      foreach ($node->getTranslationLanguages() as $langcode => $language) {
        if (!$node->hasTranslation($langcode)) {
          continue;
        }

        $translated_node = $node->getTranslation($langcode);

        if (!($translated_node instanceof NodeInterface)) {
          continue;
        }

        $referenced_paragraphs = $translated_node->get('field_paragraphs')->referencedEntities();

        foreach ($referenced_paragraphs as $referenced_paragraph) {
          if (!($referenced_paragraph instanceof ParagraphInterface)) {
            continue;
          }

          if ($referenced_paragraph->bundle() !== $liftup_paragraph_type) {
            continue;
          }

          if (!$referenced_paragraph->hasTranslation($langcode)) {
            continue;
          }

          $translated_paragraph = $referenced_paragraph->getTranslation($langcode);

          if (!($translated_paragraph instanceof ParagraphInterface)) {
            continue;
          }

          if (!$translated_paragraph->hasField('field_content_liftups') || $translated_paragraph->get('field_content_liftups')->isEmpty()) {
            continue;
          }

          $paragraph_id = $referenced_paragraph->id();

          $referenced_content_liftups = $translated_paragraph->get('field_content_liftups')->referencedEntities();

          foreach ($referenced_content_liftups as $referenced_content_liftup) {
            if (!($referenced_content_liftup instanceof ParagraphInterface)) {
              continue;
            }

            if (!$referenced_content_liftup->hasTranslation($langcode)) {
              continue;
            }

            $translated_content_liftup = $referenced_content_liftup->getTranslation($langcode);

            if (!$translated_content_liftup->hasField('field_content_link') || !$translated_content_liftup->hasField('field_internal_content_link')) {
              continue;
            }

            $referenced_content_liftup_id = $referenced_content_liftup->id();

            if (!$translated_content_liftup->get('field_internal_content_link')->isEmpty()) {
              $notice = "(Parent node {$parent_node_id}) Paragraph {$paragraph_id} ({$langcode}) already contains content in the new link field in liftup {$referenced_content_liftup_id}. Skipping liftup.";
              \Drupal::logger('tre_preprocess_migration')->notice($notice);
              continue;
            }

            $old_field_value = $translated_content_liftup->get('field_content_link')->referencedEntities();
            $old_field_link_paragraph = reset($old_field_value);

            if (!($old_field_link_paragraph instanceof ParagraphInterface)) {
              continue;
            }

            if (!$old_field_link_paragraph->hasTranslation($langcode)) {
              continue;
            }

            $translated_old_field_link_paragraph = $old_field_link_paragraph->getTranslation($langcode);

            if ($translated_old_field_link_paragraph->bundle() !== $link_paragraph_type) {
              $notice = "(Parent node {$parent_node_id}) Paragraph {$paragraph_id} ({$langcode}) contains an external link in an old link field in liftup {$referenced_content_liftup_id}. Skipping liftup.";
              \Drupal::logger('tre_preprocess_migration')->notice($notice);
              continue;
            }

            if (!$translated_old_field_link_paragraph->hasField('field_internal_link') || $translated_old_field_link_paragraph->get('field_internal_link')->isEmpty()) {
              continue;
            }

            $old_internal_link_referenced_entities = $translated_old_field_link_paragraph->get('field_internal_link')->referencedEntities();
            $old_internal_link_referenced_entity = reset($old_internal_link_referenced_entities);

            $new_field_link_paragraph = Paragraph::create([
              'type' => $link_paragraph_type,
              'langcode' => $langcode,
              'field_internal_link' => $old_internal_link_referenced_entity,
            ]);
            $new_field_link_paragraph->save();

            $new_link_paragraph_referenced_entity = [
              'target_id' => $new_field_link_paragraph->id(),
              'target_revision_id' => $new_field_link_paragraph->getRevisionId(),
            ];

            $translated_content_liftup->set('field_internal_content_link', $new_link_paragraph_referenced_entity);
            $translated_content_liftup->save();
          }

          $translated_paragraph->save();

          $info = "(Parent node {$parent_node_id}) Balanced content liftup paragraph {$paragraph_id} ({$langcode}) processed. Old link field values moved to new link field.";
          \Drupal::logger('tre_preprocess_migration')->info($info);
        }
      }
    }

  }
}
