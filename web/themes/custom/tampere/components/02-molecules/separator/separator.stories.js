import React from 'react';

import separatorTwig from './separator.twig';

export default { title: 'Molecules/Separator' };

export const separator = () => (
  <div dangerouslySetInnerHTML={{ __html: separatorTwig() }} />
);
