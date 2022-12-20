import React from 'react';

import headings from './00-headings/headings.twig';
import pre from './text/05-preformatted-text.twig';
import paragraph from './text/03-inline-elements.twig';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Text' };

export const headingsExamples = () => (
  <div dangerouslySetInnerHTML={{ __html: headings({}) }} />
);
export const preformatted = () => (
  <div dangerouslySetInnerHTML={{ __html: pre({}) }} />
);
export const random = () => (
  <div dangerouslySetInnerHTML={{ __html: paragraph({}) }} />
);
