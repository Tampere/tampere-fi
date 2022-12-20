import React from 'react';

import topicalCardData from './topical-card.yml';
import topicalCardWithImageData from './topical-card-with-image.yml';
import topicalCardBigData from './topical-card-big.yml';
import topicalCardBigWithImageData from './big-topical-card-with-image.yml';
import topicalCardAltData from './topical-card-alt.yml';
import topicalCardAltWithImageData from './alt-topical-card-with-image.yml';

import topicalCardComponent from './topical-card.twig';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Topical Card' };

export const topicalCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent(topicalCardData),
    }}
  />
);

export const topicalCardWithImage = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent({
        ...topicalCardData,
        ...topicalCardWithImageData,
      }),
    }}
  />
);

export const topicalCardBig = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent({
        ...topicalCardData,
        ...topicalCardBigData,
      }),
    }}
  />
);

export const topicalCardBigWithImage = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent({
        ...topicalCardData,
        ...topicalCardBigData,
        ...topicalCardBigWithImageData,
      }),
    }}
  />
);

export const topicalCardAlt = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent({
        ...topicalCardData,
        ...topicalCardAltData,
      }),
    }}
  />
);

export const topicalCardAltWithImage = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalCardComponent({
        ...topicalCardData,
        ...topicalCardAltData,
        ...topicalCardAltWithImageData,
      }),
    }}
  />
);
