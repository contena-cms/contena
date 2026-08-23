/**
 * @private
 */
export const STORAGE_KEYS = {
    ADMIN_LOCALE: 'ct-admin-locale',
    PREVIOUS_ROUTE: 'ct-admin-previous-route',
    SHOULD_RELOAD: 'ct-login-should-reload',
} as const;

/**
 * @private
 */
export const HTTP_STATUS = {
    TOO_MANY_REQUESTS: 429,
} as const;

/**
 * @private
 */
export const TIMING = {
    COUNTDOWN_INTERVAL_MS: 1000,
    SECONDS_PER_MINUTE: 60,
} as const;

/**
 * @private
 */
export const ROUTES = {
    CORE: 'core',
} as const;
