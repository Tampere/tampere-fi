import React from 'react';

import involvementHeaderComponent from './involvement-header.twig';

import involvementHeaderData from './involvement-header.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Involvement Header/Involvement header' };

export const involvementHeader = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: involvementHeaderComponent(involvementHeaderData),
    }}
  />
);
