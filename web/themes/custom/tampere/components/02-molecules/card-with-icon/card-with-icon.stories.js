import React from 'react';

import cardWithIconComponent from './card-with-icon.twig';

import cardWithIconData from './card-with-icon.yml';
import cardWithIconWithTopicIcon from './card-with-icon-with-topic-icon.yml';
import cardWithIconWithImageData from './card-with-icon-with-image.yml';
import imageCardWithTopicIcon from './card-with-icon-with-topic-icon-and-image.yml';
import cardWithIconColorful1Data from './card-with-icon-colorful-1.yml';
import cardWithIconColorful2Data from './card-with-icon-colorful-2.yml';
import cardWithIconColorful3Data from './card-with-icon-colorful-3.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Card with icon' };

export const cardWithDefaultIcon = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent(cardWithIconData),
    }}
  />
);

export const cardWithTopicIcon = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent({
        ...cardWithIconData,
        ...cardWithIconWithTopicIcon,
      }),
    }}
  />
);

export const cardWithImageWithDefaultIcon = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent(cardWithIconWithImageData),
    }}
  />
);

export const cardWithImageWithTopicIcon = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent({
        ...cardWithIconWithImageData,
        ...imageCardWithTopicIcon,
      }),
    }}
  />
);

export const cardWithIconColorful1 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent({
        ...cardWithIconData,
        ...cardWithIconColorful1Data,
      }),
    }}
  />
);

export const cardWithIconColorful2 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent({
        ...cardWithIconData,
        ...cardWithIconColorful2Data,
      }),
    }}
  />
);

export const cardWithIconColorful3 = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardWithIconComponent({
        ...cardWithIconData,
        ...cardWithIconColorful3Data,
      }),
    }}
  />
);
