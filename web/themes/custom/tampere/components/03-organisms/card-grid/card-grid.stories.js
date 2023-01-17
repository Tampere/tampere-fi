import React from 'react';

import cardGridComponent from './card-grid.twig';

import cardGrid3ColData from './card-grid-3-col.yml';
import cardGrid3ColExpandOneItemData from './card-grid-3-col-expand-with-one-item.yml';
import cardGrid3ColExpandTwoItemsData from './card-grid-3-col-expand-with-two-items.yml';
import cardGrid3ColExpandThreeItemsData from './card-grid-3-col-expand-with-three-items.yml';
import cardGrid3ColExpandFiveItemsData from './card-grid-3-col-expand-with-five-items.yml';
import cardGrid3ColExpandFifteenItemsData from './card-grid-3-col-expand-with-fifteen-items.yml';
import cardGrid4ColData from './card-grid-4-col.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Card grid' };

export const cardGridWith3Columns = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColData),
    }}
  />
);

export const cardGridWith3ExpandingColumnsWithOneItem = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColExpandOneItemData),
    }}
  />
);

export const cardGridWith3ExpandingColumnsWithTwoItems = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColExpandTwoItemsData),
    }}
  />
);

export const cardGridWith3ExpandingColumnsWithThreeItems = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColExpandThreeItemsData),
    }}
  />
);

export const cardGridWith3ExpandingColumnsWithFiveItems = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColExpandFiveItemsData),
    }}
  />
);

export const cardGridWith3ExpandingColumnsWithFifteenItems = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid3ColExpandFifteenItemsData),
    }}
  />
);

export const cardGridWith4ColumnsVaryingHeights = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: cardGridComponent(cardGrid4ColData),
    }}
  />
);
