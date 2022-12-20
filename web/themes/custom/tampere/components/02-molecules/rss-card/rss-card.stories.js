import React from 'react';

import rssCardComponent from './rss-card.twig';

import rssCardData from './rss-card.yml';
import rssCardColorfulData from './rss-card-colorful.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/RSS card' };

export const rssCard = () => (
  <div dangerouslySetInnerHTML={{ __html: rssCardComponent(rssCardData) }} />
);

export const rssCardColorful = () => (
  <div
    dangerouslySetInnerHTML={{ __html: rssCardComponent(rssCardColorfulData) }}
  />
);
