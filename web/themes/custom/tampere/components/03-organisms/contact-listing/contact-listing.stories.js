import React from 'react';

import contactListingData from './contact-listing.yml';

import contactListingComponent from './contact-listing.twig';

export default { title: 'Organisms/Contact listing' };

export const ContactListing = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: contactListingComponent(contactListingData),
    }}
  />
);
