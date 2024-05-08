/* eslint-disable no-underscore-dangle */
const webpack = require('webpack');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const _MiniCssExtractPlugin = require('mini-css-extract-plugin');
const _SpriteLoaderPlugin = require('svg-sprite-loader/plugin');

const MiniCssExtractPlugin = new _MiniCssExtractPlugin({
  filename: 'style.css',
  chunkFilename: '[id].css',
});

const SpriteLoaderPlugin = new _SpriteLoaderPlugin({
  plainSprite: true,
});

const ProgressPlugin = new webpack.ProgressPlugin();

module.exports = {
  ProgressPlugin,
  MiniCssExtractPlugin,
  SpriteLoaderPlugin,
  CleanWebpackPlugin: new CleanWebpackPlugin({
    cleanOnceBeforeBuildPatterns: [
      '!*.{png,jpg,gif,svg,woff,woff2,eot,ttf,otf}',
    ],
    cleanAfterEveryBuildPatterns: [
      'remove/**',
      '!js',
      'css/*.js', // Remove all unwanted, auto generated JS files from dist/css folder.
      'css/**/*.js', // Remove all unwanted, auto generated JS files from dist/css folder.
      '!*.{png,jpg,gif,svg,woff,woff2,eot,ttf,otf}',
    ],
  }),
};
