/**
 *
 * These types of initializers are called in the end of the initialization process.
 * They depend on different initializer and can be used for setups.
 */
import initUserInformation from './user-information.init';
import initLanguage from './language.init';
import initWorker from './worker.init';
import initTelemetry from './telemetry.init';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default {
    language: initLanguage,
    userInformation: initUserInformation,
    worker: initWorker,
    telemetry: initTelemetry,
};
