/**
 * @ct-package framework
 */

import { initializeUserNotifications } from 'src/app/store/notification.store';
import useTheme from 'src/app/composables/use-theme';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default function initializeUserContext() {
    return new Promise<void>((resolve) => {
        const loginService = Contena.Service('loginService');
        const userService = Contena.Service('userService');

        loginService.addOnLoginListener(() => {
            void useTheme().loadUserTheme();
        });

        // The user isn't logged in
        if (!loginService.isLoggedIn()) {
            // Remove existing login info from the locale storage
            loginService.logout();
            resolve();
            return;
        }

        void useTheme().loadUserTheme();

        userService
            .getUser()
            .then((response) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
                const data = response?.data;
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                delete data.password;

                Contena.Store.get('session').setCurrentUser(data as EntitySchema.user);
                initializeUserNotifications();
                resolve();
            })
            .catch(() => {
                // An error occurred which means the user isn't logged in so get rid of the information in local storage
                loginService.logout();
                resolve();
            });
    });
}
