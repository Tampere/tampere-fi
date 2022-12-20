<?php

/**
 * @file
 * Provides Token integration for TRE Custom Paths.
 */

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\node\NodeInterface;

/**
 * Implements hook_token_info().
 */
function tre_custom_paths_token_info() {
  $info = [];

  // Token type "tre".
  $info['types']['tre'] = [
    'name' => t('TRE tokens'),
    'description' => t('TRE custom tokens.'),
  ];

  // Tokens for type "tre".
  $info['tokens']['tre'] = [
    'node_group_frontpage_title' => [
      'name' => t("Node group frontpage title"),
      'description' => t("The title of the frontpage of the node's associated group"),
    ],
    'node_theme_topic_frontpage_title' => [
      'name' => t("Node theme group's topic frontpage title"),
      'description' => t("The title of the frontpage of the topic group of the node's associated theme group"),
    ],
    'node_theme_life_situation_frontpage_title' => [
      'name' => t("Node theme group's life situation frontpage title"),
      'description' => t("The title of the frontpage of the life situation group of the node's associated theme group"),
    ],
    'node_subsite_theme_frontpage' => [
      'name' => t("Node subsite group's theme frontpage title"),
      'description' => t("The title of the frontpage of the theme group of the node's associated subsite group"),
    ],
    'node_subsite_topic_frontpage' => [
      'name' => t("Node subsite group's topic frontpage title"),
      'description' => t("The title of the frontpage of the topic group of the node's associated subsite group"),
    ],
    'node_subsite_life_situation_frontpage' => [
      'name' => t("Node subsite group's life situation frontpage title"),
      'description' => t("The title of the frontpage of the life situation group of the node's associated subsite group"),
    ],
    'node_subsite_theme_topic_frontpage' => [
      'name' => t("Node subsite group's theme topic frontpage title"),
      'description' => t("The title of the frontpage of the topic group of the theme group of the node's associated subsite group"),
    ],
    'node_subsite_theme_life_situation_frontpage' => [
      'name' => t("Node subsite group's theme life situation frontpage title"),
      'description' => t("The title of the frontpage of the life situation group of the theme group of the node's associated subsite group"),
    ],
    'group_content_menu_parents' => [
      'name' => t("Node's group menu parent link labels"),
      'description' => t("The link labels of each parent item of the menu item representing the node in the group menu"),
    ],
  ];

  // Return them.
  return $info;
}

/**
 * Implements hook_tokens().
 */
function tre_custom_paths_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'tre') {

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    $node = $data['node'];

    if (!($node instanceof NodeInterface)) {
      return $replacements;
    }

    $group_contents = GroupContent::loadByEntity($node);
    if (count($group_contents) < 1) {
      return $replacements;
    }

    $first_group_content = current($group_contents);
    $bubbleable_metadata->addCacheableDependency($first_group_content);

    $group = $first_group_content->getGroup();
    $bubbleable_metadata->addCacheableDependency($group);

    foreach ($tokens as $name => $original) {

      switch ($name) {
        case 'node_group_frontpage_title':
          $group_front_page = current($group->get('field_front_page')
            ->referencedEntities());
          if ($group_front_page instanceof EntityInterface) {
            $bubbleable_metadata->addCacheableDependency($group_front_page);
            $translated_front_page = $entity_repository->getTranslationFromContext($group_front_page);
            $replacements[$original] = $translated_front_page->label();
          }
          break;

        case 'node_theme_topic_frontpage_title':
          if ($group->bundle() === 'theme') {
            $topic_group = current($group->get('field_theme_topic')->referencedEntities());
            if ($topic_group instanceof GroupInterface) {
              $bubbleable_metadata->addCacheableDependency($topic_group);
              if (!$topic_group->get('field_front_page')->isEmpty()) {
                $topic_front_page = current($topic_group->get('field_front_page')
                  ->referencedEntities());
                if ($topic_front_page instanceof EntityInterface) {
                  $bubbleable_metadata->addCacheableDependency($topic_front_page);
                  $translated_front_page = $entity_repository->getTranslationFromContext($topic_front_page);
                  $replacements[$original] = $translated_front_page->label();
                }
              }
            }
          }
          break;

        case 'node_theme_life_situation_frontpage_title':
          if ($group->bundle() === 'theme') {
            $life_situation_group = current($group->get('field_theme_life_situation')->referencedEntities());
            if ($life_situation_group instanceof GroupInterface) {
              $bubbleable_metadata->addCacheableDependency($life_situation_group);
              if (!$life_situation_group->get('field_front_page')->isEmpty()) {
                $life_situation_front_page = current($life_situation_group->get('field_front_page')
                  ->referencedEntities());
                if ($life_situation_front_page instanceof EntityInterface) {
                  $bubbleable_metadata->addCacheableDependency($life_situation_front_page);
                  $translated_front_page = $entity_repository->getTranslationFromContext($life_situation_front_page);
                  $replacements[$original] = $translated_front_page->label();
                }
              }
            }
          }
          break;

        case 'node_subsite_theme_frontpage':
          if ($group->bundle() === 'subsite') {
            $theme_group = current($group->get('field_subsite_theme')->referencedEntities());
            if ($theme_group instanceof GroupInterface) {
              $bubbleable_metadata->addCacheableDependency($theme_group);
              if (!$theme_group->get('field_front_page')->isEmpty()) {
                $theme_front_page = current($theme_group->get('field_front_page')
                  ->referencedEntities());
                if ($theme_front_page instanceof EntityInterface) {
                  $bubbleable_metadata->addCacheableDependency($theme_front_page);
                  $translated_front_page = $entity_repository->getTranslationFromContext($theme_front_page);
                  $replacements[$original] = $translated_front_page->label();
                }
              }
            }
          }
          break;

        case 'node_subsite_life_situation_frontpage':
          if ($group->bundle() === 'subsite') {
            $life_situation_group = current($group->get('field_subsite_life_situation')->referencedEntities());
            if ($life_situation_group instanceof GroupInterface) {
              $bubbleable_metadata->addCacheableDependency($life_situation_group);
              if (!$life_situation_group->get('field_front_page')->isEmpty()) {
                $life_situation_front_page = current($life_situation_group->get('field_front_page')
                  ->referencedEntities());
                if ($life_situation_front_page instanceof EntityInterface) {
                  $bubbleable_metadata->addCacheableDependency($life_situation_front_page);
                  $translated_front_page = $entity_repository->getTranslationFromContext($life_situation_front_page);
                  $replacements[$original] = $translated_front_page->label();
                }
              }
            }
          }
          break;

        case 'node_subsite_topic_frontpage':
          if ($group->bundle() === 'subsite') {
            $topic_group = current($group->get('field_subsite_topic')->referencedEntities());
            if ($topic_group instanceof GroupInterface) {
              $bubbleable_metadata->addCacheableDependency($topic_group);
              if (!$topic_group->get('field_front_page')->isEmpty()) {
                $topic_front_page = current($topic_group->get('field_front_page')
                  ->referencedEntities());
                if ($topic_front_page instanceof EntityInterface) {
                  $bubbleable_metadata->addCacheableDependency($topic_front_page);
                  $translated_front_page = $entity_repository->getTranslationFromContext($topic_front_page);
                  $replacements[$original] = $translated_front_page->label();
                }
              }
            }
          }
          break;

        case 'node_subsite_theme_topic_frontpage':
          if ($group->bundle() === 'subsite') {
            $theme_group = current($group->get('field_subsite_theme')->referencedEntities());
            if ($theme_group instanceof GroupInterface && !$theme_group->get('field_theme_topic')->isEmpty()) {
              $bubbleable_metadata->addCacheableDependency($theme_group);
              $topic_group = current($theme_group->get('field_theme_topic')->referencedEntities());
              if ($topic_group instanceof GroupInterface) {
                $bubbleable_metadata->addCacheableDependency($topic_group);
                if (!$topic_group->get('field_front_page')->isEmpty()) {
                  $topic_front_page = current($topic_group->get('field_front_page')
                    ->referencedEntities());
                  if ($topic_front_page instanceof EntityInterface) {
                    $bubbleable_metadata->addCacheableDependency($topic_front_page);
                    $translated_front_page = $entity_repository->getTranslationFromContext($topic_front_page);
                    $replacements[$original] = $translated_front_page->label();
                  }
                }
              }
            }
          }
          break;

        case 'node_subsite_theme_life_situation_frontpage':
          if ($group->bundle() === 'subsite') {
            $theme_group = current($group->get('field_subsite_theme')->referencedEntities());
            if ($theme_group instanceof GroupInterface && !$theme_group->get('field_theme_life_situation')->isEmpty()) {
              $bubbleable_metadata->addCacheableDependency($theme_group);
              $life_situation_group = current($theme_group->get('field_theme_life_situation')->referencedEntities());
              if ($life_situation_group instanceof GroupInterface) {
                $bubbleable_metadata->addCacheableDependency($life_situation_group);
                if (!$life_situation_group->get('field_front_page')->isEmpty()) {
                  $life_situation_front_page = current($life_situation_group->get('field_front_page')
                    ->referencedEntities());
                  if ($life_situation_front_page instanceof EntityInterface) {
                    $bubbleable_metadata->addCacheableDependency($life_situation_front_page);
                    $translated_front_page = $entity_repository->getTranslationFromContext($life_situation_front_page);
                    $replacements[$original] = $translated_front_page->label();
                  }
                }
              }
            }
          }
          break;

        case 'group_content_menu_parents':
          $path_parts = [];
          if ($group->bundle() == 'subsite' || $group->bundle() == 'minisite') {
            $current_group_content_menu = _tre_custom_paths_get_current_group_content_menu($group, $node);

            if (!empty($current_group_content_menu)) {
              $bubbleable_metadata->addCacheableDependency($current_group_content_menu);

              $path_to_root = _tre_custom_paths_get_group_menu_parents_for_node($current_group_content_menu, $node);

              /** @var \Drupal\Core\Menu\MenuTreeStorageInterface $menu_tree_storage */
              $menu_tree_storage = \Drupal::service('menu.tree_storage');

              /** @var \Drupal\pathauto\AliasCleanerInterface $alias_cleaner */
              $alias_cleaner = \Drupal::service('pathauto.alias_cleaner');

              if (!empty($path_to_root)) {
                array_shift($path_to_root);
                foreach (array_reverse($path_to_root) as $link_id) {
                  $link_id_parts = explode(':', $link_id);
                  try {
                    $menu_link_entity = $entity_repository->loadEntityByUuid($link_id_parts[0], $link_id_parts[1]);
                    $bubbleable_metadata->addCacheableDependency($menu_link_entity);
                  }
                  catch (EntityStorageException $e) {
                    // Nothing to do here.
                  }

                  $link_data = $menu_tree_storage->load($link_id);
                  if ($link_data['route_name'] === 'entity.node.canonical') {
                    $link_node_id = $link_data['route_parameters']['node'];
                    $link_node = $entity_repository->getCanonical('node', $link_node_id);

                    if ($link_node instanceof NodeInterface) {
                      $bubbleable_metadata->addCacheableDependency($link_node);
                      // Since the token by $name ('group_content_menu_parents')
                      // has been declared as 'safe' in pathauto patterns, it
                      // will not get cleaned at all. That is why we opt to use
                      // Pathauto's cleaner to clean each part separately.
                      $path_parts[] = $alias_cleaner->cleanString($link_node->label());
                    }
                  }

                }
              }
            }
          }
          $replacements[$original] = implode('/', $path_parts);
          break;
      }
    }
  }

  // Return the replacements.
  return $replacements;
}
