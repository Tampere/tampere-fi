import React from 'react';

import genericListingData from './generic-listing.yml';

import genericListingComponent from './generic-listing.twig';

export default { title: 'Organisms/Generic listing' };

export const GenericListing = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: genericListingComponent(genericListingData),
    }}
  />
);
