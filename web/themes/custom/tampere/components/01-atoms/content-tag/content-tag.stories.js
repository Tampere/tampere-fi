import React from 'react';

import contentTagComponent from './content-tag.twig';

import contentTagData from './content-tag.yml';
import contentTagNoticeData from './content-tag-notice.yml';
import contentTagBlogData from './content-tag-blog.yml';

export default { title: 'Atoms/Content Tag' };

export const contentTag = () => (
  <div
    dangerouslySetInnerHTML={{ __html: contentTagComponent(contentTagData) }}
  />
);

export const contentTagNotice = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: contentTagComponent({ ...contentTagData, ...contentTagNoticeData }),
    }}
  />
);

export const contentTagBlog = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: contentTagComponent({ ...contentTagData, ...contentTagBlogData }),
    }}
  />
);
