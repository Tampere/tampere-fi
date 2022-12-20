import React from 'react';

import textWithTitle from './text-with-title.twig';

import textWithTitleData from './text-with-title.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Text with title' };

export const texWithTitleExample = () => (
  <div dangerouslySetInnerHTML={{ __html: textWithTitle(textWithTitleData) }} />
);
