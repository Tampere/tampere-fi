import React from 'react';

import cardWithLinksComponent from './card-with-links.twig';

import cardWithLinksData from './card-with-links.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Card with links' };

export const cardWithLinks = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithLinksComponent(cardWithLinksData),
    }}
  />
);
