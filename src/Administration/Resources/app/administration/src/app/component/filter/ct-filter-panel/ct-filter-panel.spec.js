import 'src/app/component/filter/ct-filter-panel';
import 'src/app/component/filter/ct-boolean-filter';
import 'src/app/component/filter/ct-existence-filter';
import 'src/app/component/form/field-base/ct-block-field';
import 'src/app/component/form/field-base/ct-base-field';
import 'src/app/component/filter/ct-base-filter';
import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const filters = [
    {
        name: 'filter1',
        type: 'boolean-filter',
        label: 'filter1',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter2',
        type: 'existence-filter',
        label: 'filter2',
        schema: {
            localField: 'id',
        },
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter3',
        type: 'multi-select-filter',
        label: 'filter3',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter4',
        type: 'string-filter',
        label: 'filter4',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter5',
        type: 'number-filter',
        label: 'filter5',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter6',
        type: 'price-filter',
        label: 'filter6',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter7',
        type: 'date-filter',
        label: 'filter7',
        value: null,
        filterCriteria: null,
    },
];

let savedFilterData = {};
let getStoredFiltersMock = () => Promise.resolve(savedFilterData);
let saveFiltersMock = (storeKey, storedFilters) => Promise.resolve(storedFilters);

async function createWrapper() {
    return mount(await wrapTestComponent('ct-filter-panel', { sync: true }), {
        props: {
            title: 'Filter',
            entity: 'product',
            filters,
            storeKey: 'config',
            defaults: [
                'filter1',
                'filter2',
                'filter3',
                'filter4',
                'filter5',
                'filter6',
                'filter7',
            ],
        },
        global: {
            stubs: {
                'ct-boolean-filter': await wrapTestComponent('ct-boolean-filter', { sync: true }),
                'ct-block-field': await wrapTestComponent('ct-block-field', { sync: true }),
                'ct-base-field': await wrapTestComponent('ct-base-field', { sync: true }),
                'ct-base-filter': await wrapTestComponent('ct-base-filter', { sync: true }),
                'ct-field-error': {
                    template: '<div></div>',
                },
                'ct-existence-filter': await wrapTestComponent('ct-existence-filter', { sync: true }),
                'ct-multi-select-filter': true,
                'ct-string-filter': true,
                'ct-number-filter': true,
                'ct-date-filter': true,
                'ct-help-text': true,
                'ct-select-result': true,
                'ct-highlight-text': true,
                'ct-ai-copilot-badge': true,
                'ct-inheritance-switch': true,
                'ct-loader': true,
            },
            provide: {
                [routeLocationKey]: {
                    name: 'ct.test.index',
                    params: {},
                    query: {},
                },
                repositoryFactory: {
                    create: () => ({
                        create: () =>
                            Promise.resolve({
                                key: 'config',
                                userId: '1',
                            }),
                        search: () => Promise.resolve(savedFilterData),
                        save: () => Promise.resolve([]),
                    }),
                },
            },
        },
    });
}

Contena.Service().register('filterService', () => {
    return {
        getStoredFilters: (...args) => getStoredFiltersMock(...args),
        saveFilters: (...args) => saveFiltersMock(...args),
    };
});

describe('components/ct-filter-panel', () => {
    beforeEach(() => {
        savedFilterData = {};
        getStoredFiltersMock = () => Promise.resolve(savedFilterData);
        saveFiltersMock = (storeKey, storedFilters) => Promise.resolve(storedFilters);
    });

    it('should render filter components correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.ct-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('ct-multi-select-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('ct-string-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('ct-number-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('ct-date-filter-stub').exists()).toBeTruthy();
    });

    it('should update filter with updated values', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const booleanFilter = wrapper.find('.ct-boolean-filter');
        await selectMtSelectOptionByText(booleanFilter, 'ct-boolean-filter.active');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeFilters.filter1).toBeTruthy();
    });

    it('should remove filter when reset button is clicked', async () => {
        savedFilterData = {
            filter1: {},
        };

        const wrapper = await createWrapper();
        await flushPromises();

        const booleanFilter = wrapper.find('.ct-boolean-filter');
        await selectMtSelectOptionByText(booleanFilter, 'ct-boolean-filter.active');

        await wrapper.find('.ct-base-filter__reset').trigger('click');

        expect(wrapper.vm.activeFilters.filter1).toBeFalsy();
    });

    it('should display only default filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            defaults: [
                'filter1',
                'filter2',
            ],
        });

        expect(wrapper.find('.ct-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.ct-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('ct-multi-select-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('ct-string-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('ct-number-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('ct-date-filter-stub').exists()).toBeFalsy();
    });

    it('should reset all filters when `Reset All` button is clicked', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const booleanFilter = wrapper.find('.ct-boolean-filter');
        await selectMtSelectOptionByText(booleanFilter, 'ct-boolean-filter.inactive');

        expect(Object.keys(wrapper.vm.activeFilters)).not.toHaveLength(0);

        await wrapper.vm.resetAll();

        expect(Object.keys(wrapper.vm.activeFilters)).toHaveLength(0);
    });

    it('should change active filters when filter has default value', async () => {
        savedFilterData = {
            filter3: {
                value: [
                    {
                        id: '5e59f3ea47a342dd8ff1a0af2cda475',
                    },
                ],
                criteria: [
                    {
                        type: 'equalsAny',
                        field: 'tag.id',
                        value: '5e59f3ea47a342dd8ff1a0af2cda475',
                    },
                ],
            },
        };

        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.activeFilters)).toHaveLength(1);
    });

    it('should keep filter changes while stored filters are still loading', async () => {
        let resolveStoredFilters;
        getStoredFiltersMock = () =>
            new Promise((resolve) => {
                resolveStoredFilters = resolve;
            });

        const wrapper = await createWrapper();
        const filterCriteria = [
            {
                type: 'equalsAny',
                field: 'stateMachineState.id',
                value: ['state-open'],
            },
        ];
        const filterValue = [
            {
                id: 'state-open',
                name: 'Open',
            },
        ];

        wrapper.vm.updateFilter('filter3', filterCriteria, filterValue);
        await wrapper.vm.$nextTick();

        resolveStoredFilters({});
        await flushPromises();

        expect(wrapper.vm.activeFilters.filter3).toEqual(filterCriteria);
        expect(wrapper.vm.storedFilters.filter3).toEqual({
            value: filterValue,
            criteria: filterCriteria,
        });
    });

    it('should return breadcrumb path when item has breadcrumb array', async () => {
        const wrapper = await createWrapper();

        const itemWithBreadcrumb = {
            breadcrumb: [
                'Category 1',
                'Category 2',
                'Category 3',
            ],
            name: 'Product Name',
            translated: {
                name: 'Translated Product Name',
            },
        };

        const result = wrapper.vm.getBreadcrumb(itemWithBreadcrumb);

        expect(result).toBe('Category 1 / Category 2 / Category 3');
    });

    it('should return name when item has no breadcrumb', async () => {
        const wrapper = await createWrapper();

        const itemWithoutBreadcrumb = {
            breadcrumb: [],
            name: 'Product Name',
            translated: {
                name: 'Translated Product Name',
            },
        };

        const result = wrapper.vm.getBreadcrumb(itemWithoutBreadcrumb);

        expect(result).toBe('Translated Product Name');
    });
});
