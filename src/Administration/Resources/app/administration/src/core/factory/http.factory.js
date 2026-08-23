/**
 *
 * @module core/factory/http
 */
import Axios from 'axios';
import cacheAdapterFactory from 'src/core/factory/cache-adapter.factory';

/**
 * Initializes the HTTP client with the provided context. The context provides the API end point and will be used as
 * the base url for the HTTP client.
 *
 * @method createHTTPClient
 * @memberOf module:core/factory/http
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function createHTTPClient(context) {
    return createClient(context);
}

/**
 * Provides CancelToken so a request's promise from Http Client could be canceled.
 *
 * @returns { CancelToken, isCancel, Cancel}
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export const { CancelToken, isCancel, Cancel } = Axios;

/**
 * Creates the HTTP client with the provided context.
 *
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
function createClient() {
    const client = Axios.create({
        baseURL: Contena.Context.api.apiPath,
        // Add request/response size limits to mitigate DoS vulnerability
        maxContentLength: 50 * 1024 * 1024, // 50MB limit
        maxBodyLength: 50 * 1024 * 1024, // 50MB limit
        timeout: 30000, // 30 second timeout
    });

    refreshTokenInterceptor(client);
    globalErrorHandlingInterceptor(client);
    tracingInterceptor(client);
    client.CancelToken = CancelToken;

    /**
     * Don´t use cache in unit tests because it is possible
     * that the test uses the same route with different responses
     * (e.g. error, success) in a short amount of time.
     * So in test cases we are using the originalAdapter directly
     * and skipping the caching mechanism.
     */
    if (process?.env?.NODE_ENV !== 'test') {
        requestCacheAdapterInterceptor(client);
    }

    return client;
}

/**
 * Sets up an interceptor to handle automatic cache of same requests in short time amount
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function requestCacheAdapterInterceptor(client) {
    const requestCaches = {};
    client.interceptors.request.use((config) => {
        const adapter = Axios.getAdapter(config.adapter);
        config.adapter = cacheAdapterFactory(adapter, requestCaches);

        return config;
    });
}

/**
 * Sets up an interceptor to process global request errors
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function globalErrorHandlingInterceptor(client) {
    client.interceptors.response.use(
        (response) => response,
        (error) => {
            if (!error) {
                return Promise.reject(error);
            }

            const { status } = error.response ?? { status: undefined };
            const { errors, data } = error.response?.data ?? {
                errors: undefined,
                data: undefined,
            };

            try {
                handleErrorStates({ status, errors, error, data });
            } catch (e) {
                Contena.Utils.debug.error(e);

                if (errors) {
                    errors.forEach((singleError) => {
                        Contena.Store.get('notification').createNotification({
                            variant: 'error',
                            title: singleError.title,
                            message: singleError.detail,
                        });
                    });
                }
            }

            return Promise.reject(error);
        },
    );

    return client;
}

/**
 * Determines the different status codes and creates matching Administration notifications
 * @param {Number} status
 * @param {Array} errors
 * @param {Object} error
 * @param {Object} data
 */
function handleErrorStates({ status, errors, error = null, data }) {
    // Handle sync-api errors
    if (status === 400 && (error?.response?.config?.url ?? '').includes('_action/sync')) {
        if (!data) {
            return;
        }

        // Get data for each entity
        Object.values(data).forEach((item) => {
            // Get error for each result
            item.result.forEach((resultItem) => {
                if (!resultItem.errors.length) {
                    return;
                }

                const statusCode = parseInt(resultItem.errors[0].status, 10);
                handleErrorStates({
                    status: statusCode,
                    errors: resultItem.errors,
                    data,
                });
            });
        });
    }

    if (status === 403) {
        const missingPrivilegeErrors = errors.filter((e) => e.code === 'FRAMEWORK__MISSING_PRIVILEGE_ERROR');
        missingPrivilegeErrors.forEach((missingPrivilegeError) => {
            const detail = JSON.parse(missingPrivilegeError.detail);
            let missingPrivileges = detail.missingPrivileges;

            // check if response is an object and not an array. If yes, then convert it
            if (!Array.isArray(missingPrivileges) && typeof missingPrivileges === 'object') {
                missingPrivileges = Object.values(missingPrivileges);
            }

            const missingPrivilegesMessage = missingPrivileges.reduce((message, privilege) => {
                return `${message}<br>"${privilege}"`;
            }, '');

            Contena.Store.get('notification').createNotification({
                variant: 'error',
                system: true,
                autoClose: false,
                growl: true,
                title: Contena.Snippet.tc('global.error-codes.FRAMEWORK__MISSING_PRIVILEGE_ERROR'),
                message: `${Contena.Snippet.tc('ct-privileges.error.description')} <br> ${missingPrivilegesMessage}`,
            });
        });
    }

    if (status === 409) {
        if (errors[0].code === 'FRAMEWORK__DELETE_RESTRICTED') {
            const parameters = errors[0].meta.parameters;

            const entityName = parameters.entity;
            let blockingEntities = '';

            blockingEntities = parameters.usages.reduce((message, usageObject) => {
                const times = usageObject.count;
                const timesSnippet = Contena.Snippet.tc('global.default.xTimesIn', times);
                const blockingEntitiesSnippet = Contena.Snippet.tc(`global.entities.${usageObject.entityName}`, times[1]);
                return `${message}<br>${timesSnippet} <b>${blockingEntitiesSnippet}</b>`;
            }, '');

            Contena.Store.get('notification').createNotification({
                variant: 'error',
                title: Contena.Snippet.tc('global.default.error'),
                message: `${Contena.Snippet.tc(
                    'global.notification.messageDeleteFailed',
                    { entityName: Contena.Snippet.tc(`global.entities.${entityName}`) },
                    0,
                )}${blockingEntities}`,
            });
        }
    }

    if (status === 412) {
        const frameworkLanguageNotFound = errors.find((e) => e.code === 'FRAMEWORK__LANGUAGE_NOT_FOUND');

        if (frameworkLanguageNotFound) {
            localStorage.removeItem('ct-admin-current-language');

            Contena.Store.get('notification').createNotification({
                variant: 'error',
                system: true,
                autoClose: false,
                growl: true,
                title: frameworkLanguageNotFound.title,
                message: `${frameworkLanguageNotFound.detail} Please reload the administration.`,
                actions: [
                    {
                        label: 'Reload administration',
                        method: () => window.location.reload(),
                    },
                ],
            });
        }
    }
}

/**
 * Sets up an interceptor to refresh the token, cache the requests and retry them after the token got refreshed.
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function refreshTokenInterceptor(client) {
    const skipList = ['/oauth/token'];

    client.interceptors.response.use(
        (response) => {
            return response;
        },
        (error) => {
            const config = error.config || {};
            const status = error.response?.status;
            const originalRequest = config;
            const resource = originalRequest.url?.replace(originalRequest.baseURL, '');

            if (skipList.includes(resource)) {
                // For /oauth/token endpoint, reject immediately to avoid infinite loops
                // This endpoint returns 400 when token is revoked (invalid_grant error)
                return Promise.reject(error);
            }

            if (status === 401) {
                // Prevent infinite retry loops - only allow one token refresh retry per request
                if (originalRequest._tokenRefreshRetry) {
                    return Promise.reject(error);
                }

                const loginService = Contena.Service('loginService');

                // Intentionally ignore refresh token errors here; they are handled via subscribeToTokenRefresh.
                loginService.refreshToken().catch(() => undefined);

                return new Promise((resolve, reject) => {
                    loginService.subscribeToTokenRefresh(
                        (newToken) => {
                            // replace the expired token and retry
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                            originalRequest.url = originalRequest.url.replace(originalRequest.baseURL, '');
                            originalRequest._tokenRefreshRetry = true;
                            resolve(client.request(originalRequest));
                        },
                        (err) => {
                            if (!Contena.Application.getApplicationRoot()) {
                                reject(err);
                                window.location.reload();
                                return;
                            }

                            reject(err);
                        },
                    );
                });
            }

            return Promise.reject(error);
        },
    );

    return client;
}

/**
 * Sets up an interceptor to add tracing information to the request headers on which admin page this request has been fired
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function tracingInterceptor(client) {
    /**
     * axios-client-mock does not work with request interceptors. So we have to disable it for tests.
     */
    if (process.env.NODE_ENV !== 'test') {
        client.interceptors.request.use((config) => {
            const currentRoute = Contena?.Application?.view?.router?.currentRoute?.value?.name;

            if (currentRoute) {
                config.headers['contena-admin-active-route'] = currentRoute;
            }

            return config;
        });
    }

    return client;
}
