import React from 'react';

import rssFeedComponent from './rss-feed.twig';

import rssFeedData from './rss-feed.yml';
import rssFeedWithoutTitleData from './rss-feed-without-title.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Organisms/RSS Feed' };

export const rssFeed = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: rssFeedComponent(rssFeedData),
    }}
  />
);

export const rssFeedWithoutTitle = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: rssFeedComponent({ ...rssFeedData, ...rssFeedWithoutTitleData }),
    }}
  />
);
