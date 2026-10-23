const { Application, Service, Store } = Contena;
const { Criteria } = Contena.Data;

/**
 * @sw-package framework
 *
 * @module core/service/user-group-registration-listener
 */

/**
 * @sw-package checkout
 * @memberOf module:core/service/user-group-registration-listener
 * @method addCustomerGroupRegistrationListener
 * @param loginService
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function addCustomerGroupRegistrationListener(loginService) {
    let applicationRoot = null;

    loginService.addOnLoginListener(checkForUpdates);

    async function checkForUpdates() {
        if (!Contena.Service('acl').can('user.viewer')) {
            return;
        }

        const userRepository = Service('repositoryFactory').create('user');
        const criteria = new Criteria(1, 25);
        criteria.addAssociation('requestedGroup');
        criteria.addFilter(Criteria.not('AND', [Criteria.equals('requestedGroupId', null)]));

        const users = await userRepository.search(criteria, Contena.Context.api);

        users.forEach(createNotification);
    }

    function createNotification(user) {
        const notification = {
            title: getApplicationRootReference().$t('global.default.info'),
            message: getApplicationRootReference().$t(
                'sw-user.userGroupRegistration.notification.message',
                {
                    name: `${user.firstName} ${user.lastName}`,
                    groupName: user.requestedGroup.name,
                },
                0,
            ),
            actions: [
                {
                    label: getApplicationRootReference().$t(
                        'sw-user.userGroupRegistration.notification.openCustomer',
                    ),
                    route: {
                        name: 'sw.user.detail',
                        params: { id: user.id },
                    },
                },
            ],
            variant: 'info',
            appearance: 'notification',
            growl: true,
        };

        Store.get('notification').createNotification(notification);
    }

    function getApplicationRootReference() {
        if (!applicationRoot) {
            applicationRoot = Application.getApplicationRoot();
        }

        return applicationRoot;
    }
}
