<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\Core\Breadcrumb\Breadcrumb;

/**
 * Group content menu block preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.block__breadcrumb",
 *   hook = "block__breadcrumb"
 * )
 */
class SubsiteBreadcrumb extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $breadcrumb = $variables['elements']['content'];
    $variables['breadcrumb_mobile'] = $breadcrumb;

    // Check that we are working with a node and if we are not, return.
    $node = $this->routeMatch->getParameter('node');

    if (!($node instanceof NodeInterface)) {
      return $variables;
    }

    // Check that group exists and if it does not, return.
    $group = $this->helperFunctions->getNodeGroup($node);

    if (!$group) {
      return $variables;
    }

    $group_type = $group->bundle();

    // Check that current node belongs to a subsite group, otherwise return.
    if ($group_type !== "subsite") {
      return $variables;
    }

    $group_id = $group->id();
    $node_id = $node->id();
    $breadcrumb['#cache']['tags'][] = "group:{$group_id}";
    $breadcrumb['#cache']['tags'][] = "node:{$node_id}";
    $variables['breadcrumb_mobile'] = $breadcrumb;

    // @phpstan-ignore-nextline
    $group_frontpage_node = $group->get('field_front_page')->entity;

    if (!($group_frontpage_node instanceof NodeInterface)) {
      return $variables;
    }

    $group_frontpage_id = $group_frontpage_node->id();

    // Check that current node is not the frontpage of the current group and if
    // it is, return.
    if ($node_id == $group_frontpage_id) {
      return $variables;
    }

    // Check for parents (topic, theme or life situation groups).
    // if there are none, return.
    $parent = $this->checkForSubordination($group);

    if (!$parent) {
      return $variables;
    }

    $parent_frontpage_node = $parent->get('field_front_page')->entity;

    if (!($parent_frontpage_node instanceof NodeInterface)) {
      return $variables;
    }

    // Get current link element and clone it for replacement.
    $link_mobile[] = clone $breadcrumb['#links'][0];

    $translated_parent_front_page = $this->entityRepository->getTranslationFromContext($parent_frontpage_node);

    $parent_frontpage_name = $translated_parent_front_page->label();

    $link_mobile[0]->setText($parent_frontpage_name);
    $link_mobile[0]->setUrl($translated_parent_front_page->toUrl());
    $mobile_breadcrumb = new Breadcrumb();
    $mobile_breadcrumb->setLinks($link_mobile);
    $mobile_breadcrumb->addCacheableDependency($group);
    $mobile_breadcrumb->addCacheableDependency($node);
    $mobile_breadcrumb->addCacheableDependency($parent_frontpage_node);

    // Since the original Breadcrumb object is not accessible in this preprocess
    // function, we need to use RendererInterface::mergeBubbleableMetadata() on
    // the render array produced from the new Breadcrumb object instead to
    // transfer all cacheability metadata from the old breadcrumb to the mobile
    // one.
    $mobile_renderable = $mobile_breadcrumb->toRenderable();
    $mobile_renderable = $this->renderer->mergeBubbleableMetadata($mobile_renderable, $variables['elements']['content']);

    $variables['breadcrumb_mobile'] = $mobile_renderable;
    return $variables;
  }

  /**
   * Checks a group for being a descendant of another group.
   *
   *   The parent can be:
   *     - Group type topic.
   *     - Group type theme.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to check.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   Returns exactly one group entity if found, otherwise returns null.
   */
  protected function checkForSubordination(GroupInterface $group): ?GroupInterface {
    $group_entity = NULL;
    $group_list = [];
    if (!$group->get('field_subsite_theme')->isEmpty()) {
      $group_list[] = $group->get('field_subsite_theme')->entity;
    }

    if (!$group->get('field_subsite_topic')->isEmpty()) {
      $group_list[] = $group->get('field_subsite_topic')->entity;
    }

    if (!$group->get('field_subsite_life_situation')->isEmpty()) {
      $group_list[] = $group->get('field_subsite_life_situation')->entity;
    }

    if (count($group_list) !== 1) {
      return $group_entity;
    }

    /** @var \Drupal\group\Entity\GroupInterface $group_entity */
    $group_entity = $group_list[0];
    return $group_entity;
  }

}
