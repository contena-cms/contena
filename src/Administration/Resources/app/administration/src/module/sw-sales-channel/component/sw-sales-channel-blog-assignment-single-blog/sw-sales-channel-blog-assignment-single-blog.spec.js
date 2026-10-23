/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

let blogData = [];

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        sortings: [{ field: 'name', naturalSorting: false, order: 'ASC' }],
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        },
    };
}

function setProductData(blogs) {
    blogData = [...blogs];
    blogData.total = 3;
    blogData.criteria = {
        page: 1,
        limit: 25,
    };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-sales-channel-blogs-assignment-single-blogs', { sync: true }), {
        global: {
            stubs: {
                'sw-container': true,
                'mt-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-card-section': {
                    template: '<div><slot></slot></div>',
                },
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-field-error': true,
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing', { sync: true }),
                'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                    sync: true,
                }),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-context-button': await wrapTestComponent('sw-context-button', { sync: true }),
                'sw-context-menu-item': true,
                'sw-modal': true,
                'sw-tabs': true,
                'sw-tab-items': true,
                'sw-pagination': true,
                'sw-data-grid-skeleton': true,
                'sw-data-grid-settings': true,
                'sw-text-field-deprecated': true,
                'sw-bulk-edit-modal': true,
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'router-link': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
                'sw-provide': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(blogData),
                        };
                    },
                },
                validationService: {},
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'regular-content',
                        },
                    },
                },
            },
        },
        props: {
            channel: {
                id: 1,
                name: 'Headless',
            },
            containerStyle: {},
        },
        attachTo: document.body,
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-blogs-assignment-single-blogs', () => {
    it('should display empty state when blog data is empty', async () => {
        setProductData([]);
        const wrapper = await createWrapper();

        expect(wrapper.find('.mt-empty-state').exists()).toBeTruthy();
    });

    it('should display data grid when there is blog data', async () => {
        setProductData([
            {
                name: 'Test blog 1',
                blogNumber: '1',
            },
        ]);

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-data-grid').exists()).toBeTruthy();
    });

    it('should emit selected blogs', async () => {
        setProductData([
            {
                id: 1,
                name: 'Test blog 1',
                blogNumber: '1',
            },
            {
                id: 2,
                name: 'Test blog 2',
                blogNumber: '2',
            },
            {
                id: 3,
                name: 'Test blog 3',
                blogNumber: '3',
            },
        ]);

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-data-grid__select-all.mt-field--checkbox__container input').trigger('click');
        expect(wrapper.emitted('selection-change').at(-1)).toEqual([
            [
                {
                    id: 1,
                    name: 'Test blog 1',
                    blogNumber: '1',
                },
                {
                    id: 2,
                    name: 'Test blog 2',
                    blogNumber: '2',
                },
                {
                    id: 3,
                    name: 'Test blog 3',
                    blogNumber: '3',
                },
            ],
            'singleProducts',
        ]);
    });

    it('should get blogs when searching', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onChangeSearchTerm('Standard prices');

        expect(wrapper.vm.searchTerm).toBe('Standard prices');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get blogs when changing page', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProducts = jest.fn();
        expect(wrapper.vm.blogCriteria.sortings).toEqual([]);
        wrapper.vm.blogs.criteria = mockCriteria();

        await wrapper.vm.onChangePage({ page: 2, limit: 25 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(25);
        expect(wrapper.vm.blogCriteria.sortings).toEqual([{ field: 'name', naturalSorting: false, order: 'ASC' }]);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });
});
