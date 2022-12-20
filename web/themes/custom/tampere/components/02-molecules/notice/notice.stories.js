import React from 'react';

import noticeData from './notice.yml';
import noticeComponent from './notice.twig';

export default { title: 'Molecules/Notices/Notice' };

export const notice = () => (
  <div dangerouslySetInnerHTML={{ __html: noticeComponent(noticeData) }} />
);
