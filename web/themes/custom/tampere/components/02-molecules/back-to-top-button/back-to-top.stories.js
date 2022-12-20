import React from 'react';

import backToTopButton from './back-to-top.twig';

import './back-to-top';

export default { title: 'Molecules/Back To Top Button' };

export const backToTop = () => (
  <div dangerouslySetInnerHTML={{ __html: backToTopButton() }} />
);
