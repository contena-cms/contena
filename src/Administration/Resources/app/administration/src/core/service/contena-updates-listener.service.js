const { Application } = Contena;

/**
 * @sw-package framework
 *
 * @module core/service/contena-updates-listener
 */

/**
 *
 * @memberOf module:core/service/contena-updates-listener
 * @method addContenaUpdatesListener
 * @param loginService
 * @param serviceContainer
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function addContenaUpdatesListener(loginService, serviceContainer) {
    /** @var {String} localStorage token */
    let applicationRoot = null;

    loginService.addOnLoginListener(() => {
        if (Contena.Context.app.hideUpdateModule) {
            return;
        }

        if (!Contena.Service('acl').can('system.core_update')) {
            return;
        }

        serviceContainer.updateService
            .checkForUpdates()
            .then((response) => {
                if (response.version && response.autoUpdateEnabled !== false) {
                    createUpdatesAvailableNotification(response);
                }
            })
            .catch();
    });

    function createUpdatesAvailableNotification(response) {
        const cancelLabel = getApplicationRootReference().$t('global.default.cancel');
        const updateLabel = getApplicationRootReference().$t(
            'global.notification-center.contena-updates-listener.updateNow',
        );

        const notification = {
            title: getApplicationRootReference().$t(
                'global.notification-center.contena-updates-listener.updatesAvailableTitle',
                {
                    version: response.version,
                },
            ),
            message: getApplicationRootReference().$t(
                'global.notification-center.contena-updates-listener.updatesAvailableMessage',
                {
                    version: response.version,
                },
            ),
            variant: 'info',
            growl: true,
            system: true,
            actions: [
                {
                    label: updateLabel,
                    route: { name: 'sw.settings.contena.updates.wizard' },
                },
                {
                    label: cancelLabel,
                },
            ],
            autoClose: false,
        };

        Contena.Store.get('notification').createNotification(notification);
    }

    function getApplicationRootReference() {
        if (!applicationRoot) {
            applicationRoot = Application.getApplicationRoot();
        }

        return applicationRoot;
    }
}
