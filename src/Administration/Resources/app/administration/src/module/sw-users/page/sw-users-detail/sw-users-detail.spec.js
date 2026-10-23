import { mount } from '@vue/test-utils';
import ContenaError from 'src/core/data/ContenaError';

/**
 * @sw-package checkout
 */

const defaultCustomer = {
    id: 'test',
    accountType: 'private',
    company: 'Contena',
    requestedGroup: {
        translated: {
            name: 'Test',
        },
    },
};

async function createWrapper(
    privileges = [],
    editMode = false,
    userResponse = defaultCustomer,
    { featureActive = false, routeName = 'sw.user.detail.base', routerPush = jest.fn() } = {},
) {
    return mount(
        await wrapTestComponent('sw-user-detail', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                    },
                    'sw-button-process': await wrapTestComponent('sw-button-process'),
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-container': true,
                    'sw-field': true,
                    'sw-language-info': true,
                    'sw-tabs': {
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot></slot></div>',
                        props: ['positionIdentifier'],
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot></slot></div>',
                        props: ['route', 'title', 'hasError'],
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs"></div>',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                    },
                    'router-view': true,
                    'sw-user-card': {
                        template: '<div></div>',
                    },
                    'sw-custom-field-set-renderer': await wrapTestComponent('sw-custom-field-set-renderer'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-skeleton': true,
                    'sw-loader': true,
                },
                mocks: {
                    $route: {
                        name: routeName,
                        query: {
                            edit: editMode,
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: routerPush,
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(userResponse),

                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['1'],
                                    }),
                            };
                        },
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    userGroupRegistrationService: {
                        accept: jest.fn().mockResolvedValue(true),
                        decline: jest.fn().mockResolvedValue(true),
                    },
                    userValidationService: {},
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                },
            },

            props: {
                userId: 'userId',
            },
        },
    );
}

describe('module/sw-user/page/sw-user-detail', () => {
    let wrapper;

    beforeAll(() => {
        global.console.warn = jest.fn();
    });

    beforeEach(async () => {
        Contena.Store.get('error').resetApiErrors();
        wrapper = await createWrapper();
    });

    afterEach(() => {
        Contena.Store.get('error').resetApiErrors();
        Contena.Store.get('contenaApps').selectedIds = [];
        jest.restoreAllMocks();
    });

    it("should keep the user's account type as private even when the company field is set", async () => {
        expect(wrapper.vm).toBeTruthy();

        expect(wrapper.vm.$data.user.accountType).toBe('private');
        expect(wrapper.vm.$data.user.company).toBe('Contena');
    });

    it('should not be able to edit the user', async () => {
        const wrapperWithPrivileges = await createWrapper();

        await flushPromises();

        const saveButton = wrapperWithPrivileges.find('.sw-user-detail__open-edit-mode-action');

        expect(saveButton.attributes()['is-loading']).toBeFalsy();
        expect(saveButton.attributes().disabled).toBe('');

        wrapperWithPrivileges.unmount();
    });

    it('should be able to edit the user', async () => {
        const wrapperWithPrivileges = await createWrapper(['user.editor']);

        await flushPromises();

        const saveButton = wrapperWithPrivileges.find('.sw-user-detail__open-edit-mode-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should accept user registration button called', async () => {
        await wrapper.setData({
            user: {
                active: true,
            },
        });

        await flushPromises();

        expect(wrapper.vm.userGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.userGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-user-detail__customer-registration-alert button:last-child');
        expect(button.attributes().disabled).toBeFalsy();
        await button.trigger('click');

        expect(wrapper.vm.userGroupRegistrationService.accept).toHaveBeenCalled();
    });

    it('should decline user registration button called', async () => {
        await wrapper.setData({
            user: {
                active: true,
            },
        });

        await flushPromises();

        expect(wrapper.vm.userGroupRegistrationService.decline).not.toHaveBeenCalled();
        expect(wrapper.vm.userGroupRegistrationService.accept).not.toHaveBeenCalled();

        const button = wrapper.find('.sw-user-detail__customer-registration-alert button:first-child');
        expect(button.attributes().disabled).toBeFalsy();
        await button.trigger('click');

        expect(wrapper.vm.userGroupRegistrationService.decline).toHaveBeenCalled();
    });

    it('should have company validation when user type is commercial', async () => {
        const wrapperWithPrivileges = await createWrapper(['user.editor'], true);

        await flushPromises();

        wrapperWithPrivileges.vm.createNotificationError = jest.fn();
        const notificationMock = wrapperWithPrivileges.vm.createNotificationError;

        await wrapperWithPrivileges.setData({
            user: {
                id: '1',
                accountType: 'business',
                company: '',
            },
        });

        const saveButton = wrapperWithPrivileges.findComponent('.sw-user-detail__save-action');
        await saveButton.trigger('click');

        expect(notificationMock).toHaveBeenCalledTimes(1);
        expect(notificationMock).toHaveBeenCalledWith({
            message: 'sw-user.detail.messageSaveError',
        });

        wrapperWithPrivileges.vm.createNotificationError.mockRestore();
    });

    it('should get default salutation is value not specified', async () => {
        await flushPromises();

        expect(wrapper.vm.user.salutationId).toBe('1');
    });

    it('should redirect to the user listing when the user does not exist', async () => {
        let resolveCustomer = () => {};
        const userPromise = new Promise((resolve) => {
            resolveCustomer = resolve;
        });
        const wrapperWithMissingCustomer = await createWrapper([], false, userPromise);
        wrapperWithMissingCustomer.vm.createNotificationError = jest.fn();

        resolveCustomer(null);

        await flushPromises();

        expect(wrapperWithMissingCustomer.vm.user).toBeNull();
        expect(wrapperWithMissingCustomer.vm.isLoading).toBe(false);
        expect(wrapperWithMissingCustomer.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-user.detail.messageCustomerNotFound',
        });
        expect(wrapperWithMissingCustomer.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.user.index' });
    });

    it('should show a notification when the user cannot be loaded', async () => {
        const notificationSpy = jest.spyOn(Contena.Store.get('notification'), 'createNotification');
        const wrapperWithLoadingError = await createWrapper([], false, Promise.reject(new Error('Could not load user')));

        await flushPromises();

        expect(wrapperWithLoadingError.vm.user).toBeNull();
        expect(wrapperWithLoadingError.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });
        expect(wrapperWithLoadingError.vm.$router.push).not.toHaveBeenCalled();

        notificationSpy.mockRestore();
    });

    it('should set the initial limit on the addresses association criteria', async () => {
        await flushPromises();

        const criteria = wrapper.vm.defaultCriteria;
        const addressesAssociation = criteria.getAssociation('addresses');

        expect(addressesAssociation.limit).toBe(criteria.limit);
        expect(addressesAssociation.limit).toBe(25);
    });

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapperWithMeteorTabs = await createWrapper([], false, defaultCustomer, {
            featureActive: true,
            routeName: 'sw.user.detail.addresses',
        });

        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-user-detail-tabs');
        expect(tabs.props('defaultItem')).toBe('sw.user.detail.addresses');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-user.detail.tabGeneral',
                name: 'sw.user.detail.base',
                hasError: false,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-user.detail.tabAddresses',
                name: 'sw.user.detail.addresses',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-user.detailBase.labelOrderCard',
                name: 'sw.user.detail.order',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapperWithMeteorTabs.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapperWithMeteorTabs = await createWrapper([], true, defaultCustomer, {
            featureActive: true,
            routerPush,
        });
        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });
        const addressesTab = tabs.props('items').find((item) => item.name === 'sw.user.detail.addresses');

        addressesTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.user.detail.addresses',
            params: { id: 'userId' },
            query: { edit: true },
        });
    });

    it('should pass the general tab error state to meteor tabs', async () => {
        Contena.Store.get('error').addApiError({
            expression: 'user.test.email',
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        const wrapperWithMeteorTabs = await createWrapper([], false, defaultCustomer, {
            featureActive: true,
        });
        const tabs = wrapperWithMeteorTabs.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')[0]).toEqual(
            expect.objectContaining({
                hasError: true,
            }),
        );
    });

    it('should select the displayed user for app action buttons', async () => {
        await flushPromises();

        expect(Contena.Store.get('contenaApps').selectedIds).toEqual(['userId']);
    });

    it('should select the new user for app action buttons when navigating to another user', async () => {
        await flushPromises();

        await wrapper.setProps({ userId: 'otherCustomerId' });
        await flushPromises();

        expect(Contena.Store.get('contenaApps').selectedIds).toEqual(['otherCustomerId']);
    });

    it('should deselect the user for app action buttons when leaving the detail page', async () => {
        await flushPromises();

        expect(Contena.Store.get('contenaApps').selectedIds).toEqual(['userId']);

        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm);

        expect(Contena.Store.get('contenaApps').selectedIds).toEqual([]);
    });
});
