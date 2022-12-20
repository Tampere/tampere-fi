module.exports = {
  core: {
    builder: 'webpack5',
  },
  stories: ['../components/**/*.stories.js'],
  staticDirs: ['../dist', '../images'],
  addons: [
    '@storybook/addon-a11y',
    '@storybook/addon-actions/register',
    '@storybook/addon-links/register',
    '@storybook/addon-backgrounds',
    'storybook-addon-themes',
  ],
};