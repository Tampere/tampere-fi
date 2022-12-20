import React from 'react';

import menuButtonComponent from './menu-button.twig';

import menuButtonData from './menu-button.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Menu Button' };

export const menuButton = () => (
  <div
    dangerouslySetInnerHTML={{ __html: menuButtonComponent(menuButtonData) }}
  />
);
