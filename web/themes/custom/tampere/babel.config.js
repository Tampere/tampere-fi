module.exports = (api) => {
  api.cache(true);

  const presets = [
    [
      '@babel/preset-react',
    ],
    ['minify', { builtIns: false }],
  ];

  const comments = false;

  return { presets, comments };
};
