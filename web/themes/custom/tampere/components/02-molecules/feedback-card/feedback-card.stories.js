import React from 'react';

import feedbackCardComponent from './feedback-card.twig';

import feedbackCardData from './feedback-card.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Cards/Feedback card' };

export const feedbackCard = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: feedbackCardComponent(feedbackCardData),
    }}
  />
);
