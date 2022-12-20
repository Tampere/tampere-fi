import React from 'react';

import contentImgComponent from './content-image.twig';

import contentImgData from './content-image.yml';
import contentImgNoticeData from './content-image-notice.yml';
import contentImgBlogData from './content-image-blog.yml';

export default { title: 'Atoms/Images/Content Image' };

export const contentImage = () => (
  <div
    dangerouslySetInnerHTML={{ __html: contentImgComponent(contentImgData) }}
  />
);

export const contentImageNotice = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: contentImgComponent(contentImgNoticeData),
    }}
  />
);

export const contentImageBlog = () => (
  <div
    dangerouslySetInnerHTML={{
      __html: contentImgComponent(contentImgBlogData),
    }}
  />
);
