import React from 'react';

import dl from './02-dl.twig';
import ul from './00-ul.twig';
import ol from './01-ol.twig';

import dlData from './02-dl.yml';
import ulData from './00-ul.yml';
import olData from './01-ol.yml';
import ulWithNewLinesData from './ul-with-new-lines.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Lists' };

export const definitionList = () => (
  <div dangerouslySetInnerHTML={{ __html: dl(dlData) }} />
);
export const unorderedList = () => (
  <div dangerouslySetInnerHTML={{ __html: ul(ulData) }} />
);
export const unorderedListWithNewLines = () => (
  <div dangerouslySetInnerHTML={{ __html: ul({ ...ulData, ...ulWithNewLinesData }) }} />
);
export const orderedList = () => (
  <div dangerouslySetInnerHTML={{ __html: ol(olData) }} />
);
