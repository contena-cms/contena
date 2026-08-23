const path = require('path');

module.exports = {
    rules: {
        /* eslint-disable global-require,import/no-dynamic-require */
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
        'require-explicit-emits': require(path.resolve(__dirname, 'require-explicit-emits.js')),
        'move-v-if-conditions-to-blocks': require(path.resolve(__dirname, 'move-v-if-conditions-to-blocks.js')),
        'remove-empty-templates': require(path.resolve(__dirname, 'remove-empty-templates.js')),
        'move-slots-to-wrap-blocks': require(path.resolve(__dirname, 'move-slots-to-wrap-blocks.js')),
        'replace-top-level-blocks-to-extends': require(path.resolve(__dirname, 'replace-top-level-blocks-to-extends.js')),
        'enforce-async-component-registers': require(path.resolve(__dirname, 'enforce-async-component-registers.js')),
        'no-tc-translation': require(path.resolve(__dirname, 'no-tc-translation.js')),
        'valid-contena-setup': require(path.resolve(__dirname, 'valid-contena-setup.js')),
        'native-setup-filename': require(path.resolve(__dirname, 'native-setup-filename.js')),
        'require-global-default-use': require(path.resolve(__dirname, 'require-global-default-use.js')),
        /* eslint-enable global-require,import/no-dynamic-require */
    },
};
