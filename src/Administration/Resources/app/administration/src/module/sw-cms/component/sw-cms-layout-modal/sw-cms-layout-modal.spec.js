/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

const defaultCategoryId = 'default-category-id';
const defaultProductId = 'default-blog-id';

const blogMocks = [
    {
        id: 'some-other-id',
        sections: [],
        categories: [],
        blogs: [],
        translated: {
            name: 'CMS Page 1',
        },
    },
    {
        id: defaultProductId,
        sections: [],
        categories: [],
        blogs: [],
        translated: {
            name: 'CMS Page 2',
        },
    },
    {
        id: defaultCategoryId,
        sections: [],
        categories: [],
        blogs: [],
        translated: {
            name: 'CMS Page 3',
        },
    },
];

const searchMock = jest.fn(() => Promise.resolve(blogMocks));

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-layout-modal', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: searchMock,
                        }),
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                    systemConfigApiService: {
                        getValues: (query) => {
                            if (query !== 'core.cms') {
                                return null;
                            }

                            return {
                                'core.cms.default_category_cms_page': defaultCategoryId,
                                'core.cms.default_product_cms_page': defaultProductId,
                            };
                        },
                        saveValues: () => null,
                    },
                    cmsPageTypeService: {
                        getType: () => {
                            return {
                                name: 'custom_entity_detail',
                                icon: 'regular-tag',
                                title: 'sw-cms.detail.label.pageType.customEntityDetail',
                                class: ['sw-cms-create-wizard__page-type-custom-entity-detail'],
                                hideInList: false,
                            };
                        },
                    },
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                },

                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),

                    'sw-simple-search-field': true,
                    'sw-loader': true,
                    'sw-container': true,
                    'sw-sorting-select': true,
                    'sw-pagination': true,
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field', { sync: true }),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-inheritance-switch': true,
                    'sw-field-error': true,
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-cms-list-item': await wrapTestComponent('sw-cms-list-item', { sync: true }),
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'router-link': true,
                    'sw-data-grid-skeleton': true,
                    'sw-help-text': true,
                    'sw-ai-copilot-badge': true,
                    'sw-provide': { template: `<slot/>`, inheritAttrs: false },
                    'sw-time-ago': true,
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-layout-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should search cms pages with criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: ['page', 'landingpage', 'blog_list'],
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(
            expect.objectContaining({
                filters: [
                    {
                        type: 'equalsAny',
                        field: 'type',
                        value: 'page|landingpage|blog_list',
                    },
                ],
            }),
        );

        expect(searchMock).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should search cms pages without criteria filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            cmsPageTypes: [],
        });
        await wrapper.vm.getList();

        expect(wrapper.vm.cmsPageCriteria).toEqual(
            expect.objectContaining({
                filters: [],
            }),
        );

        expect(searchMock).toHaveBeenCalledWith(wrapper.vm.cmsPageCriteria);
    });

    it('should display default status', async () => {
        global.activeAclRoles = ['system_config:read'];

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.defaultProductId).toBe(defaultProductId);
        expect(wrapper.vm.defaultCategoryId).toBe(defaultCategoryId);

        const listItems = wrapper.findAllComponents('.sw-cms-list-item');

        expect(listItems).toHaveLength(3);

        expect(listItems[0].props('isDefault')).toBe(false);
        expect(listItems[1].props('isDefault')).toBe(true);
        expect(listItems[2].props('isDefault')).toBe(true);
    });

    it('should return the correct page type', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.listMode = 'list';
        await flushPromises();

        const typeCell = wrapper.find('.sw-data-grid__cell--type > .sw-data-grid__cell-content');
        expect(typeCell.text()).toBe('sw-cms.detail.label.pageType.customEntityDetail');
    });

    it('should emit "modal-layout-select" and "modal-close" when selecting a layout', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('.sw-cms-layout-modal__button-select-layout').trigger('click');

        expect(wrapper.vm.selectedPageObject).toBeNull();
        expect(wrapper.vm.term).toBeNull();

        expect(wrapper.emitted('modal-layout-select')).toHaveLength(1);
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should toggle list mode correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.listMode).toBe('grid');

        wrapper.vm.toggleListMode();
        expect(wrapper.vm.listMode).toBe('list');

        wrapper.vm.toggleListMode();
        expect(wrapper.vm.listMode).toBe('grid');
    });

    it('should set the cmsPageCriteria correctly, when setting a search term', async () => {
        const wrapper = await createWrapper();
        const searchTerm = 'hello world';

        wrapper.vm.term = searchTerm;
        wrapper.vm.onSearch(searchTerm);
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria.term).toBe(searchTerm);
        expect(wrapper.vm.cmsPageCriteria.page).toBe(1);
        expect(wrapper.vm.cmsPageCriteria.limit).toBe(10);
        expect(wrapper.vm.cmsPageCriteria.filters).toEqual([]);
        expect(wrapper.vm.cmsPageCriteria.hasAssociation('previewMedia')).toBe(true);
        expect(wrapper.vm.cmsPageCriteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);

        wrapper.vm.onSearch('');
        await flushPromises();

        expect(wrapper.vm.cmsPageCriteria.term).toBeNull();
        expect(wrapper.vm.cmsPageCriteria.page).toBe(1);
        expect(wrapper.vm.cmsPageCriteria.limit).toBe(10);
        expect(wrapper.vm.cmsPageCriteria.filters).toEqual([]);
        expect(wrapper.vm.cmsPageCriteria.hasAssociation('previewMedia')).toBe(true);
        expect(wrapper.vm.cmsPageCriteria.sortings).toEqual([
            {
                field: 'createdAt',
                order: 'DESC',
                naturalSorting: false,
            },
        ]);
    });

    it.each(blogMocks)('should select the given item and load all variables correctly', async (blog) => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isLoading: false,
        });

        const blogIndex = blogMocks.indexOf(blog);
        const expected = blogMocks[blogIndex];

        const column = wrapper.findAll('.sw-cms-layout-modal__content .sw-cms-list-item').at(blogIndex);
        await column.find('.sw-cms-list-item__title').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.selectedPageObject).toEqual(expected);
        expect(wrapper.vm.gridPreSelection).toStrictEqual({
            [expected.id]: expected,
        });
    });

    it.each(blogMocks)(
        'should set and unset the selectedPageObject correctly, when selecting a column',
        async (blog) => {
            const wrapper = await createWrapper();
            await wrapper.setData({
                isLoading: false,
            });

            const blogIndex = blogMocks.indexOf(blog);
            const expected = blogMocks[blogIndex];

            const checkbox = wrapper
                .findAll('.sw-cms-layout-modal__content-item .mt-field--checkbox__container input')
                .at(blogIndex);

            await checkbox.setChecked(true);
            expect(wrapper.vm.selectedPageObject).toEqual(expected);
        },
    );
});
