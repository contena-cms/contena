import getBlockDataScope from '../../ct-block/get-block-data-scope';

/**
 * @private
 */
export default function createDataScopeFixture() {
    return {
        install(app) {
            Object.defineProperty(app.config.globalProperties, '$dataScope', {
                get: getBlockDataScope,
                enumerable: true,
            });
        },
    };
}
