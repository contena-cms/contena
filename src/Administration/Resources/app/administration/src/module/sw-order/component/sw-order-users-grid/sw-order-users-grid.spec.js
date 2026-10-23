/* eslint-disable sw-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

let userData = [];

function setCustomerData(users) {
    userData = [...users];
    userData.total = users.length;
    userData.criteria = {
        page: 1,
        limit: 5,
    };
}

const users = generateCustomers();

const contextState = {
    id: 'context',
    state: () => ({
        api: {
            languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        },
    }),
    actions: {
        resetLanguageToDefault: jest.fn(),
        setApiLanguageId: jest.fn(function (newLanguageId) {
            this.api.languageId = newLanguageId;
        }),
    },
    getters: {
        isSystemDefaultLanguage: () => false,
    },
};

function generateCustomers() {
    const items = [];

    for (let i = 1; i <= 10; i += 1) {
        items.push({
            id: i,
            firstName: `Quynh ${i}`,
            lastName: 'Nguyen',
            email: `quynh${i}@example.com`,
            channelId: '1234',
            userNumber: `sw${i}`,
            channel: {
                translated: {
                    name: 'Frontend',
                },
            },
            boundSalesChannel: {
                translated: {
                    name: 'Frontend',
                },
            },
            boundSalesChannelId: '1234',
        });
    }

    return items;
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-user-grid', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: `
                        <div class="sw-card__content">
                            <slot name="toolbar"></slot>
                            <slot name="grid"></slot>
                        </div>
                    `,
                },
                'mt-number-field': {
                    template: '<input class="mt-number-field" type="number" v-model="value" />',
                    props: {
                        value: 0,
                    },
                },
                'sw-checkbox-field': {
                    template: '<input class="sw-checkbox-field" type="checkbox" v-model="value" />',
                    props: {
                        value: false,
                    },
                },
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-pagination': await wrapTestComponent('sw-pagination', {
                    sync: true,
                }),
                'sw-blog-variant-info': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-context-button': {
                    template: '<div class="sw-context-button"><slot></slot></div>',
                },
                'sw-context-menu-item': true,
                'sw-card-filter': {
                    data() {
                        return { term: '' };
                    },
                    template: `
                        <div>
                            <input class="sw-card-filter" :value="term" @input="$emit(\'sw-card-filter-term-change\', $event.target.value)">
                            <slot name="filter"></slot>
                        </div>
                    `,
                },
                'sw-field': true,
                'router-link': true,
                'sw-order-new-user-modal': true,
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', { sync: true }),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result': {
                    props: ['item', 'index'],
                    template: `
                        <li class="sw-select-result" @click.stop="onClickResult">
                            <slot></slot>
                        </li>`,
                    methods: {
                        onClickResult() {
                            this.$parent.$parent.$emit('item-select', this.item);
                        },
                    },
                },
                'sw-bulk-edit-modal': true,

                'sw-data-grid-inline-edit': true,
                'sw-data-grid-column-boolean': true,
                'sw-select-field': true,
                'sw-highlight-text': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-field-error': true,
                'sw-popover': {
                    template: '<div class="sw-popover"><slot></slot></div>',
                },
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            provide: {
                searchRankingService: () => {},
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'channel') {
                                return Promise.resolve([
                                    {
                                        id: '1234',
                                        name: 'Lazada',
                                        languageId: '8888',
                                    },
                                    {
                                        id: '123456',
                                        name: 'Tiki',
                                        languageId: '5678',
                                    },
                                ]);
                            }

                            return Promise.resolve(userData);
                        },
                        get: () => Promise.resolve({ ...users[0] }),
                        searchIds: () =>
                            Promise.resolve({
                                data: users.map((user) => user.channelId),
                            }),
                    }),
                },
            },
            mocks: {
                $t: (key, value) => {
                    if (!value) {
                        return key;
                    }
                    return key + JSON.stringify(value);
                },
            },
        },
    });
}

describe('src/module/sw-order/view/sw-order-user-grid', () => {
    beforeAll(() => {
        Contena.Service().register('contextStoreService', () => {
            return {
                updateCustomerContext: () =>
                    Promise.resolve({
                        status: 200,
                    }),
                getSalesChannelContext: () =>
                    Promise.resolve({
                        data: {
                            channelId: '1234',
                        },
                    }),
            };
        });

        Contena.Service().register('cartStoreService', () => {
            return {
                getCart: () =>
                    Promise.resolve({
                        data: {
                            token: 'HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2',
                            lineItems: [],
                        },
                    }),
                createCart: () =>
                    Promise.resolve({
                        data: {
                            token: 'HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2',
                        },
                    }),
            };
        });

        Contena.Store.get('swOrder').setCart({ token: '', lineItems: [] });
        Contena.Store.get('swOrder').setContext({ user: {} });

        if (Contena.Store.get('context')) {
            Contena.Store.unregister('context');
        }

        Contena.Store.register(contextState);
    });

    it('should show empty state view when there is no user', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-empty-state').exists()).toBeTruthy();
    });

    it('should show empty title correctly', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe('sw-user.list.messageEmpty');

        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Hello World');
        await searchField.trigger('input');

        expect(wrapper.find('.mt-empty-state__headline').text()).toBe(
            'sw-order.initialModal.userGrid.textEmptySearch{"name":"Hello World"}',
        );
    });

    it('should show user grid', async () => {
        setCustomerData(users);

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-empty-state').exists()).toBeFalsy();

        const gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(users.length);
    });

    it('should able to search user', async () => {
        setCustomerData(users);

        const wrapper = await createWrapper();
        await flushPromises();

        let gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(users.length);

        setCustomerData([{ ...users[1] }]);
        const searchField = wrapper.find('.sw-card-filter');

        await searchField.setValue('Quynh 2');
        await searchField.trigger('input');

        gridBody = wrapper.find('.sw-data-grid__body');
        expect(gridBody.findAll('.sw-data-grid__row')).toHaveLength(1);
    });

    it('should open add new user modal', async () => {
        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const buttonAddCustomer = wrapper.find('.sw-order-user-grid__add-user');
        let modalAddCustomer = wrapper.find('sw-order-new-user-modal-stub');

        expect(modalAddCustomer.exists()).toBeFalsy();

        await buttonAddCustomer.trigger('click');

        modalAddCustomer = wrapper.find('sw-order-new-user-modal-stub');
        expect(modalAddCustomer.exists()).toBeTruthy();
    });

    it('should refresh grid list after adding new user successfully', async () => {
        users[0].boundSalesChannelId = users[0].channelId;

        setCustomerData([]);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyGetList = jest.spyOn(wrapper.vm, 'getList');
        const buttonAddCustomer = wrapper.find('.sw-order-user-grid__add-user');
        await buttonAddCustomer.trigger('click');

        const modalAddCustomer = wrapper.findComponent('sw-order-new-user-modal-stub');
        modalAddCustomer.vm.$emit('on-select-existing-user', 'user1');

        expect(spyGetList).toHaveBeenCalled();
    });

    it('should create cart after selecting a user if there is no cart token', async () => {
        setCustomerData(users);

        const wrapper = await createWrapper();
        await flushPromises();

        const spyCreateCart = jest.spyOn(wrapper.vm, 'createCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(spyCreateCart).toHaveBeenCalled();
    });

    it('should update user context and cart after selecting a user', async () => {
        setCustomerData(users);
        Contena.Store.get('swOrder').setCartToken('1d8af3ddddbd378ba0065debd5e4e4b1');

        const wrapper = await createWrapper();

        wrapper.vm.userRepository.get = jest.fn(() => Promise.resolve(users[0]));
        const spyUpdateCustomerContext = jest.spyOn(wrapper.vm, 'updateCustomerContext');
        const spyGetCart = jest.spyOn(wrapper.vm, 'getCart');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);
        await flushPromises();

        expect(spyUpdateCustomerContext).toHaveBeenCalled();

        await flushPromises();

        expect(spyGetCart).toHaveBeenCalled();
    });

    it('should show a dedicated maintenance mode error when switching user fails', async () => {
        const user = {
            ...users[0],
            channelId: '1234',
            boundSalesChannelId: '1234',
        };

        setCustomerData([user]);
        Contena.Store.get('swOrder').setCustomer(null);
        Contena.Store.get('swOrder').setCartToken('1d8af3ddddbd378ba0065debd5e4e4b1');

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.userRepository.get = jest.fn(() => Promise.resolve(user));
        wrapper.vm.$t = jest.fn((key) => {
            if (key === 'global.error-codes.FRAMEWORK__API_CHANNEL_MAINTENANCE_MODE') {
                return 'Translated maintenance mode error';
            }

            return key;
        });

        const updateCustomerContextSpy = jest.spyOn(wrapper.vm, 'updateCustomerContext').mockRejectedValue({
            response: {
                data: {
                    errors: [
                        {
                            code: 'FRAMEWORK__API_CHANNEL_MAINTENANCE_MODE',
                        },
                    ],
                },
            },
        });

        const createNotificationErrorSpy = jest.spyOn(wrapper.vm, 'createNotificationError').mockImplementation(() => {});

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(createNotificationErrorSpy).toHaveBeenCalledWith({
            message: 'sw-order.create.messageSwitchCustomerError: Translated maintenance mode error',
        });

        updateCustomerContextSpy.mockRestore();
        createNotificationErrorSpy.mockRestore();
    });

    it('should check user initially if user exists', async () => {
        setCustomerData(users);

        Contena.Store.get('swOrder').setCustomer({
            ...users[0],
        });

        const wrapper = await createWrapper();
        const spyOnSearchList = jest.spyOn(wrapper.vm, 'onSearch');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        const firstRowRadioField = firstRow.find('.sw-field__radio-input input');

        await flushPromises();

        expect(wrapper.find('.sw-card-filter').element.value).toBe('sw1');
        expect(spyOnSearchList).toHaveBeenCalledWith('sw1');

        expect(firstRowRadioField.element.checked).toBeTruthy();
    });

    it('should set the user language id when user has a language id', async () => {
        users[0].channel.languageId = '1234';
        setCustomerData(users);

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(Contena.Store.get('context').api.languageId).toBe('1234');
    });

    it('should reset language to default if system language exists in user sales channel languages', async () => {
        users[0].channel.languages = [
            {
                id: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            },
        ];
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const resetLanguageToDefaultSpy = jest.spyOn(Contena.Store.get('context'), 'resetLanguageToDefault');

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.userRepository.get = jest.fn(() => Promise.resolve(users[0]));

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(resetLanguageToDefaultSpy).toHaveBeenCalled();
    });

    it('should set user is null when close modal', async () => {
        users[0].boundSalesChannelId = null;
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-user-grid__sales-channel-selection-modal').exists()).toBeTruthy();

        await wrapper.findByText('button', 'global.default.cancel').trigger('click');

        expect(wrapper.vm.user).toBeNull();
        expect(wrapper.find('.sw-order-user-grid__sales-channel-selection-modal').exists()).toBeFalsy();
    });

    it('should call handleSelectCustomer after sales channel selected', async () => {
        users[0].boundSalesChannelId = null;
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        let select = wrapper.find('.sw-order-user-grid__sales-channel-selection');
        await select.trigger('click');

        await flushPromises();

        select = wrapper.find('.sw-entity-single-select__selection');
        await select.trigger('click');

        await flushPromises();

        const entityItem = wrapper.findAll('.sw-select-result');
        await entityItem.at(0).trigger('click');
        await flushPromises();

        const buttonSelect = wrapper.findByText('button', 'sw-order.initialModal.userGrid.buttonSelectSalesChannel');
        expect(buttonSelect.attributes('disabled')).toBeUndefined();

        await buttonSelect.trigger('click');

        expect(handleSelectCustomerSpy).toHaveBeenCalled();

        // First call on user select, second call after sales channel select
        expect(Contena.Store.get('context').api.languageId).toBe('8888');
    });

    it('should show sales channel select modal when user sales channel is not in the allowed list and has no bound sales channel', async () => {
        users[0].boundSalesChannelId = null;

        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-user-grid__sales-channel-selection-modal').exists()).toBeTruthy();
    });

    it('should handle select user when user sales channel is in the allowed list and has a bound sales channel', async () => {
        users[0].channelId = '1234';
        users[0].boundSalesChannelId = '1234';
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();
        await wrapper.setData({
            userDraft: null,
        });

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(handleSelectCustomerSpy).toHaveBeenCalled();
    });

    it('should show user changes modal when switching to a different user whose sales channel is different from the current one', async () => {
        users[0].boundSalesChannelId = '1234';
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();

        await wrapper.setData({
            userDraft: {
                boundSalesChannelId: null,
                channelId: '123456',
            },
        });

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        await flushPromises();

        expect(wrapper.find('.sw-order-user-grid__customer-changes-modal').exists()).toBeTruthy();

        const cancelButton = wrapper.findByText('button', 'global.default.cancel');
        expect(cancelButton.attributes('disabled')).toBeUndefined();
        await cancelButton.trigger('click');

        expect(wrapper.vm.user).toEqual(wrapper.vm.userDraft);
    });

    it('should handle select user when select user changes', async () => {
        users[0].boundSalesChannelId = '1234';
        setCustomerData(users);

        Contena.Store.get('swOrder').setCartToken('HE6KD7HOCC3TCS0AX903KCA6JHXCTXU2');

        const wrapper = await createWrapper();

        await wrapper.setData({
            userDraft: {
                boundSalesChannelId: null,
                channelId: '123456',
            },
        });

        const handleSelectCustomerSpy = jest.spyOn(wrapper.vm, 'handleSelectCustomer');

        await flushPromises();

        const firstRow = wrapper.find('.sw-data-grid__body .sw-data-grid__row--0');
        await firstRow.find('.sw-field__radio-input input').setChecked(true);

        expect(wrapper.find('.sw-order-user-grid__customer-changes-modal').exists()).toBeTruthy();

        const changeCustomerButton = wrapper.findByText('button', 'sw-order.initialModal.userGrid.buttonChangeCustomer');
        expect(changeCustomerButton.attributes('disabled')).toBeUndefined();

        await changeCustomerButton.trigger('click');

        expect(handleSelectCustomerSpy).toHaveBeenCalled();
    });
});
