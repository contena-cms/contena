import { mount } from '@vue/test-utils';
import ContenaError from 'src/core/data/ContenaError';

/**
 * @sw-package checkout
 */

const { Context } = Contena;
const { EntityCollection } = Contena.Data;

async function createWrapper({
    repositoryMocks = {
        userRepositoryMock: undefined,
        languageRepositoryMock: undefined,
    },
} = {}) {
    return mount(await wrapTestComponent('sw-order-new-user-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
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
                    template: '<div class="mt-tabs"></div>',
                },
                'sw-user-address-form': true,
                'sw-user-base-form': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-loader': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'user') {
                            if (repositoryMocks.userRepositoryMock) {
                                return repositoryMocks.userRepositoryMock;
                            }

                            return {
                                create: () => {
                                    return {
                                        id: '1',
                                        addresses: new EntityCollection(
                                            '/user_address',
                                            'user_address',
                                            Context.api,
                                            null,
                                            [],
                                        ),
                                    };
                                },
                                save: () => Promise.resolve(),
                            };
                        }

                        if (entity === 'language') {
                            if (repositoryMocks.languageRepositoryMock) {
                                return repositoryMocks.languageRepositoryMock;
                            }

                            return {
                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['1'],
                                    }),
                            };
                        }

                        if (entity === 'salutation') {
                            return {
                                searchIds: () =>
                                    Promise.resolve({
                                        total: 1,
                                        data: ['salutationId'],
                                    }),
                            };
                        }

                        if (entity === 'user_address') {
                            return {
                                create: () => {
                                    return {
                                        id: 'new-shipping-address-id',
                                    };
                                },
                            };
                        }

                        return {
                            create: () => Promise.resolve(),
                        };
                    },
                },
                numberRangeService: {
                    reverse: () => Promise.resolve(),
                },
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({
                            'core.loginRegistration.passwordMinLength': 8,
                        });
                    },
                },
                userValidationService: {
                    checkCustomerEmail: () => Promise.resolve(),
                },
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-new-user-modal', () => {
    let wrapper;

    beforeEach(async () => {
        Contena.Store.get('error').resetApiErrors();
        wrapper = await createWrapper();
    });

    it('should not leave any warnings behind when the modal is closed', async () => {
        const errorStore = Contena.Store.get('error');
        const billingAddressMock = {
            getEntityName: () => 'user_address',
            id: wrapper.vm.billingAddress.id,
        };
        const userMock = {
            getEntityName: () => 'user',
            id: wrapper.vm.user.id,
        };

        wrapper.vm.createErrorMessageForCompanyField();
        errorStore.addApiError({
            expression: `user.${userMock.id}.email`,
            error: new ContenaError({ code: 'CONTENT__CUSTOMER_EMAIL_NOT_UNIQUE' }),
        });

        expect(errorStore.getApiError(billingAddressMock, 'company')).toBeInstanceOf(ContenaError);
        expect(errorStore.getApiError(userMock, 'email')).toBeInstanceOf(ContenaError);

        wrapper.vm.onClose();

        expect(errorStore.getApiError(billingAddressMock, 'company')).toBeNull();
        expect(errorStore.getApiError(userMock, 'email')).toBeNull();
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy new-user tabs.
    it.deprecated('v6.8.0.0')('should render the fallback tabs branch', () => {
        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        wrapper = await createWrapper();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-order-new-user-modal');
        expect(tabs.props('defaultItem')).toBe('details');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-order.newCustomerModal.labelDetails',
                name: 'details',
                hasError: false,
            },
            {
                label: 'sw-order.createBase.detailsBody.labelBillingAddress',
                name: 'billingAddress',
                hasError: false,
            },
            {
                label: 'sw-order.createBase.detailsBody.labelShippingAddress',
                name: 'shippingAddress',
                hasError: false,
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.find('sw-user-base-form-stub').exists()).toBe(true);
        expect(wrapper.find('sw-user-address-form-stub').exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should switch meteor tab content when the active tab changes', async () => {
        wrapper = await createWrapper();

        wrapper.getComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'billingAddress');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('billingAddress');
        expect(wrapper.find('sw-user-base-form-stub').exists()).toBe(false);
        expect(wrapper.find('sw-user-address-form-stub').exists()).toBe(true);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should pass validation errors to meteor tabs', async () => {
        wrapper = await createWrapper();

        Contena.Store.get('error').addApiError({
            expression: 'user.1.email',
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        Contena.Store.get('error').addApiError({
            expression: 'user_address.1.street',
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('items')).toEqual([
            {
                label: 'sw-order.newCustomerModal.labelDetails',
                name: 'details',
                hasError: true,
            },
            {
                label: 'sw-order.createBase.detailsBody.labelBillingAddress',
                name: 'billingAddress',
                hasError: true,
            },
            {
                label: 'sw-order.createBase.detailsBody.labelShippingAddress',
                name: 'shippingAddress',
                hasError: false,
            },
        ]);

        wrapper.vm.user.defaultShippingAddressId = 'different-shipping-address-id';
        await wrapper.vm.$nextTick();

        expect(wrapper.getComponent({ name: 'mt-tabs' }).props('items')[2]).toEqual({
            label: 'sw-order.createBase.detailsBody.labelShippingAddress',
            name: 'shippingAddress',
            hasError: true,
        });
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy new-user tabs.
    it.deprecated('v6.8.0.0')('should navigate tab correctly', async () => {
        let userBaseForm = wrapper.find('sw-user-base-form-stub');
        let userAddressForm = wrapper.find('sw-user-address-form-stub');

        expect(userBaseForm.exists()).toBeTruthy();
        expect(userAddressForm.exists()).toBeFalsy();

        const tabItems = wrapper.findAll('.sw-tabs-item');
        await tabItems.at(1).trigger('click');

        userBaseForm = wrapper.find('sw-user-base-form-stub');
        userAddressForm = wrapper.find('sw-user-address-form-stub');

        expect(userBaseForm.exists()).toBeFalsy();
        expect(userAddressForm.exists()).toBeTruthy();
    });

    it('should override context when the sales channel does not exist language compared to the API language', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            repositoryMocks: {
                userRepositoryMock: {
                    create: () => ({
                        id: '1',
                        addresses: new EntityCollection('/user_address', 'user_address', Context.api, null, []),
                    }),
                    save: jest.fn((user, context) => Promise.resolve(context)),
                },
            },
        });

        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        await wrapper.setData({
            user: {
                id: '1',
                email: 'admin_user@domain.com',
                accountType: 'business',
                password: 'contena',
                channelId: 'a7921464677a4ef591683d144beecd24',
                company: 'Contena',
            },
        });

        expect(await wrapper.vm.languageId).toBe('1');

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toBe('1');
    });

    it('should keep context when sales channel exists language compared to API language', async () => {
        await wrapper.unmount();
        wrapper = await createWrapper({
            repositoryMocks: {
                languageRepositoryMock: {
                    searchIds: () =>
                        Promise.resolve({
                            total: 1,
                            data: [Contena.Context.api.languageId],
                        }),
                },
                userRepositoryMock: {
                    create: () => ({
                        id: '1',
                        addresses: new EntityCollection('/user_address', 'user_address', Context.api, null, []),
                    }),
                    save: jest.fn((user, context) => Promise.resolve(context)),
                },
            },
        });

        wrapper.vm.validateEmail = jest.fn().mockImplementation(() => Promise.resolve({ isValid: true }));

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        await wrapper.setData({
            user: {
                id: '1',
                email: 'admin_user@domain.com',
                accountType: 'business',
                password: 'contena',
                channelId: 'a7921464677a4ef591683d144beecd24',
                company: 'Contena',
            },
        });

        expect(await wrapper.vm.languageId).toEqual(Contena.Context.api.languageId);

        const context = await wrapper.vm.onSave();

        expect(context.languageId).toEqual(Contena.Context.api.languageId);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy new-user tabs.
    it.deprecated('v6.8.0.0')('should show error inside sw-tabs-item component', async () => {
        let swDetailsTab = wrapper.findAll('.sw-tabs-item').at(0);
        let swBillingAddressTab = wrapper.findAll('.sw-tabs-item').at(1);

        expect(swDetailsTab.find('.mt-icon').exists()).toBe(false);
        expect(swBillingAddressTab.find('.mt-icon').exists()).toBe(false);

        Contena.Store.get('error').addApiError({
            expression: 'user.1.email',
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        wrapper.vm.userRepository.save = jest.fn(() => Promise.resolve());

        const saveButton = wrapper.findByText('button', 'global.default.save');

        await saveButton.trigger('click');

        swDetailsTab = wrapper.findAll('.sw-tabs-item').at(0);
        swBillingAddressTab = wrapper.findAll('.sw-tabs-item').at(1);

        expect(swDetailsTab.find('.mt-icon.icon--solid-exclamation-circle').exists()).toBe(true);
        expect(swBillingAddressTab.find('.mt-icon').exists()).toBe(false);
    });

    it('should get default salutation is value not specified', async () => {
        expect(wrapper.vm.user.salutationId).toBe('salutationId');
    });

    it('should set defaultShippingAddressId to defaultBillingAddressId when newValue is true', async () => {
        await wrapper.setData({
            user: {
                ...wrapper.vm.user,
                defaultBillingAddressId: 'billing-address-id',
                isNew: jest.fn(() => false),
            },
        });

        wrapper.vm.isSameBilling = true;
        expect(wrapper.vm.user.defaultShippingAddressId).toBe('billing-address-id');
    });

    it('should remove all addresses but default billing when user is new and newValue is true', async () => {
        await wrapper.setData({
            user: {
                ...wrapper.props().user,
                defaultBillingAddressId: 'billing-address-id',
                shippingAddressId: 'shipping-address-id',
                addresses: new EntityCollection('/user_address', 'user_address', Context.api, null, [
                    { id: 'billing-address-id' },
                    { id: 'shipping-address-id' },
                ]),
                isNew: jest.fn(() => true),
            },
        });

        wrapper.vm.isSameBilling = true;

        expect(wrapper.vm.user.addresses.has('shipping-address-id')).toBe(false);
        expect(wrapper.vm.user.addresses.has('billing-address-id')).toBe(true);
    });

    it('should create a new shipping address when newValue is false', async () => {
        await wrapper.setData({
            user: {
                ...wrapper.props().user,
                defaultBillingAddressId: 'billing-address-id',
                shippingAddressId: 'shipping-address-id',
                addresses: new EntityCollection('/user_address', 'user_address', Context.api, null, [
                    { id: 'billing-address-id' },
                    { id: 'shipping-address-id' },
                ]),
                isNew: jest.fn(() => true),
            },
        });

        wrapper.vm.isSameBilling = true;
        wrapper.vm.isSameBilling = false;

        expect(wrapper.vm.user.defaultShippingAddressId).toBe('new-shipping-address-id');
        expect(wrapper.vm.user.addresses.has('new-shipping-address-id')).toBe(true);
        expect(wrapper.vm.defaultSalutationId).toBe('salutationId');
        expect(wrapper.vm.user.addresses.get('new-shipping-address-id').salutationId).toBe('salutationId');
    });

    it('should does not change defaultShippingAddressId if newValue is the same as isSameBilling', async () => {
        await wrapper.setData({
            user: {
                ...wrapper.vm.user,
                defaultBillingAddressId: 'billing-address-id',
                defaultShippingAddressId: 'billing-address-id',
                isNew: jest.fn(() => false),
            },
        });

        const originalShippingAddressId = wrapper.vm.user.defaultShippingAddressId;
        wrapper.vm.isSameBilling = true;

        expect(wrapper.vm.user.defaultShippingAddressId).toBe(originalShippingAddressId);
    });
});
