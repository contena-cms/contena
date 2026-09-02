import type { Plugin } from 'vue';

const DevtoolHelperPlugin: Plugin = {
    install: (app) => {
        app.mixin({
            mounted() {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                if (this.$options.extensionApiDevtoolInformation) {
                    if (!window._ct_extension_component_collection) {
                        window._ct_extension_component_collection = [];
                    }

                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                    window._ct_extension_component_collection.push(this);
                }
            },

            beforeUnmount() {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                if (this.$options.extensionApiDevtoolInformation) {
                    window._ct_extension_component_collection = window._ct_extension_component_collection.filter(
                        (component) => component !== this,
                    );
                }
            },
        });
    },
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default DevtoolHelperPlugin;
