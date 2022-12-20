import React from 'react';

import listingItemdata from './listing-item.yml';
import listingItemWithLinkdata from './listing-item-with-link.yml';
import listingItemLinkWrapperData from './listing-item-link-wrapper.yml';
import listingItemDefaultSearchResultData from './listing-item-default-search-result.yml';

import listingItemComponent from './listing-item.twig';

export default { title: 'Molecules/Listing item' };

export const listingItem = () => (
  <div
    dangerouslySetInnerHTML={{ __html: listingItemComponent(listingItemdata) }}
  />
);

export const listingItemWithLink = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: listingItemComponent(listingItemWithLinkdata),
    }}
  />
);

export const listingItemLinkWrapper = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: listingItemComponent(listingItemLinkWrapperData),
    }}
  />
);

export const listingItemDefaultSearchResult = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: listingItemComponent(listingItemDefaultSearchResultData),
    }}
  />
);
