const { merge } = require('webpack-merge');
const _BrowserSyncPlugin = require('browser-sync-v3-webpack-plugin');
const common = require('./webpack.common.js');

const BrowserSyncPlugin = new _BrowserSyncPlugin({
  // Replace with project domain.
  proxy: 'PROJECT-DOMAIN.test',
  port: 32778,
});

module.exports = merge(common, {
  mode: 'development',
  devtool: 'inline-source-map',
  plugins: [BrowserSyncPlugin],
});
