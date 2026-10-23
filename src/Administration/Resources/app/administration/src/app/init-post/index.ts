/**
 * @sw-package framework
 *
 * These types of initializers are called in the end of the initialization process.
 * They depend on different initializer and can be used for setups.
 */
import initUserInformation from './admin_user-information.init';
import initLanguage from './language.init';
import initWorker from './worker.init';
import initTelemetry from './telemetry.init';
import initProductAnalytics from './blog-analytics.init';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    language: initLanguage,
    admin_userInformation: initUserInformation,
    worker: initWorker,
    telemetry: initTelemetry,
    blogAnalytics: initProductAnalytics,
};
