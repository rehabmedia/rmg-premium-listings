const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const path = require('path');
const glob = require('glob');

function getAssetEntries() {
  const entries = {};

  const jsFiles = glob.sync('./src/js/*.js');
  jsFiles.forEach((file) => {
    const filename = path.basename(file, '.js');
    entries[`js/${filename}`] = path.resolve(file);
  });

  return entries;
}

const assetEntries = getAssetEntries();

module.exports = {
  ...defaultConfig,
  entry: async () => {
    const originalEntry = await (typeof defaultConfig.entry === 'function'
      ? defaultConfig.entry()
      : defaultConfig.entry);

    return {
      ...originalEntry,
      ...assetEntries,
    };
  },
  plugins: [
    ...defaultConfig.plugins,
    new RemoveEmptyScriptsPlugin({
      stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
      extensions: ['css'],
    }),
  ],
};
