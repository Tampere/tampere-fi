import React from 'react';

import topicalListingData from './topical-listing.yml';
import topicalListingEventsData from './topical-listing-events.yml';
import topicalListingWithoutHighlightedLiftupData from './topical-listing-without-highlighted-liftup.yml';

import topicalListingComponent from './topical-listing.twig';

export default { title: 'Organisms/Topical listing' };

export const topicalListing = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalListingComponent(topicalListingData),
    }}
  />
);

export const topicalListingWithoutHighlightedLiftup = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalListingComponent({
        ...topicalListingData,
        ...topicalListingWithoutHighlightedLiftupData,
      }),
    }}
  />
);

export const topicalListingEvents = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: topicalListingComponent({
        ...topicalListingData,
        ...topicalListingEventsData,
      }),
    }}
  />
);
