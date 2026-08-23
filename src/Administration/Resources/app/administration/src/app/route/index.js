// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default [
    {
        path: '/',
        name: 'core',
        coreRoute: true,
        root: true,
        component: 'ct-desktop',
        redirect: '/ct/dashboard/index',
    },
    {
        path: '/error',
        name: 'error',
        coreRoute: true,
        component: 'ct-error',
        meta: {
            forceRoute: true,
        },
    },
];
