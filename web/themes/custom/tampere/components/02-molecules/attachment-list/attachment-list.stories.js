import React from 'react';

import attachmentListComponent from './attachment-list.twig';

import attachmentListData from './attachment-list.yml';

export default { title: 'Molecules/Attachment List' };

export const attachmentList = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: attachmentListComponent(attachmentListData),
    }}
  />
);
