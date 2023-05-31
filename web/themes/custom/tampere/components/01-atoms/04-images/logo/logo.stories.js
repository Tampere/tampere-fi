import React from 'react';

import logoComponent from './logo.twig';

import logoData from './logo.yml';
import logoWithLinkData from './logo-with-link.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Images/Logo' };

export const logo = () => (
  <div dangerouslySetInnerHTML={{ __html: logoComponent(logoData) }} />
);

export const logoWithLink = () => (
  <div dangerouslySetInnerHTML={{ __html: logoComponent(
    { ...logoData, ...logoWithLinkData },
  ) }} />
);
