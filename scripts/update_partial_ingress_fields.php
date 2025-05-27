<?php

use Drupal\node\Entity\Node;

/**
 * Update partial ingress fields.
 */
function update_partial_ingress_fields() {
  // Resave certain content types to update the partial ingress fields.
  $entity_type_manager = \Drupal::entityTypeManager();
  $query = $entity_type_manager->getStorage('node')->getQuery();
  $fields_condition = $query->orConditionGroup();
  $fields_condition->condition('field_lead', NULL, 'IS NOT NULL');
  $fields_condition->condition('field_description', NULL, 'IS NOT NULL');
  $node_types = ['blog_article', 'small_news_item', 'rich_article', 'news_item', 'city_planning_and_constructions', 'zoning_information', 'comprehensive_plan', 'project', 'organization', 'place'];
  $query->condition('type', $node_types, 'IN')
    ->condition('status', 1)
    ->condition($fields_condition)
    ->accessCheck(FALSE);
  $nids = $query->execute();

  echo 'Updating ' . count($nids) . ' nodes.' . PHP_EOL;

  $nodes = Node::loadMultiple($nids);

  foreach ($nodes as $node) {
    try {
      $language = $node->language()->getId();
      $translation_language = $language === 'fi' ? 'en' : 'fi';
      $node->save();
      if ($node->hasTranslation($translation_language)) {
        $translation = $node->getTranslation($translation_language);
        $translation->save();
      }

      echo "Saved " . $node->id() . PHP_EOL;
    }
    catch (Exception $e) {
      echo "Couldn't save node " . $node->id() . PHP_EOL;
    }
  }

  echo 'Done updating' . PHP_EOL;
}

update_partial_ingress_fields();
