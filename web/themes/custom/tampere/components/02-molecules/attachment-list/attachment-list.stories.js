import React from 'react';

import attachmentListComponent from './attachment-list.twig';

import attachmentListData from './attachment-list.yml';
import withAdditionalIconsData from './with-additional-icons.yml';

export default { title: 'Molecules/Attachment List' };

export const attachmentList = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: attachmentListComponent(attachmentListData),
    }}
  />
);

export const attachmentListWithAdditionalIcons = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: attachmentListComponent(
        { ...attachmentListData, ...withAdditionalIconsData },
      ),
    }}
  />
);
