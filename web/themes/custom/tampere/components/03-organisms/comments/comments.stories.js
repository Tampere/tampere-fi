import React from 'react';

import commentsComponent from './comments.twig';
import commentsData from './comments.yml';

export default { title: 'Organisms/Comments' };

export const comments = () => (
  <div dangerouslySetInnerHTML={{ __html: commentsComponent(commentsData) }} />
);
