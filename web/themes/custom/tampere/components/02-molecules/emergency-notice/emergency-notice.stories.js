import React from 'react';

import emergencyNoticeData from './emergency-notice.yml';
import emergencyNoticeWithLinkData from './emergency-notice-with-link.yml';

import emergencyNoticeComponent from './emergency-notice.twig';

import './emergency-notice';

export default { title: 'Molecules/Notices/Emergency Notice' };

export const emergencyNotice = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: emergencyNoticeComponent(emergencyNoticeData),
    }}
  />
);

export const emergencyNoticeWithLink = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: emergencyNoticeComponent(emergencyNoticeWithLinkData),
    }}
  />
);
