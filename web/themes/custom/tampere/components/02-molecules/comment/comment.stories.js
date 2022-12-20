import React from 'react';

import commentComponent from './comment.twig';
import commentData from './comment.yml';
import commentVerifiedData from './comment-verified.yml';

export default { title: 'Molecules/Comment' };

export const comment = () => (
  <div dangerouslySetInnerHTML={{ __html: commentComponent(commentData) }} />
);

export const commentVerified = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: commentComponent(commentVerifiedData),
    }}
  />
);
