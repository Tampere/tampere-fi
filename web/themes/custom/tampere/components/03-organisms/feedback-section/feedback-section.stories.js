import React from 'react';

import feedbackSectionData from './feedback-section.yml';
import feedbackSectionLinksData from '../../01-atoms/link-list/link-list-large-icons.yml';
import feedbackSectionLinksVariationData from '../../01-atoms/link-list/link-list-large-icons-primary.yml';
import feedbackSectionCardData from '../../02-molecules/feedback-card/feedback-card.yml';

import feedbackSectionComponent from './feedback-section.twig';

export default { title: 'Organisms/Feedback section' };

export const FeedbackSection = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: feedbackSectionComponent({
        ...feedbackSectionData,
        ...feedbackSectionLinksData,
        ...feedbackSectionLinksVariationData,
        ...feedbackSectionCardData,
      }),
    }}
  />
);
