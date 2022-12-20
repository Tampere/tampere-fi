<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Header region preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.region__header",
 *   hook = "region__header"
 * )
 */
class HeaderRegion extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $current_path = $this->currentPath->getPath();
    $group_id_from_path = $this->helperFunctions->getGroupIdFromSearchViewPath($current_path);

    $is_minisite_group = $this->helperFunctions->isMinisite($group_id_from_path);

    if (!$is_minisite_group) {
      return $variables;
    }

    if ($group_id_from_path) {
      $current_group = $this->entityTypeManager->getStorage('group')->load($group_id_from_path);
    }
    else {
      $current_group = $this->helperFunctions->getCurrentGroup();
    }

    $group_front_page_details = $this->helperFunctions->getGroupFrontPageDetails($group_id_from_path);
    $variables['is_minisite_group'] = $is_minisite_group;

    if (isset($group_front_page_details)) {
      [$front_page_url, $front_page_node_id] = $group_front_page_details;

      $variables['group_front_page_url'] = $front_page_url;
      $variables['#cache']['tags'][] = "node:{$front_page_node_id}";
    }

    if (!($current_group instanceof GroupInterface)) {
      return $variables;
    }

    /** @var \Drupal\group\Entity\GroupInterface $translated_group */
    $translated_group = $this->entityRepository->getTranslationFromContext($current_group);
    if ($translated_group->hasField('field_minisite_heading') && !$translated_group->get('field_minisite_heading')->isEmpty()) {
      $variables['group_main_heading'] = $translated_group->get('field_minisite_heading')->getString();
    }

    if ($translated_group->hasField('field_svg_logo') && !$translated_group->get('field_svg_logo')->isEmpty()) {
      $variables['group_logo'] = $translated_group->get('field_svg_logo')->view('default');
    }

    $variables['group_name'] = $translated_group->label();

    return $variables;
  }

}
