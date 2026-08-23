/**
 *
 * These types of initializers are called in the beginning of the initialization process.
 * They can decorate the following initializer.
 */
import initApiServices from './api-services.init';
import initStore from './store.init';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    apiServices: initApiServices,
    store: initStore,
};
