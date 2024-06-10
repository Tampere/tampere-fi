<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\Core\Entity\EntityInterface;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Collection page content pattern preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.pattern_collection_page_content",
 *   hook = "pattern_collection_page_content"
 * )
 */
class CollectionPageContentPattern extends TrePreProcessPluginBase {

  /**
   * The icon field names to use.
   */
  const AVAILABLE_ICON_FIELDS = [
    'field_life_situation_icon',
    'field_topic_icon',
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $pattern_context = $variables['context'];

    $bundle = $pattern_context->getProperty('bundle');
    $node = $pattern_context->getProperty('entity');

    if ($node instanceof NodeInterface && $bundle == 'collection_page' && !$node->isNew()) {
      /** @var \Drupal\node\NodeInterface $translated_node */
      $translated_node = $this->entityRepository->getTranslationFromContext($node);

      $group_contents_for_node = GroupRelationship::loadByEntity($translated_node);
      $node_belongs_to_group = count($group_contents_for_node) > 0;
      if ($node_belongs_to_group) {
        /** @var \Drupal\group\Entity\GroupRelationshipInterface $group_content */
        $group_content = reset($group_contents_for_node);
        if ($group_content instanceof GroupRelationshipInterface) {
          $group = $group_content->getGroup();

          if ($group instanceof EntityInterface) {
            /** @var \Drupal\group\Entity\GroupRelationshipInterface $translated_group */
            $translated_group = $this->entityRepository->getTranslationFromContext($group);

            foreach (self::AVAILABLE_ICON_FIELDS as $field_name) {
              if ($translated_group->hasField($field_name) && !$translated_group->get($field_name)->isEmpty()) {
                $variables['hero_icon_name'] = $translated_group->get($field_name)->getString();

                $group_id = $translated_group->id();
                $variables['#cache']['tags'][] = "group:{$group_id}";
              }
            }
          }
        }
      }
    }

    return $variables;
  }

}
