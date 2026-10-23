import { mount } from '@vue/test-utils';
import ContenaError from 'src/core/data/ContenaError';
import Entity from 'src/core/data/entity.data';
import EntityValidationService from 'src/app/service/entity-validation.service';

/**
 * @sw-package checkout
 */

const { CUSTOMER } = Contena.Constants;

async function createWrapper() {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/country',
        status: 200,
        response: {
            data: [
                {
                    id: 'bc05040b-9da1-41ec-93ad-add9d33cd731',
                    attributes: {
                        id: '3a2e625b-f5e1-46d8-9e76-68c0e9b672a1',
                    },
                },
            ],
        },
    });

    return mount(await wrapTestComponent('sw-user-address-form', { sync: true }), {
        props: {
            user: new Entity('userId', 'user', {
                company: 'foo',
            }),
            address: new Entity('1', 'user_address', {
                id: '1',
                company: 'foo',
            }),
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-entity-single-select': true,
                'sw-inheritance-switch': true,
                'sw-field-copyable': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'country') {
                            return {
                                get: (id) => {
                                    if (id) {
                                        return Promise.resolve({
                                            id,
                                            name: 'Germany',
                                        });
                                    }

                                    return Promise.resolve({});
                                },
                            };
                        }

                        return {
                            search: (criteria = {}) => {
                                const countryIdFilter = criteria?.filters.find((item) => item.field === 'countryId');

                                if (countryIdFilter?.value === '1') {
                                    return Promise.resolve([
                                        {
                                            id: 'state1',
                                        },
                                    ]);
                                }
                                return Promise.resolve([]);
                            },
                        };
                    },
                },
            },
        },
    });
}

const MANAGED_FLAG_FIELDS = ['company', 'countryStateId', 'zipcode'];

describe('module/sw-user/page/sw-user-address-form', () => {
    let flagSnapshot = {};

    beforeEach(() => {
        const definition = Contena.EntityDefinition.get('user_address');

        flagSnapshot = Object.fromEntries(
            MANAGED_FLAG_FIELDS.map((field) => [field, definition.properties[field].flags.required]),
        );
    });

    afterEach(() => {
        const definition = Contena.EntityDefinition.get('user_address');

        MANAGED_FLAG_FIELDS.forEach((field) => {
            definition.properties[field].flags.required = flagSnapshot[field];
        });

        Contena.Store.get('error').resetApiErrors();
    });

    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = {
            type: 'not',
            operator: 'or',
            queries: [
                {
                    field: 'id',
                    type: 'equals',
                    value: 'ed643807c9f84cc8b50132ea3ccb1c3b',
                },
            ],
        };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should hide state field if country dont have states', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            address: {
                countryId: '2',
                getEntityName: () => {
                    return 'user_address';
                },
            },
        });

        await flushPromises();

        const stateSelect = wrapper.find('.sw-user-address-form__state-select');
        expect(stateSelect.exists()).toBeFalsy();
    });

    it('should show state field if country has states', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            address: {
                countryId: '1',
                getEntityName: () => {
                    return 'user_address';
                },
            },
        });

        await flushPromises();

        const stateSelect = wrapper.find('.sw-user-address-form__state-select');
        expect(stateSelect.exists()).toBeTruthy();
    });

    it('should mark company as required field when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            user: {
                accountType: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
            },
            address: new Entity('1', 'user_address', {}),
        });

        await flushPromises();

        expect(wrapper.find('label[for="sw-field--address-company"]').classes('is--required')).toBeTruthy();
    });

    it('should not mark company as required when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            user: {
                accountType: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
            },
        });

        await flushPromises();

        expect(wrapper.find('label[for="sw-field--address-company"]').classes('is--required')).toBeFalsy();
    });

    it('should display company, department and vat fields by default when account type is empty', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            user: {
                company: 'contena',
            },
            address: new Entity('1', 'user_address', {
                id: '1',
            }),
        });

        expect(wrapper.find('label[for="sw-field--address-company"]').exists()).toBeTruthy();
        expect(wrapper.find('label[for="sw-field--address-department"]').exists()).toBeTruthy();
    });

    it('should hide the error field when a disabled field', async () => {
        Contena.Store.get('error').addApiError({
            expression: 'user_address.1.firstName',
            error: new ContenaError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
                selfLink: 'user_address.1.firstName',
            }),
        });

        const wrapper = await createWrapper();

        await flushPromises();

        const firstName = wrapper.findAll('.mt-field').at(3);

        expect(wrapper.vm.disabled).toBe(false);
        expect(firstName.classes()).toContain('has--error');
        expect(firstName.find('.mt-field__error').text()).toBe('This value should not be blank.');

        await wrapper.setProps({ disabled: true });
        await flushPromises();

        expect(wrapper.vm.disabled).toBe(true);
        expect(firstName.classes()).not.toContain('has--error');
        expect(firstName.find('.sw-field__error').exists()).toBeFalsy();
    });

    it('should set required attribute based on the configuration of the country', async () => {
        const wrapper = await createWrapper();

        const definition = Contena.EntityDefinition.get('user_address');

        expect(definition.properties.zipcode.flags?.required).toBe(false);
        expect(definition.properties.countryStateId.flags?.required).toBe(false);

        await wrapper.setData({
            country: {
                postalCodeRequired: true,
                forceStateInRegistration: true,
            },
        });

        await flushPromises();

        expect(definition.properties.zipcode.flags?.required).toBe(true);
        expect(definition.properties.countryStateId.flags?.required).toBe(true);
    });

    it('should not inherit the required flags of a previously edited address', async () => {
        const definition = Contena.EntityDefinition.get('user_address');

        definition.properties.zipcode.flags.required = true;
        definition.properties.countryStateId.flags.required = true;

        await createWrapper();

        await flushPromises();

        expect(definition.properties.zipcode.flags.required).toBe(false);
        expect(definition.properties.countryStateId.flags.required).toBe(false);
    });

    it('should reset the country dependent required flags when the form is closed', async () => {
        const wrapper = await createWrapper();

        const definition = Contena.EntityDefinition.get('user_address');

        await wrapper.setData({
            country: {
                postalCodeRequired: true,
                forceStateInRegistration: true,
            },
        });

        await flushPromises();

        expect(definition.properties.zipcode.flags.required).toBe(true);
        expect(definition.properties.countryStateId.flags.required).toBe(true);

        wrapper.unmount();

        expect(definition.properties.zipcode.flags.required).toBe(false);
        expect(definition.properties.countryStateId.flags.required).toBe(false);
    });

    it('should drop the required field errors when the country stops requiring the fields', async () => {
        const wrapper = await createWrapper();
        const errorStore = Contena.Store.get('error');

        await wrapper.setData({
            country: {
                postalCodeRequired: true,
                forceStateInRegistration: true,
            },
        });

        await flushPromises();

        ['zipcode', 'countryStateId'].forEach((field) => {
            errorStore.addApiError({
                expression: `user_address.1.${field}`,
                error: new ContenaError({ code: EntityValidationService.ERROR_CODE_REQUIRED }),
            });
        });

        await wrapper.setData({
            country: {
                postalCodeRequired: false,
                forceStateInRegistration: false,
            },
        });

        expect(errorStore.getApiError(wrapper.vm.address, 'zipcode')).toBeNull();
        expect(errorStore.getApiError(wrapper.vm.address, 'countryStateId')).toBeNull();
    });

    it('should keep a server reported error when the form is mounted and when it is closed', async () => {
        const errorStore = Contena.Store.get('error');

        errorStore.addApiError({
            expression: 'user_address.1.zipcode',
            error: new ContenaError({ code: 'ZIPCODE_IS_TOO_LONG' }),
        });

        const wrapper = await createWrapper();

        await flushPromises();

        expect(errorStore.getApiError(wrapper.vm.address, 'zipcode')).toBeInstanceOf(ContenaError);

        wrapper.unmount();

        expect(errorStore.getApiError({ getEntityName: () => 'user_address', id: '1' }, 'zipcode')).toBeInstanceOf(
            ContenaError,
        );
    });

    it('should set user company for new user', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.user.markAsNew();
        wrapper.vm.address.company = 'bar';

        await flushPromises();

        expect(wrapper.vm.user.company).toBe('bar');
    });

    it('should not change user company for existing user', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.address.company = 'bar';

        await flushPromises();

        expect(wrapper.vm.user.company).toBe('foo');
    });
});
