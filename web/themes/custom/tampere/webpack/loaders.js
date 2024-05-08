const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const globImporter = require('node-sass-glob-importer');

const JSLoader = {
  test: /^(?!.*\.(stories|component)\.js$).*\.js$/,
  exclude: /node_modules/,
  loader: 'babel-loader',
};

const ImageLoader = {
  test: /\.(png|svg|jpg|gif)$/i,
  exclude: /icons\/.*\.svg$/,
  loader: 'file-loader',
};

const CSSLoader = {
  test: /\.s[ac]ss$/i,
  exclude: /node_modules/,
  use: [
    MiniCssExtractPlugin.loader,
    {
      loader: 'css-loader',
      options: {
        sourceMap: true,
        url: false,
      },
    },
    {
      loader: 'postcss-loader',
      options: {
        sourceMap: true,
      },
    },
    {
      loader: 'sass-loader',
      options: {
        sourceMap: true,
        sassOptions: {
          importer: globImporter(),
          outputStyle: 'compressed',
        },
      },
    },
  ],
};

const SVGSpriteLoader = {
  test: /icons\/.*\.svg$/, // your icons directory
  loader: 'svg-sprite-loader',
  options: {
    extract: true,
    spriteFilename: (name) =>
      // .../images/icon-sets/main-site-icons/arrow.svg (name) => main-site-icons.svg (sprite filename)
      `${/icon-sets([\\|/])(.*?)\1/gm.exec(name)[2]}.svg`,
    publicPath: '../dist/',
  },
};

const FontsLoader = {
  test: /\.(woff|woff2|eot|ttf|otf)$/,
  loader: 'file-loader',
  include: [/fonts/],
  options: {
    name: '[hash].[ext]',
    outputPath: 'fonts/',
  },
};

module.exports = {
  JSLoader,
  CSSLoader,
  SVGSpriteLoader,
  ImageLoader,
  FontsLoader,
};
