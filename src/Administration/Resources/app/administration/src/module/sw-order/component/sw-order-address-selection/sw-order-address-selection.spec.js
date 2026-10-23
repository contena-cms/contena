import { DOMWrapper, mount } from '@vue/test-utils';
import ContenaError from 'src/core/data/ContenaError';
import EntityValidationService from 'src/app/service/entity-validation.service';

/**
 * @sw-package checkout
 */

const { Context } = Contena;
const { EntityCollection } = Contena.Data;

function createCustomerMock() {
    return {
        id: '63e27affb5804538b5b06cb4e344b130',
        addresses: new EntityCollection('/user_address', 'user_address', Context.api, null, [
            {
                street: 'Stehr Divide',
                zipcode: '64885-2245',
                city: 'Faheyshire',
                id: '652e9e571cc94bd898077f256dcf629f',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                hash: 'isUnique',
                getEntityName: () => 'user_address',
            },
            {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: '652e9e571cc94bd898077f256dcf6233',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                countryState: {
                    translated: {
                        name: 'NRW',
                    },
                },
                hash: 'isDuplicate',
                getEntityName: () => 'user_address',
            },
        ]),
    };
}

async function createWrapper(propsData, userResponse = createCustomerMock()) {
    // The user is shared between all address selections of an order, so it is loaded through the store.
    jest.spyOn(Contena.Store.get('swOrderDetail'), 'loadCustomer').mockImplementation(() => {
        Contena.Store.get('swOrderDetail').user = userResponse;

        return Promise.resolve(userResponse);
    });

    return mount(await wrapTestComponent('sw-order-address-selection', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `<li :class="componentClasses" class="sw-select-result" @click.stop="onClickResult">
                        <slot></slot></li>`,
                    methods: {
                        onClickResult() {
                            Contena.Utils.EventBus.emit('item-select', this.item);
                        },
                    },
                    computed: {
                        componentClasses() {
                            return [`sw-select-option--${this.index}`];
                        },
                    },
                },
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                'sw-single-select': await wrapTestComponent('sw-single-select', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-user-address-form': await wrapTestComponent('sw-user-address-form'),
                'sw-address': {
                    props: ['formattingAddress'],
                    template: '<div class="sw-address">{{ formattingAddress }}</div>',
                },
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-user-address-form-options': await wrapTestComponent('sw-user-address-form-options'),
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'router-link': true,
                'sw-blog-variant-info': true,
                'sw-field-copyable': true,
                'sw-contextual-field': true,
                'sw-checkbox-field': true,
                'sw-custom-field-set-renderer': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([]);
                        },
                        save: () => {
                            return Promise.resolve();
                        },
                        create: () => ({
                            _isNew: true,
                            getEntityName: () => 'user_address',
                        }),
                    }),
                },
                customSnippetApiService: {
                    render: (address) => {
                        return Promise.resolve({
                            rendered: `${address.street}, ${address.zipcode} ${address.city}`,
                        });
                    },
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
        props: {
            address: {
                street: 'Denesik Bridge',
                zipcode: '05132',
                city: 'Bernierstad',
                company: 'Muster SE',
                department: 'People & Culture',
                id: '38e8895864a649a1b2ec806dad02ab87',
                country: {
                    translated: {
                        name: 'Buzbach',
                    },
                },
                countryState: {
                    translated: {
                        name: 'NRW',
                    },
                },
                hash: 'isDuplicate',
                getEntityName: () => 'order_address',
            },
            addressId: '38e8895864a649a1b2ec806dad02ab87',
            type: 'billing',
            ...propsData,
        },
    });
}

describe('src/module/sw-order/component/sw-order-address-selection', () => {
    let wrapper;

    beforeEach(async () => {
        const orderDetailStore = Contena.Store.get('swOrderDetail');

        orderDetailStore.$reset();
        orderDetailStore.resetCustomer();
        orderDetailStore.versionContext = {};
        orderDetailStore.order = {
            addresses: [
                {
                    street: 'Denesik Bridge',
                    zipcode: '05132',
                    city: 'Bernierstad',
                    id: '38e8895864a649a1b2ec806dad02ab87',
                    country: {
                        translated: {
                            name: 'Buzbach',
                        },
                    },
                },
            ],
            billingAddressId: '38e8895864a649a1b2ec806dad02ab87',
            orderCustomer: {
                userId: '63e27affb5804538b5b06cb4e344b130',
            },
        };

        wrapper = await createWrapper();
    });

    it('should be able to edit address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.findComponent('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const selectEdit = new DOMWrapper(document.body).get('.sw-select-option--0');

        await selectEdit.get('.sw-context-menu-item').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.currentAddress).toMatchObject({
            street: 'Denesik Bridge',
            zipcode: '05132',
            city: 'Bernierstad',
            company: 'Muster SE',
            id: '38e8895864a649a1b2ec806dad02ab87',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            countryState: {
                translated: {
                    name: 'NRW',
                },
            },
            department: 'People & Culture',
            hash: 'isDuplicate',
        });
    });

    it('should be able to change the address', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        expect(addressSelection.find('.sw-single-select__selection-text').text()).toBe(
            'Muster SE - People & Culture, Denesik Bridge, 05132 Bernierstad, NRW, Buzbach',
        );

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const select = new DOMWrapper(document.body).get('.sw-select-option--1');

        await select.trigger('click');
        await flushPromises();

        expect(wrapper.emitted('change-address')).toBeTruthy();
        expect(wrapper.emitted('change-address')[0]).toEqual([
            {
                orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                userAddressId: '652e9e571cc94bd898077f256dcf629f',
                type: 'billing',
                edited: false,
            },
        ]);
    });

    it('should be able to create new address', async () => {
        expect(wrapper.vm.currentAddress).toBeNull();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const newAddress = new DOMWrapper(document.body).get('.sw-select-result-list__content ul:nth-of-type(1)');

        await newAddress.get('.sw-select-result__add-new-address').trigger('click');
        await flushPromises();

        expect(wrapper.vm.currentAddress._isNew).toBe(true);
        expect(wrapper.vm.currentAddress.userId).toBe('63e27affb5804538b5b06cb4e344b130');
        expect(wrapper.find('.sw-user-address-form')).toBeTruthy();
    });

    it('should not offer to create a new address when the user was deleted', async () => {
        wrapper = await createWrapper({}, null);
        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.vm.user).toBeNull();
        expect(new DOMWrapper(document.body).find('.sw-select-result__add-new-address').exists()).toBeFalsy();
        expect(new DOMWrapper(document.body).findAll('.sw-select-result')).toHaveLength(1);
    });

    it('should select a newly created address after saving it', async () => {
        wrapper = await createWrapper({
            type: 'shipping',
        });

        await flushPromises();

        wrapper.vm.createNewCustomerAddress();
        Object.assign(wrapper.vm.currentAddress, {
            id: 'newCustomerAddressId',
            firstName: 'Ada',
            lastName: 'Lovelace',
            street: 'Example Street 1',
            zipcode: '12345',
            city: 'Example City',
            countryId: 'countryId',
        });

        wrapper.vm.isValidAddress = jest.fn(() => true);

        await wrapper.vm.onSaveAddress();

        expect(wrapper.emitted('change-address')).toEqual([
            [
                {
                    orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                    userAddressId: 'newCustomerAddressId',
                    type: 'shipping',
                    edited: false,
                },
            ],
        ]);
    });

    it('should read the user from the order detail store so all address selections stay in sync', async () => {
        await flushPromises();

        expect(wrapper.vm.user).toBe(Contena.Store.get('swOrderDetail').user);
        expect(Contena.Store.get('swOrderDetail').loadCustomer).toHaveBeenCalledWith('63e27affb5804538b5b06cb4e344b130');
    });

    it('should write the user back to the store, so inheriting modules can still replace it', async () => {
        await flushPromises();

        const replacement = createCustomerMock();
        replacement.id = 'replacedCustomerId';

        wrapper.vm.user = replacement;

        expect(Contena.Store.get('swOrderDetail').user.id).toBe('replacedCustomerId');
        expect(wrapper.vm.user).toBe(Contena.Store.get('swOrderDetail').user);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the `userCriteria` computed property.
    it.deprecated('v6.8.0.0')('should still provide the deprecated userCriteria', async () => {
        await flushPromises();

        const { associations } = wrapper.vm.userCriteria.parse();

        expect(Object.keys(associations)).toEqual(['addresses']);
        expect(Object.keys(associations.addresses.associations)).toEqual(['country']);
    });

    it('should reload the shared user after saving an address, so it is selectable in the other selections', async () => {
        await flushPromises();

        wrapper.vm.createNewCustomerAddress();
        Object.assign(wrapper.vm.currentAddress, {
            id: 'newCustomerAddressId',
            firstName: 'Ada',
            lastName: 'Lovelace',
            street: 'Example Street 1',
            zipcode: '12345',
            city: 'Example City',
            countryId: 'countryId',
        });

        wrapper.vm.isValidAddress = jest.fn(() => true);

        await wrapper.vm.onSaveAddress();

        expect(Contena.Store.get('swOrderDetail').loadCustomer).toHaveBeenCalledWith(
            '63e27affb5804538b5b06cb4e344b130',
            true,
        );
    });

    it('should keep id on options for addresses where id is not enumerable via spread', async () => {
        await flushPromises();

        const newAddressId = 'new-user-address-without-enumerable-id';
        const draft = {
            street: 'Ada Street 1',
            zipcode: '12345',
            city: 'Example City',
            country: {
                translated: {
                    name: 'Buzbach',
                },
            },
            hash: 'brandNewAddress',
            getEntityName: () => 'user_address',
        };
        // Mimic Entity proxy: id is readable but missing from Object spread / own keys
        const entityLikeAddress = new Proxy(draft, {
            get(target, property) {
                if (property === 'id') {
                    return newAddressId;
                }

                return target[property];
            },
        });

        wrapper.vm.user.addresses.push(entityLikeAddress);

        const option = wrapper.vm.addressOptions.find((item) => item.street === 'Ada Street 1');

        expect(option).toBeDefined();
        expect(option.id).toBe(newAddressId);
        // Selecting uses option.id as userAddressId; without the explicit assignment this is undefined
        expect({ ...draft }.id).toBeUndefined();

        wrapper.vm.onAddressChange(option.id);

        expect(wrapper.emitted('change-address').at(-1)).toEqual([
            {
                orderAddressId: '38e8895864a649a1b2ec806dad02ab87',
                userAddressId: newAddressId,
                type: 'billing',
                edited: false,
            },
        ]);
    });

    it('should be able to get the options with props', async () => {
        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = new DOMWrapper(document.body).get('.sw-select-result-list__item-list');

        expect(list.findAll('.sw-select-result')).toHaveLength(2);

        const firstSelection = list.get('.sw-select-result .sw-order-address-selection__information');
        expect(firstSelection.findAll('p').at(1).text()).toBe('Muster SE - People & Culture');
        expect(firstSelection.findAll('p').at(2).text()).toBe('Denesik Bridge');
        expect(firstSelection.findAll('p').at(3).text()).toBe('05132 Bernierstad');
        expect(firstSelection.findAll('p').at(4).text()).toBe('Buzbach');

        const secondSelection = list.findAll('.sw-select-result .sw-order-address-selection__information').at(1);
        expect(secondSelection.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(secondSelection.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
        expect(secondSelection.findAll('p').at(3).text()).toBe('Buzbach');
    });

    it('should be able to get the options with not props', async () => {
        wrapper = await createWrapper({
            address: null,
            addressId: null,
        });

        await flushPromises();

        const addressSelection = wrapper.find('.sw-order-address-selection');

        await addressSelection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        const list = new DOMWrapper(document.body).get('.sw-select-result-list__item-list');

        const information = list.get('.sw-select-result .sw-order-address-selection__information');

        expect(list.findAll('.sw-select-result')).toHaveLength(2);
        expect(information.findAll('p').at(1).text()).toBe('Stehr Divide');
        expect(information.findAll('p').at(2).text()).toBe('64885-2245 Faheyshire');
        expect(information.findAll('p').at(3).text()).toBe('Buzbach');
    });

    it('should report and clear required field errors when validating an address', async () => {
        await flushPromises();

        const errorStore = Contena.Store.get('error');
        jest.spyOn(Contena.EntityDefinition, 'getRequiredFields').mockReturnValue({
            firstName: {},
            lastName: {},
        });

        const address = {
            id: 'new-address-id',
            firstName: '',
            lastName: 'Lovelace',
            getEntityName: () => 'user_address',
        };

        errorStore.addApiError({
            expression: 'user_address.new-address-id.lastName',
            error: new ContenaError({ code: EntityValidationService.ERROR_CODE_REQUIRED }),
        });

        expect(wrapper.vm.isValidAddress(address)).toBe(false);

        expect(errorStore.getApiError(address, 'firstName')).toBeInstanceOf(ContenaError);
        expect(errorStore.getApiError(address, 'lastName')).toBeNull();
    });

    it('should keep a server reported error when validating an address', async () => {
        await flushPromises();

        const errorStore = Contena.Store.get('error');
        jest.spyOn(Contena.EntityDefinition, 'getRequiredFields').mockReturnValue({
            firstName: {},
            lastName: {},
        });

        const address = {
            id: 'new-address-id',
            firstName: '',
            lastName: 'Lovelace',
            getEntityName: () => 'user_address',
        };

        errorStore.addApiError({
            expression: 'user_address.new-address-id.lastName',
            error: new ContenaError({ code: 'LAST_NAME_IS_TOO_LONG' }),
        });

        expect(wrapper.vm.isValidAddress(address)).toBe(false);

        expect(errorStore.getApiError(address, 'lastName')).toBeInstanceOf(ContenaError);
    });

    it('should not leave any warnings on the order page when the address modal is closed', async () => {
        await flushPromises();

        const errorStore = Contena.Store.get('error');

        wrapper.vm.currentAddress = {
            id: 'closed-address-id',
            getEntityName: () => 'user_address',
        };

        await expect(wrapper.vm.onSaveAddress()).rejects.toBeUndefined();

        expect(errorStore.getErrorsForEntity('user_address', 'closed-address-id')).not.toBeNull();

        wrapper.vm.currentAddress = null;
        await flushPromises();

        expect(errorStore.getErrorsForEntity('user_address', 'closed-address-id')).toBeNull();
    });

    it('should keep a server reported error when the address modal is closed', async () => {
        await flushPromises();

        const errorStore = Contena.Store.get('error');
        const address = { id: 'kept-address-id', getEntityName: () => 'user_address' };

        errorStore.addApiError({
            expression: 'user_address.kept-address-id.additionalAddressLine1',
            error: new ContenaError({ code: 'ADDITIONAL_ADDR1_IS_TOO_LONG' }),
        });

        wrapper.vm.currentAddress = address;
        await flushPromises();

        wrapper.vm.currentAddress = null;
        await flushPromises();

        expect(errorStore.getApiError(address, 'additionalAddressLine1')).toBeInstanceOf(ContenaError);
    });

    it('should clear pending required field errors when the component is torn down', async () => {
        await flushPromises();

        const errorStore = Contena.Store.get('error');
        const address = { id: 'torn-down-address-id', getEntityName: () => 'user_address' };

        errorStore.addApiError({
            expression: 'user_address.torn-down-address-id.firstName',
            error: new ContenaError({ code: EntityValidationService.ERROR_CODE_REQUIRED }),
        });

        wrapper.vm.currentAddress = address;
        await flushPromises();

        wrapper.unmount();

        expect(errorStore.getApiError(address, 'firstName')).toBeNull();
    });

    it('should show a notification when trying to save an invalid address', async () => {
        await flushPromises();

        const notificationSpy = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.currentAddress = {
            id: 'new-address-id',
            firstName: '',
            getEntityName: () => 'user_address',
        };

        await expect(wrapper.vm.onSaveAddress()).rejects.toBeUndefined();
        expect(notificationSpy).toHaveBeenCalled();
    });

    it('renders the selected address details below the select', async () => {
        await flushPromises();

        const selectedAddress = wrapper.find('.sw-order-address-selection__selected-address');
        const selectedAddressContent = selectedAddress.find('.sw-address');

        expect(selectedAddress.exists()).toBe(true);
        expect(selectedAddressContent.text()).toBe('Denesik Bridge, 05132 Bernierstad');
    });
});
