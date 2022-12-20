import React from 'react';

import topicsAndLifeSituationsComponent from './topics-and-life-situations.twig';

import lifeSituationsData from './topics-and-life-situations-life-situations.yml';
import topicsData from './topics-and-life-situations-topics.yml';
import linkListWithLargeIconsData from '../../01-atoms/link-list/link-list-large-icons.yml';
import linkListWithLargeIconsPrimaryData from '../../01-atoms/link-list/link-list-large-icons-primary.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/Topics and Life situations' };

export const topics = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicsAndLifeSituationsComponent({
        ...topicsData,
        ...linkListWithLargeIconsData,
      }),
    }}
  />
);

export const lifeSituations = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicsAndLifeSituationsComponent({
        ...lifeSituationsData,
        ...linkListWithLargeIconsData,
        ...linkListWithLargeIconsPrimaryData,
      }),
    }}
  />
);
