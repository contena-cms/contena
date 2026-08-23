/* eslint-disable ct-test-rules/test-file-max-lines-warning, ct-test-rules/test-file-max-lines-error */

import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Entity from 'src/core/data/entity.data';
import EntityCollection from 'src/core/data/entity-collection.data';

const defaultUserConfig = {
    createdAt: '2021-01-21T06:52:41.857+00:00',
    id: '021150d043ee49e18642daef58e92c96',
    key: 'grid.setting.ct-customer-list',
    updatedAt: '2021-01-21T06:54:00.252+00:00',
    userId: 'd9a43905b72e43b7b669c6b005a3cf15',
    value: {
        columns: [
            {
                dataIndex: 'name',
                label: 'Name',
                property: 'name',
                visible: false,
            },
            {
                dataIndex: 'company',
                label: 'Company',
                property: 'company',
                visible: false,
            },
        ],
        compact: true,
        previews: false,
    },
};

const defaultProps = {
    identifier: 'ct-customer-list',
    columns: [
        { property: 'name', label: 'Name' },
        { property: 'company', label: 'Company' },
    ],
    dataSource: [
        { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
        { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
        { id: 'uuid3', company: 'Skidoo', name: 'Arturo Staker' },
        { id: 'uuid4', company: 'Meetz', name: 'Dalston Top' },
        { id: 'uuid5', company: 'Photojam', name: 'Neddy Jensen' },
    ],
};

describe('components/data-grid/ct-data-grid', () => {
    let stubs;

    async function createWrapper(props, userConfig, overrideProps) {
        if (!overrideProps) {
            props = { ...defaultProps, ...props };
        }

        stubs = {
            'ct-data-grid-settings': await wrapTestComponent('ct-data-grid-settings', { sync: true }),
            'ct-context-button': await wrapTestComponent('ct-context-button', {
                sync: true,
            }),
            'ct-context-menu': await wrapTestComponent('ct-context-menu', {
                sync: true,
            }),
            'ct-context-menu-item': await wrapTestComponent('ct-context-menu-item', { sync: true }),
            'ct-base-field': await wrapTestComponent('ct-base-field', {
                sync: true,
            }),
            'ct-field-error': true,
            'ct-context-menu-divider': true,
            'ct-button-group': true,
            'ct-data-grid-column-boolean': true,
            'ct-data-grid-inline-edit': true,
            'router-link': true,
            'ct-data-grid-skeleton': true,
            'ct-inheritance-switch': true,
            'ct-ai-copilot-badge': true,
            'ct-help-text': true,
            'ct-loader': true,
            'mt-floating-ui': {
                template: '<div><slot /></div>',
            },
            'mt-switch': true,
            'ct-provide': true,
        };

        return mount(await wrapTestComponent('ct-data-grid', { sync: true }), {
            global: {
                directives: {
                    popover: {},
                    tooltip: {
                        beforeMount(el, binding) {
                            el.setAttribute('data-tooltip-message', binding.value);
                        },
                    },
                },
                stubs,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    userConfig ?? defaultUserConfig,
                                ]);
                            },
                            save: () => {
                                return Promise.resolve();
                            },
                            get: () => Promise.resolve({}),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
            props: props ?? defaultProps,
        });
    }

    beforeAll(async () => {
        stubs = {
            'ct-data-grid-settings': await wrapTestComponent('ct-data-grid-settings', { sync: true }),
            'ct-context-button': await wrapTestComponent('ct-context-button', {
                sync: true,
            }),
            'ct-context-menu': await wrapTestComponent('ct-context-menu', {
                sync: true,
            }),
            'ct-context-menu-item': await wrapTestComponent('ct-context-menu-item', { sync: true }),
            'ct-base-field': await wrapTestComponent('ct-base-field', {
                sync: true,
            }),
            'ct-field-error': true,
            'ct-context-menu-divider': true,
            'ct-button-group': true,
            'ct-data-grid-column-boolean': true,
            'ct-data-grid-inline-edit': true,
            'router-link': true,
            'ct-data-grid-skeleton': true,
            'mt-checkbox': true,
            'ct-inheritance-switch': true,
            'ct-ai-copilot-badge': true,
            'ct-help-text': true,
            'ct-loader': true,
            'mt-floating-ui': {
                template: '<div><slot /></div>',
            },
            'mt-switch': true,
        };
    });

    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('should be in compact mode by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.ct-data-grid').classes()).toContain('is--compact');
    });

    it('should render grid header with correct columns', async () => {
        const wrapper = await createWrapper();

        const nameColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--0 .ct-data-grid__cell-content');
        const companyColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--1 .ct-data-grid__cell-content');
        const selectionColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--selection');
        const actionColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--actions');

        expect(selectionColumn.exists()).toBeTruthy();
        expect(actionColumn.exists()).toBeTruthy();

        expect(nameColumn.text()).toBe('Name');
        expect(companyColumn.text()).toBe('Company');
    });

    it('should derive horizontal scrolling from the rendered grid elements', async () => {
        const wrapper = await createWrapper();
        const grid = wrapper.get('.ct-data-grid');
        const gridWrapper = wrapper.get('.ct-data-grid__wrapper').element;

        Object.defineProperties(gridWrapper, {
            clientWidth: { configurable: true, value: 100 },
            scrollWidth: { configurable: true, value: 200 },
        });

        wrapper.vm.trackScrollX();

        expect(grid.classes()).toContain('is--scroll-x');

        Object.defineProperty(gridWrapper, 'scrollWidth', { configurable: true, value: 100 });
        wrapper.vm.trackScrollX();

        expect(grid.classes()).not.toContain('is--scroll-x');
    });

    it('should fix the rendered table and column widths when resize mode is enabled', async () => {
        const wrapper = await createWrapper();
        const table = wrapper.get('.ct-data-grid__table');
        const headerColumns = wrapper.findAll('.ct-data-grid__cell--header.ct-data-grid__cell--property');

        headerColumns.forEach((headerColumn) => {
            Object.defineProperty(headerColumn.element, 'offsetWidth', { configurable: true, value: 120 });
        });

        wrapper.vm.enableResizeMode();

        expect(table.element.style.tableLayout).toBe('fixed');
        expect(wrapper.vm.hasColumnsResizeState).toBe(true);
        headerColumns.forEach((headerColumn) => {
            expect(headerColumn.element.style.width).toBe('120px');
            expect(headerColumn.element.style.minWidth).toBe('120px');
        });
    });

    it('should hide selection column, action column and header based on prop', async () => {
        const wrapper = await createWrapper({
            showSelection: false,
            showActions: false,
            showHeader: false,
        });

        const header = wrapper.find('.ct-data-grid__header');
        const selectionColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--selection');
        const actionColumn = wrapper.find('.ct-data-grid__header .ct-data-grid__cell--actions');

        expect(header.exists()).toBeFalsy();
        expect(selectionColumn.exists()).toBeFalsy();
        expect(actionColumn.exists()).toBeFalsy();
    });

    it('should render a row for each item in dataSource prop', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        expect(rows).toHaveLength(5);
    });

    it('should change appearance class based on prop', async () => {
        const wrapper = await createWrapper({
            plainAppearance: true,
        });

        expect(wrapper.get('.ct-data-grid').classes()).toContain('ct-data-grid--plain-appearance');
    });

    it('should load and apply user configuration', async () => {
        const wrapper = await createWrapper({
            showSettings: true,
        });

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['ct-context-menu']).exists()).toBe(false);

        await flushPromises();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.ct-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['ct-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.ct-data-grid__settings-column-item')).toHaveLength(2);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(defaultUserConfig.value.columns[0].visible);
        expect(wrapper.vm.currentColumns[1].visible).toBe(defaultUserConfig.value.columns[1].visible);

        expect(wrapper.vm.compact).toBe(defaultUserConfig.value.compact);
        expect(wrapper.vm.previews).toBe(defaultUserConfig.value.previews);

        const valueChecked = !defaultUserConfig.value.columns[0].visible;

        const name = wrapper.find('.ct-data-grid__settings-item--0 input');
        await name.setChecked(valueChecked);

        expect(wrapper.vm.currentColumns[0].visible).toBe(valueChecked);
    });

    it('remove property in client', async () => {
        const wrapper = await createWrapper(
            {
                showSettings: true,
                identifier: 'ct-customer-list',
                columns: [
                    { property: 'name', label: 'Name' },
                ],
                dataSource: [
                    { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                    {
                        id: 'uuid2',
                        company: 'Twitternation',
                        name: 'Baxy Eardley',
                    },
                ],
            },
            {
                createdAt: '2021-01-21T06:52:41.857+00:00',
                id: '021150d043ee49e18642daef58e92c96',
                key: 'grid.setting.ct-customer-list',
                updatedAt: '2021-01-21T06:54:00.252+00:00',
                userId: 'd9a43905b72e43b7b669c6b005a3cf15',
                value: {
                    columns: [
                        {
                            dataIndex: 'name',
                            label: 'Name',
                            property: 'name',
                            visible: false,
                        },
                        {
                            dataIndex: 'company',
                            label: 'Company',
                            property: 'company',
                            visible: false,
                        },
                    ],
                    compact: true,
                    previews: true,
                },
            },
            true,
        );

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['ct-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.ct-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['ct-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.ct-data-grid__settings-column-item')).toHaveLength(1);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1]).toBeUndefined();

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property in client', async () => {
        const wrapper = await createWrapper(
            {
                showSettings: true,
                identifier: 'ct-customer-list',
                columns: [
                    { property: 'name', label: 'Name' },
                    { property: 'company', label: 'Company' },
                ],
                dataSource: [
                    { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                    {
                        id: 'uuid2',
                        company: 'Twitternation',
                        name: 'Baxy Eardley',
                    },
                ],
            },
            {
                createdAt: '2021-01-21T06:52:41.857+00:00',
                id: '021150d043ee49e18642daef58e92c96',
                key: 'grid.setting.ct-customer-list',
                updatedAt: '2021-01-21T06:54:00.252+00:00',
                userId: 'd9a43905b72e43b7b669c6b005a3cf15',
                value: {
                    columns: [
                        {
                            dataIndex: 'name',
                            label: 'Name',
                            property: 'name',
                            visible: false,
                        },
                    ],
                    compact: true,
                    previews: true,
                },
            },
            true,
        );

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['ct-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.ct-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['ct-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.ct-data-grid__settings-column-item')).toHaveLength(2);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[1].visible).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('add property value in client', async () => {
        const wrapper = await createWrapper(
            {
                showSettings: true,
                identifier: 'ct-customer-list',
                columns: [
                    { property: 'name', label: 'Name', mockProperty: true },
                ],
                dataSource: [
                    { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                ],
            },
            {
                createdAt: '2021-01-21T06:52:41.857+00:00',
                id: '021150d043ee49e18642daef58e92c96',
                key: 'grid.setting.ct-customer-list',
                updatedAt: '2021-01-21T06:54:00.252+00:00',
                userId: 'd9a43905b72e43b7b669c6b005a3cf15',
                value: {
                    columns: [
                        {
                            dataIndex: 'name',
                            label: 'Name',
                            property: 'name',
                            visible: false,
                        },
                        {
                            dataIndex: 'company',
                            label: 'Company',
                            property: 'company',
                            visible: false,
                        },
                    ],
                    compact: true,
                    previews: true,
                },
            },
            true,
        );

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['ct-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.ct-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['ct-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.ct-data-grid__settings-column-item')).toHaveLength(1);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBe(true);

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    it('remove property value in client', async () => {
        const wrapper = await createWrapper(
            {
                showSettings: true,
                identifier: 'ct-customer-list',
                columns: [
                    { property: 'name', label: 'Name' },
                ],
                dataSource: [
                    { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
                    {
                        id: 'uuid2',
                        company: 'Twitternation',
                        name: 'Baxy Eardley',
                    },
                ],
            },
            {
                createdAt: '2021-01-21T06:52:41.857+00:00',
                id: '021150d043ee49e18642daef58e92c96',
                key: 'grid.setting.ct-customer-list',
                updatedAt: '2021-01-21T06:54:00.252+00:00',
                userId: 'd9a43905b72e43b7b669c6b005a3cf15',
                value: {
                    columns: [
                        {
                            dataIndex: 'name',
                            label: 'Name',
                            property: 'name',
                            visible: false,
                            mockProperty: true,
                        },
                    ],
                    compact: true,
                    previews: true,
                },
            },
            true,
        );

        expect(wrapper.vm.showSettings).toBe(true);
        expect(wrapper.findComponent(stubs['ct-context-menu']).exists()).toBe(false);

        await wrapper.vm.$nextTick();

        // find and click button setting
        const contextButtonSetting = wrapper.find('.ct-data-grid-settings__trigger');
        await contextButtonSetting.trigger('click');

        await flushPromises();

        // show popover
        const popover = wrapper.findComponent(stubs['ct-context-menu']);
        expect(popover.exists()).toBe(true);
        expect(popover.findAll('.ct-data-grid__settings-column-item')).toHaveLength(1);

        // check default columns
        expect(wrapper.vm.currentColumns[0].visible).toBe(false);
        expect(wrapper.vm.currentColumns[0].mockProperty).toBeUndefined();

        expect(wrapper.vm.compact).toBe(true);
        expect(wrapper.vm.previews).toBe(true);
    });

    const cases = {
        'simple field': { accessor: 'id', expected: '123' },
        'translated field': { accessor: 'name', expected: 'translated' },
        'translated field with accessor': {
            accessor: 'translated.name',
            expected: 'translated',
        },
        'nested object with simple field': {
            accessor: 'manufacturer.description',
            expected: 'manufacturer-description',
        },
        'nested object with translated field': {
            accessor: 'manufacturer.name',
            expected: 'manufacturer-translated',
        },
        'nested object with translated field with accessor': {
            accessor: 'manufacturer.translated.name',
            expected: 'manufacturer-translated',
        },
        'unknown field': { accessor: 'unknown', expected: undefined },
        'nested unknown field': {
            accessor: 'manufacturer.unknown',
            expected: undefined,
        },
        'unknown nested object': {
            accessor: 'unknown.unknown',
            expected: undefined,
            errorMsg: '[[ct-data-grid] Can not resolve accessor: unknown.unknown]',
        },

        'test last function': {
            accessor: 'transactions.last().name',
            expected: 'last',
        },
        'test first function': {
            accessor: 'transactions.first().name',
            expected: 'first',
        },
        'test array access on collection': {
            accessor: 'transactions[1].name',
            expected: 'second',
        },

        'test array element 1': { accessor: 'arrayField[0]', expected: 1 },
        'test array element 2': { accessor: 'arrayField[1]', expected: 2 },
        'test array element 3': { accessor: 'arrayField[2]', expected: 3 },

        'test null object': {
            accessor: 'payload.customerId',
            expected: null,
            errorMsg: '[[ct-data-grid] Can not resolve accessor: payload.customerId]',
        },
        'test nested null object': {
            accessor: 'customer.type.name',
            expected: null,
            errorMsg: '[[ct-data-grid] Can not resolve accessor: customer.type.name]',
        },
    };

    // This test cases previously tested for console.warn calls. This was removed because vue compat emits too many warnings
    Object.entries(cases).forEach(
        ([
            key,
            testCase,
        ]) => {
            it(`should render columns with ${key}`, async () => {
                jest.spyOn(Contena.Utils.debug, 'warn').mockImplementation(() => {});

                const wrapper = await createWrapper();
                const grid = wrapper.vm;

                const data = {
                    name: 'original',
                    translated: {
                        name: 'translated',
                    },
                    manufacturer: new Entity('test', 'product_manufacturer', {
                        description: 'manufacturer-description',
                        name: 'manufacturer',
                        translated: { name: 'manufacturer-translated' },
                    }),
                    plainObject: {
                        name: 'object',
                    },
                    transactions: new EntityCollection(
                        '',
                        'order_transaction',
                        {},
                        {},
                        [
                            { name: 'first' },
                            { name: 'second' },
                            { name: 'last' },
                        ],
                        1,
                        null,
                    ),
                    arrayField: [
                        1,
                        2,
                        3,
                    ],
                    payload: null,
                    customer: { type: null },
                };

                const entity = new Entity('123', 'test', data);

                const column = { property: testCase.accessor };
                const result = grid.renderColumn(entity, column);

                expect(result).toBe(testCase.expected);
            });

            it(`should render different columns dynamically with ${key}`, async () => {
                const wrapper = await createWrapper();
                const grid = wrapper.vm;

                const data = {
                    name: 'original',
                    translated: {
                        name: 'translated',
                    },
                    manufacturer: new Entity('test', 'product_manufacturer', {
                        description: 'manufacturer-description',
                        name: 'manufacturer',
                        translated: { name: 'manufacturer-translated' },
                    }),
                    plainObject: {
                        name: 'object',
                    },
                    transactions: new EntityCollection(
                        '',
                        'order_transaction',
                        {},
                        {},
                        [
                            { name: 'first' },
                            { name: 'second' },
                            { name: 'last' },
                        ],
                        1,
                        null,
                    ),
                    arrayField: [
                        1,
                        2,
                        3,
                    ],
                    payload: null,
                    customer: { type: null },
                };

                const entity = new Entity('123', 'test', data);

                const column = { property: testCase.accessor };

                const result = grid.renderColumn(entity, column);

                expect(result).toBe(testCase.expected);
            });
        },
    );

    it('should pre select grid using preSelection prop', async () => {
        const preSelection = {
            uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
        };

        const wrapper = await createWrapper({
            identifier: 'ct-customer-list-identifier',
            preSelection,
        });

        expect(wrapper.vm.selection).toEqual(preSelection);

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        const checkbox = rows.at(0).find('.mt-field--checkbox__container input');

        expect(checkbox.element.checked).toBe(true);
    });

    it('should checked a item in grid if the grid state include that item', async () => {
        const wrapper = await createWrapper({
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: {
                    id: 'uuid1',
                    company: 'Wordify',
                    name: 'Portia Jobson',
                },
            },
        });

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        const checkbox = rows.at(0).find('.mt-field--checkbox__container input');

        expect(checkbox.element.checked).toBe(true);
    });

    it('should add a selection to grid state when selected an item', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        const checkbox = rows.at(0).find('.mt-field--checkbox__container input');

        await checkbox.setChecked(true);
        await wrapper.vm.$nextTick();

        const firstRow = defaultProps.dataSource[0];

        expect(wrapper.vm.selection).toEqual({ [firstRow.id]: firstRow });
    });

    it('should remove a selection from selection when deselected an item', async () => {
        const wrapper = await createWrapper({
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: {
                    id: 'uuid1',
                    company: 'Wordify',
                    name: 'Portia Jobson',
                },
            },
        });

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        const checkbox = rows.at(0).find('.mt-field--checkbox__container input');

        expect(checkbox.element.checked).toBe(true);

        await checkbox.setChecked(false);

        expect(wrapper.vm.selection).toEqual({});
    });

    it('should add all records to grid selection when clicking select all', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            identifier: 'ct-customer-list',
        });

        const header = wrapper.find('.ct-data-grid__header');
        const selectionAll = header.find(
            '.ct-data-grid__header .mt-field--checkbox__container.ct-data-grid__select-all input',
        );

        expect(selectionAll.element.checked).toBe(false);
        await selectionAll.setChecked(true);

        const expectedState = {};

        defaultProps.dataSource.forEach((item) => {
            expectedState[item.id] = item;
        });

        expect(wrapper.vm.selection).toEqual(expectedState);
    });

    it('should remove all records to grid state when deselected all items', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            identifier: 'ct-customer-list',
        });

        const curentGridState = {};

        defaultProps.dataSource.forEach((item) => {
            curentGridState[item.id] = item;
        });

        Object.assign(wrapper.vm, {
            selection: curentGridState,
        });
        await wrapper.vm.$nextTick();

        const header = wrapper.find('.ct-data-grid__header');
        const selectionAll = header.find(
            '.ct-data-grid__header .mt-field--checkbox__container.ct-data-grid__select-all input',
        );

        await selectionAll.setChecked(false);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selection).toEqual({});
    });

    it('should selectionCount equals to grid state count', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            identifier: 'ct-customer-list',
        });

        expect(wrapper.vm.selectionCount).toBe(0);

        const curentGridState = {};

        defaultProps.dataSource.forEach((item) => {
            curentGridState[item.id] = item;
        });

        Object.assign(wrapper.vm, {
            selection: curentGridState,
        });
        await wrapper.vm.$nextTick();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectionCount).toBe(5);
    });

    it('should persist selected items when dataSource change', async () => {
        const wrapper = await createWrapper();

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');
        expect(rows).toHaveLength(5);

        const checkbox = rows.at(0).find('.mt-field--checkbox__container input');

        await checkbox.setChecked(true);

        await wrapper.setProps({
            dataSource: [
                { id: 'uuid6', company: 'Woops', name: 'Portia Jobson' },
                { id: 'uuid7', company: 'Laprta', name: 'Baxy Eardley' },
                { id: 'uuid8', company: 'Manen', name: 'Arturo Staker' },
                { id: 'uuid9', company: 'Ginpo', name: 'Dalston Top' },
            ],
        });

        await wrapper.vm.$nextTick();

        const newRows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');
        expect(newRows).toHaveLength(4);

        const newCheckbox = newRows.at(0).find('.mt-field--checkbox__container input');

        await newCheckbox.setChecked(true);

        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });

        const previousRows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');
        expect(previousRows).toHaveLength(1);

        const previousCheckbox = newRows.at(0).find('.mt-field--checkbox__container input');
        expect(previousCheckbox.element.checked).toBe(true);
    });

    it('should not show deselect all action', async () => {
        const wrapper = await createWrapper({
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
            },
        });
        const bulkActions = wrapper.find('.ct-data-grid__bulk');
        const deselectAll = bulkActions.find('.bulk-deselect-all');

        expect(deselectAll.exists()).toBe(false);
    });

    it('should show deselect all action', async () => {
        const wrapper = await createWrapper({
            identifier: 'ct-customer-list',
            preSelection: {
                uuid10: { id: 'uuid10', company: 'Quartz', name: 'Tinto' },
            },
        });

        const bulkActions = wrapper.find('.ct-data-grid__bulk');
        const deselectAll = bulkActions.find('.bulk-deselect-all');

        expect(deselectAll.exists()).toBe(true);
    });

    it('should show maximum selection exceed', async () => {
        const wrapper = await createWrapper({
            maximumSelectItems: 3,
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.reachMaximumSelectionExceed).toBe(false);

        Object.assign(wrapper.vm, {
            selection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
                uuid2: { id: 'uuid2', company: 'Quartz2', name: 'Tinto' },
                uuid3: { id: 'uuid3', company: 'Quartz3', name: 'Tinto' },
            },
        });
        await wrapper.vm.$nextTick();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.reachMaximumSelectionExceed).toBe(true);

        // The maximum-selection notice is exposed as a tooltip on the disabled select-all checkbox.
        const selectAll = wrapper.find('.ct-data-grid__header .ct-data-grid__select-all');

        expect(selectAll.attributes('data-tooltip-message')).toBeDefined();
    });

    it('should disable the select-all header checkbox and keep it unchecked when the maximum selection is reached', async () => {
        const wrapper = await createWrapper({
            maximumSelectItems: 1,
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.reachMaximumSelectionExceed).toBe(true);
        expect(wrapper.vm.isSelectAllDisabled).toBe(true);
        // A single visible selection must not make the header look like "all items selected".
        expect(wrapper.vm.allSelectedChecked).toBe(false);

        const selectAll = wrapper.find(
            '.ct-data-grid__header .mt-field--checkbox__container.ct-data-grid__select-all input',
        );

        expect(selectAll.attributes().disabled).toBe('');
        expect(selectAll.element.checked).toBe(false);
    });

    it('should not disable the select-all header checkbox when no maximum selection is set', async () => {
        const wrapper = await createWrapper({
            identifier: 'ct-customer-list',
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isSelectAllDisabled).toBe(false);
    });

    it('should disable checkboxes when maximum selection exceed', async () => {
        const wrapper = await createWrapper({
            maximumSelectItems: 3,
            preSelection: {
                uuid1: { id: 'uuid1', company: 'Quartz1', name: 'Tinto' },
                uuid2: { id: 'uuid2', company: 'Quartz2', name: 'Tinto' },
                uuid3: { id: 'uuid3', company: 'Quartz3', name: 'Tinto' },
            },
        });

        await wrapper.vm.$nextTick();

        const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        // selected items are de-selectable
        const checkedBox = rows.at(0).findComponent({ name: 'MtCheckbox' });
        expect(checkedBox.props('disabled')).toBe(false);

        // unselected items are selectable
        const uncheckedBox = rows.at(4).findComponent({ name: 'MtCheckbox' });

        expect(uncheckedBox.props('disabled')).toBe(true);

        // unselected rows blocked by the maximum expose the reason as a tooltip on hover
        expect(rows.at(4).find('.ct-data-grid__cell--selection [data-tooltip-message]').exists()).toBe(true);

        // Change data source, select all checkbox and all items checkboxes will be disabled
        await wrapper.setProps({
            dataSource: [
                { id: 'uuid4', company: 'Quartz4', name: 'Tinto' },
                { id: 'uuid5', company: 'Quartz5', name: 'Tinto' },
                { id: 'uuid6', company: 'Quartz6', name: 'Tinto' },
            ],
        });

        await wrapper.vm.$nextTick();

        const newRows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

        newRows.forEach((row) => {
            const checkbox = row.findComponent({ name: 'MtCheckbox' });
            expect(checkbox.props('disabled')).toBe(true);
        });

        const header = wrapper.find('.ct-data-grid__header');
        const selectionAll = header.find(
            '.ct-data-grid__header .mt-field--checkbox__container.ct-data-grid__select-all input',
        );

        expect(selectionAll.attributes().disabled).toBe('');
    });

    it('should render icon column header', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    iconLabel: 'regular-file-text',
                },
                { property: 'company', label: 'Company' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });
        expect(wrapper.find('.ct-data-grid__cell--icon-label').exists()).toBe(true);
        expect(wrapper.find('.ct-data-grid__cell--icon-label .mt-icon').classes()).toContain('icon--regular-file-text');
        expect(wrapper.find('.ct-data-grid__cell--icon-label .mt-icon').attributes()).not.toHaveProperty(
            'data-tooltip-message',
        );
    });

    it('should render icon column header with tooltip', async () => {
        const wrapper = await createWrapper({
            columns: [
                {
                    property: 'name',
                    label: 'Name',
                    iconLabel: 'regular-file-text',
                    iconTooltip: 'tooltip message',
                },
                { property: 'company', label: 'Company' },
            ],
            dataSource: [
                { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
            ],
        });

        expect(wrapper.find('.ct-data-grid__cell--icon-label').exists()).toBe(true);
        expect(wrapper.find('.ct-data-grid__cell--icon-label .mt-icon').classes()).toContain('icon--regular-file-text');
        expect(wrapper.find('.ct-data-grid__cell--icon-label .mt-icon').attributes('data-tooltip-message')).toBe(
            'tooltip message',
        );
    });

    it('should render a row for each item in isRecordDisabled prop', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isRecordDisabled: (record) => record.id === 'uuid1',
        });

        const row = wrapper.find('.ct-data-grid__body .ct-data-grid__row--0');

        expect(row.classes()).toContain('is--disabled');
    });

    it('should sets default context button menu width', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.props().contextButtonMenuWidth).toBe(220);
    });

    describe('rows-clickable feature', () => {
        it('should not have is--clickable class when rowsClickable is false', async () => {
            const wrapper = await createWrapper({
                rowsClickable: false,
            });

            const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

            rows.forEach((row) => {
                expect(row.classes()).not.toContain('is--clickable');
            });
        });

        it('should have is--clickable class when rowsClickable is true', async () => {
            const wrapper = await createWrapper({
                rowsClickable: true,
            });

            const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');

            rows.forEach((row) => {
                expect(row.classes()).toContain('is--clickable');
            });
        });

        it('should emit row-click event when rowsClickable is true', async () => {
            const wrapper = await createWrapper({
                rowsClickable: true,
            });

            await wrapper.find('.ct-data-grid__body .ct-data-grid__row').trigger('click');

            expect(wrapper.emitted('row-click')).toBeDefined();
            expect(wrapper.emitted('row-click')).toHaveLength(1);
            expect(wrapper.emitted('row-click')[0][0]).toEqual(defaultProps.dataSource[0]);
        });

        it('should select item when clicking row with rowsClickable and showSelection enabled', async () => {
            const wrapper = await createWrapper({
                rowsClickable: true,
                showSelection: true,
            });

            expect(wrapper.vm.selection).toEqual({});

            await wrapper.find('.ct-data-grid__body .ct-data-grid__row').trigger('click');

            expect(wrapper.vm.selection).toHaveProperty('uuid1');
            expect(wrapper.vm.selection.uuid1).toEqual(defaultProps.dataSource[0]);
        });

        it('should deselect item when clicking an already selected row', async () => {
            const wrapper = await createWrapper({
                rowsClickable: true,
                showSelection: true,
                preSelection: {
                    uuid1: defaultProps.dataSource[0],
                },
            });

            expect(wrapper.vm.selection).toHaveProperty('uuid1');

            await wrapper.find('.ct-data-grid__body .ct-data-grid__row').trigger('click');

            expect(wrapper.vm.selection).not.toHaveProperty('uuid1');
            expect(wrapper.vm.selection).toEqual({});
        });

        it('should not select item when showSelection is false', async () => {
            const wrapper = await createWrapper({
                rowsClickable: true,
                showSelection: false,
            });

            await wrapper.find('.ct-data-grid__body .ct-data-grid__row').trigger('click');

            expect(wrapper.vm.selection).toEqual({});
            expect(wrapper.emitted('row-click')).toBeDefined();
        });

        it('should not emit row-click event when rowsClickable is false', async () => {
            const wrapper = await createWrapper({
                rowsClickable: false,
            });

            await wrapper.find('.ct-data-grid__body .ct-data-grid__row').trigger('click');

            expect(wrapper.emitted('row-click')).toBeUndefined();
        });

        it.each([
            { name: 'selection checkbox', selector: '.ct-data-grid__cell--selection input', additionalProps: {} },
            { name: 'actions cell', selector: '.ct-data-grid__cell--actions', additionalProps: { showActions: true } },
            { name: 'context button', selector: '.ct-context-button', additionalProps: { showActions: true } },
        ])('should not emit row-click event when clicking on $name', async ({ selector, additionalProps }) => {
            const wrapper = await createWrapper({
                rowsClickable: true,
                ...additionalProps,
            });

            await wrapper.find(selector).trigger('click');

            expect(wrapper.emitted('row-click')).toBeUndefined();
        });

        it.each([
            { name: 'button', tagName: 'button' },
            { name: 'link', tagName: 'a' },
            { name: 'input', tagName: 'input' },
        ])('should not emit row-click event when clicking on a $name element', async ({ tagName }) => {
            const wrapper = await createWrapper({
                rowsClickable: true,
            });

            const firstRow = wrapper.find('.ct-data-grid__body .ct-data-grid__row');

            const element = document.createElement(tagName);
            firstRow.element.appendChild(element);

            await element.dispatchEvent(new Event('click', { bubbles: true }));
            await flushPromises();

            expect(wrapper.emitted('row-click')).toBeUndefined();
        });

        it.each([
            { name: 'first', rowIndex: 0 },
            { name: 'third', rowIndex: 2 },
            { name: 'fifth', rowIndex: 4 },
        ])('should emit row-click event with correct item when clicking $name row', async ({ rowIndex }) => {
            const wrapper = await createWrapper({
                rowsClickable: true,
            });

            const rows = wrapper.findAll('.ct-data-grid__body .ct-data-grid__row');
            await rows.at(rowIndex).trigger('click');

            expect(wrapper.emitted('row-click')).toBeDefined();
            expect(wrapper.emitted('row-click')[0][0]).toEqual(defaultProps.dataSource[rowIndex]);
        });
    });

    describe('getColumnLabel', () => {
        const messages = {
            'en-GB': { 'ct-grid': { column: { name: 'Name (EN)' } } },
            'de-DE': { 'ct-grid': { column: { name: 'Name (DE)' } } },
        };

        async function createWrapperWithI18n({ locale = 'en-GB' } = {}) {
            Contena.Context.app.fallbackLocale = 'en-GB';

            const i18n = createI18n({
                legacy: false,
                locale,
                fallbackLocale: false,
                missingWarn: false,
                fallbackWarn: false,
                messages,
            });

            return mount(await wrapTestComponent('ct-data-grid', { sync: true }), {
                global: {
                    plugins: [i18n],
                    provide: {
                        repositoryFactory: {
                            create: () => ({
                                search: () => Promise.resolve([defaultUserConfig]),
                                save: () => Promise.resolve(),
                                get: () => Promise.resolve({}),
                            }),
                        },
                        acl: { can: () => true },
                    },
                },
                props: defaultProps,
            });
        }

        it('returns the translated label when the snippet exists in the current locale', async () => {
            const wrapper = await createWrapperWithI18n({ locale: 'de-DE' });

            const result = wrapper.vm.getColumnLabel({ label: 'ct-grid.column.name' });

            expect(result).toBe('Name (DE)');
        });

        it('falls back to the fallback locale when the snippet is missing in the current locale', async () => {
            const wrapper = await createWrapperWithI18n({ locale: 'fr-FR' });

            const result = wrapper.vm.getColumnLabel({ label: 'ct-grid.column.name' });

            expect(result).toBe('Name (EN)');
        });

        it('returns the raw label when neither current nor fallback locale has the snippet', async () => {
            const wrapper = await createWrapperWithI18n({ locale: 'fr-FR' });

            const result = wrapper.vm.getColumnLabel({ label: 'Plain Text Label' });

            expect(result).toBe('Plain Text Label');
        });

        it('returns an empty string when the column has no label', async () => {
            const wrapper = await createWrapperWithI18n({ locale: 'en-GB' });

            expect(wrapper.vm.getColumnLabel({})).toBe('');
            expect(wrapper.vm.getColumnLabel({ label: '' })).toBe('');
        });

        it('does not consult the fallback locale when no fallbackLocale is configured', async () => {
            const wrapper = await createWrapperWithI18n({ locale: 'fr-FR' });

            Contena.Context.app.fallbackLocale = '';

            const result = wrapper.vm.getColumnLabel({ label: 'ct-grid.column.name' });

            expect(result).toBe('ct-grid.column.name');
        });
    });
});
