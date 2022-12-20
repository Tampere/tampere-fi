import React from 'react';

import iconWithText from './icon-with-text.twig';

import iconWithTextData from './icon-with-text.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Icon with text' };

export const iconWithTextExample = () => (
  <div dangerouslySetInnerHTML={{ __html: iconWithText(iconWithTextData) }} />
);
