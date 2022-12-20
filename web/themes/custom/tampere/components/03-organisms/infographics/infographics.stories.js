import React from 'react';

import infographicsComponent from './infographics.twig';

import infographicsData from './infographics.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Infographics' };

export const infographics = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: infographicsComponent(infographicsData),
    }}
  />
);
