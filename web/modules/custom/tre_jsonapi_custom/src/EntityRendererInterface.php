<?php

namespace Drupal\tre_jsonapi_custom;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for service class for rendering content entities into markup.
 */
interface EntityRendererInterface {

  /**
   * Gets rendered markup for an entity in the desired view mode.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to render.
   * @param string $view_mode
   *   The view mode to use for rendering.
   * @param string|null $langcode
   *   The langcode (fi or en) to use in rendering. If none is given, defaults
   *   to current content language in use.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered markup.
   *
   * @see \Drupal\Core\Entity\EntityViewBuilderInterface::view()
   */
  public function renderEntity(ContentEntityInterface $entity, string $view_mode, string $langcode = NULL): MarkupInterface;

}
