const path = require('path');
const isDevMode = process.env.NODE_ENV !== 'production';

const config = {
  entry: {
    main: ['./js/src/index.js'],
  },
  devtool: (isDevMode) ? 'inline-source-map' : false,
  mode: (isDevMode) ? 'development' : 'production',
  output: {
    path: path.resolve(__dirname, 'js/dist'),
    filename: '[name].bundle.js'
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        loader: 'babel-loader',
        options: {
          plugins: [['babel-plugin-styled-components', { 'displayName': (isDevMode) ? true : false, }]]
        },
        exclude: /(node_modules)/,
        include: path.join(__dirname, 'js/src'),
      },
    ],
  },
};

module.exports = config;
