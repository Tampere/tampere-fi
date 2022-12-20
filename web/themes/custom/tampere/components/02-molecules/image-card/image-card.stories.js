import React from 'react';

import imageCardComponent from './image-card.twig';

import imageCardData from './image-card.yml';
import imageCardTopCornerTagData from './image-card-top-corner-tag.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Image card' };

export const imageCard = () => (
  <div
    dangerouslySetInnerHTML={{ __html: imageCardComponent(imageCardData) }}
  />
);

export const imageCardWithTopCornerTag = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: imageCardComponent({
        ...imageCardData,
        ...imageCardTopCornerTagData,
      }),
    }}
  />
);
