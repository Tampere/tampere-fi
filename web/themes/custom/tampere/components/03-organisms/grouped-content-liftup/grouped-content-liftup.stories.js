import React from 'react';

import groupedContentLiftupData from './grouped-content-liftup.yml';
import groupedContentLiftup3LiftupsData from './grouped-content-liftup-3-liftups.yml';
import groupedContentLiftup2LiftupsData from './grouped-content-liftup-2-liftups.yml';
import groupedContentLiftupComponent from './grouped-content-liftup.twig';

export default { title: 'Organisms/Grouped Content Liftup' };

export const groupedContentLiftup = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: groupedContentLiftupComponent(groupedContentLiftupData),
    }}
  />
);

export const groupedContentLiftupWith3Liftups = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: groupedContentLiftupComponent(groupedContentLiftup3LiftupsData),
    }}
  />
);

export const groupedContentLiftupWith2Liftups = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: groupedContentLiftupComponent(groupedContentLiftup2LiftupsData),
    }}
  />
);
