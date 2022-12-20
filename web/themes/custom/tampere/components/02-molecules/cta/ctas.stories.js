import React from 'react';

import ctaComponent from './cta.twig';

import ctaData from './cta.yml';
import ctaWithOnlyLinksData from './cta-with-only-links.yml';
import ctaHalfwidthData from './cta-halfwidth.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/CTA' };

export const cta = () => (
  <div dangerouslySetInnerHTML={{ __html: ctaComponent(ctaData) }} />
);

export const ctaWithOnlyLinks = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: ctaComponent({ ...ctaData, ...ctaWithOnlyLinksData }),
    }}
  />
);

export const ctaHalfwidth = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: ctaComponent({ ...ctaData, ...ctaHalfwidthData }),
    }}
  />
);
