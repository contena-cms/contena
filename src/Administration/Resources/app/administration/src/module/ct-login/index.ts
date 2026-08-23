/** @private */
Contena.Component.register('ct-login', () => import('./page/index'));
/** @private */
Contena.Component.register('ct-login-login', () => import('./view/ct-login-login'));
/** @private */
Contena.Component.register('ct-login-access-denied', () => import('./view/ct-login-access-denied'));
/** @private */
Contena.Component.register('ct-login-recovery', () => import('./view/ct-login-recovery'));
/** @private */
Contena.Component.register('ct-login-recovery-info', () => import('./view/ct-login-recovery-info'));
/** @private */
Contena.Component.register('ct-login-recovery-recovery', () => import('./view/ct-login-recovery-recovery'));

/**
 * @private
 */
Contena.Module.register('ct-login', {
    type: 'core',
    name: 'login',
    title: 'ct-login.general.moduleTitle',

    routes: {
        index: {
            path: '/login',
            component: 'ct-login',
            coreRoute: true,
            redirect: {
                name: 'ct.login.index.credentials',
            },
            children: {
                credentials: {
                    component: 'ct-login-login',
                    path: '',
                },
                accessDenied: {
                    component: 'ct-login-access-denied',
                    path: 'access-denied',
                },
                recovery: {
                    component: 'ct-login-recovery',
                    path: 'recovery',
                    meta: {
                        backToLogin: true,
                    },
                },
                requestSent: {
                    component: 'ct-login-recovery-info',
                    path: 'request-sent',
                    meta: {
                        backToLogin: true,
                    },
                },
                reset: {
                    component: 'ct-login-recovery-recovery',
                    path: 'reset/:hash',
                    props: true,
                    meta: {
                        backToLogin: true,
                    },
                },
            },
        },
    },
});
