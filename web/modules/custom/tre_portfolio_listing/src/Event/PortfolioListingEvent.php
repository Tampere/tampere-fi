<?php

namespace Drupal\tre_portfolio_listing\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Portfolio listing event for event listeners.
 */
class PortfolioListingEvent extends Event {

  const PORTFOLIO_GROUP_CONTENT_INSERT = 'tre_portfolio_listing.group_content.insert';

  const PORTFOLIO_LISTING_ENTITY_PRESAVE = 'tre_portfolio_listing.entity.presave';

  const PORTFOLIO_NODE_UPDATE = 'tre_portfolio_listing.node.update';

  /**
   * The entity for the event.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs a portfolio listing event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being inserted or updated.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Get the entity for the event.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The inserted or updated entity.
   */
  public function getEntity() {
    return $this->entity;
  }

}
