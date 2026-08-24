const path = require('path');

module.exports = {
    rules: {
        'private-feature-declarations': require(path.resolve(__dirname, 'private-feature-declarations.js')),
        'no-deprecated-component-usage': require(path.resolve(__dirname, 'no-deprecated-component-usage.js')),
        'no-compat-conditions': require(path.resolve(__dirname, 'no-compat-conditions.js')),
        'no-empty-listeners': require(path.resolve(__dirname, 'no-empty-listeners.js')),
        'no-vue-options-api': require(path.resolve(__dirname, 'no-vue-options-api.js')),
    },
};
