import React from 'react';

import textWithBgcolorComponent from './text-with-bgcolor.twig';

import textWithBgcolorOneStringData from './text-with-bgcolor-one-string.yml';
import textWithBgcolorData from './text-with-bgcolor.yml';
import textWithBgcolorDataReverse from './text-with-bgcolor-reverse.yml';
import textWithBgcolorDataThird from './text-with-bgcolor-third.yml';
import textWithBgcolorDataThirdReverse from './text-with-bgcolor-third-reverse.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Text with background color' };

export const textWithBgcolorOneString = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textWithBgcolorComponent(textWithBgcolorOneStringData),
    }}
  />
);

export const textWithBgcolor = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textWithBgcolorComponent(textWithBgcolorData),
    }}
  />
);

export const textWithBgcolorReverse = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textWithBgcolorComponent(textWithBgcolorDataReverse),
    }}
  />
);

export const textWithBgcolorThird = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textWithBgcolorComponent(textWithBgcolorDataThird),
    }}
  />
);

export const textWithBgcolorThirdReverse = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: textWithBgcolorComponent(textWithBgcolorDataThirdReverse),
    }}
  />
);
