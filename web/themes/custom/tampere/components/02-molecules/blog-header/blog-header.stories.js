import React from 'react';

import blogHeaderComponent from './blog-header.twig';

import blogHeaderData from './blog-header.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Blog header/Blog header' };

export const blogHeader = () => (
  <div
    dangerouslySetInnerHTML={{ __html: blogHeaderComponent(blogHeaderData) }}
  />
);
