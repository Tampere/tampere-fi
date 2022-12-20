module.exports = (api) => {
  api.cache(true);

  const presets = [
    '@babel/preset-react',
    [
      '@babel/preset-env',
      {
        corejs: 3,
        useBuiltIns: 'usage',
      },
    ],
    ['minify', {
      builtIns: false,
    }],
  ];

  const comments = false;

  return { presets, comments };
};