import React from 'react';

import feedbackLinksComponent from './feedback-links.twig';

import feedbackLinksData from './feedback-links.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Feedback links' };

export const feedbackLinks = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: feedbackLinksComponent(feedbackLinksData),
    }}
  />
);
