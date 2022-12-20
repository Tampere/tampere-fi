import React from 'react';

import link from './link.twig';

import linkData from './link.yml';
import linkNoHrefData from './link-no-href.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Links' };

export const links = () => (
  <div dangerouslySetInnerHTML={{ __html: link(linkData) }} />
);

export const linkNoHref = () => (
  <div dangerouslySetInnerHTML={{ __html: link(linkNoHrefData) }} />
);
