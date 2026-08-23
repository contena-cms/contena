// The eslint import resolver vite does not support shared worker imports
import SharedAdminWorker from 'src/core/worker/admin-worker.shared-worker?sharedworker';
import AdminWorker from 'src/core/worker/admin-worker.worker?worker';

import AdminNotificationWorker from 'src/core/worker/admin-notification-worker';
import type { ApiContext } from '@contena/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { LoginService } from '../../core/service/login.service';
import type { ContextStore } from '../store/context.store';

type ContextAppConfig = ContextStore['app']['config'];

let enabled = false;
let enabledNotification = false;

/**
 *
 * Starts the worker
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeWorker() {
    const loginService = Contena.Service('loginService');
    const context = Contena.Context.app;
    const configService = Contena.Service('configService');

    function getConfig() {
        return configService.getConfig().then((response) => {
            Object.entries(response as ContextAppConfig).forEach(
                ([
                    key,
                    value,
                ]) => {
                    Contena.Store.get('context').addAppConfigValue({
                        key: key as keyof ContextAppConfig,
                        value,
                    });
                },
            );

            // Enable worker notification listener regardless of the config
            if (!enabledNotification) {
                enableNotificationWorker(loginService);
            }

            if (context.config.adminWorker?.enableAdminWorker && !enabled) {
                enableAdminWorker(loginService, Contena.Context.api, context.config.adminWorker);
            }
        });
    }

    if (loginService.isLoggedIn()) {
        return getConfig().catch();
    }

    return loginService.addOnLoginListener(getConfig);
}

function enableAdminWorker(
    loginService: LoginService,
    context: ApiContext,
    config: ContextStore['app']['config']['adminWorker'],
) {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    const transports = (JSON.parse(JSON.stringify(config))?.transports || []) as string[];

    const getMessage = () => {
        return {
            context: {
                languageId: context.languageId,
                apiResourcePath: context.apiResourcePath,
            },
            bearerAuth: loginService.getBearerAuthentication(),
            host: window.location.origin,
            // Quick fix to lose the reference to the config object, this was causing issues with the worker
            transports,
        };
    };

    if (loginService.isLoggedIn()) {
        getWorker().port.postMessage(getMessage());
    }

    loginService.addOnTokenChangedListener((auth) => {
        getWorker().port.postMessage({
            ...getMessage(),
            ...{ bearerAuth: auth },
        });
    });

    loginService.addOnLogoutListener(() => {
        getWorker().port.postMessage({ type: 'logout' });
    });

    enabled = true;
}

// singleton instance of worker
let worker: SharedWorker;

/* istanbul ignore next */
function getWorker(): SharedWorker {
    if (worker) {
        return worker;
    }

    // SharedWorker is not supported in all browsers, especially on mobile devices
    if (typeof SharedWorker === 'undefined') {
        // @ts-expect-error
        worker = new AdminWorker();

        // hack to make the worker api like a shared worker
        // @ts-expect-error
        worker.port = worker;
        worker.port.start = () => {};
    } else {
        worker = new SharedAdminWorker();
    }

    worker.port.start();

    worker.port.onmessage = ({ data }: { data: { [key: string]: unknown } }) => {
        if (data && data.isWorkerError) {
            /**
             * To debug workers visit the following URL in Chrome browser
             * chrome://inspect/#workers
             */
            console.error(data.error);

            return;
        }

        const loginService = Contena.Service('loginService');

        void loginService.refreshToken();
    };

    return worker;
}

function enableNotificationWorker(loginService: LoginService) {
    let notificationWorker = new AdminNotificationWorker();

    if (loginService.isLoggedIn()) {
        notificationWorker.start();
    }

    loginService.addOnTokenChangedListener(() => {
        notificationWorker.terminate();
        notificationWorker = new AdminNotificationWorker();
        notificationWorker.start();
    });

    loginService.addOnLogoutListener(() => {
        notificationWorker.terminate();
        notificationWorker = new AdminNotificationWorker();
    });

    enabledNotification = true;
}
