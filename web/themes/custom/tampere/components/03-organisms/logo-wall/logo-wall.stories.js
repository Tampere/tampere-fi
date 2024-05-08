import React from 'react';

import logoWallComponent from './logo-wall.twig';

import logoWallData from './logo-wall.yml';
import logoWallWBgData from './logo-wall-with-background.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Logo wall' };

export const logoWall = () => (
  <div dangerouslySetInnerHTML={{ __html: logoWallComponent(logoWallData) }} />
);

export const logoWallWithBackground = () => (
  <div dangerouslySetInnerHTML={{
    __html: logoWallComponent(
      { ...logoWallData, ...logoWallWBgData },
    ),
  }} />
);
