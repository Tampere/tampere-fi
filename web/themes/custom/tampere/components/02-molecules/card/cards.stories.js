import React from 'react';

import cardComponent from './01-card.twig';

import cardData from './card.yml';
import cardColorful1Data from './card-colorful-1.yml';
import cardColorful2Data from './card-colorful-2.yml';
import cardColorful3Data from './card-colorful-3.yml';
import cardSlimData from './card-slim.yml';
import cardGroupedData from './card-grouped.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Card' };

export const card = () => (
  <div dangerouslySetInnerHTML={{ __html: cardComponent(cardData) }} />
);

export const colorfulCard1 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardComponent({ ...cardData, ...cardColorful1Data }),
    }}
  />
);

export const colorfulCard2 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardComponent({ ...cardData, ...cardColorful2Data }),
    }}
  />
);

export const colorfulCard3 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardComponent({ ...cardData, ...cardColorful3Data }),
    }}
  />
);

export const slimCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardComponent({ ...cardData, ...cardSlimData }),
    }}
  />
);

export const groupedCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardComponent(cardGroupedData),
    }}
  />
);
