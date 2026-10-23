/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';

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

const blogsMock = [
    {
        id: '101',
        active: true,
        name: 'blog-101',
        blogNumber: '001',
        visibilities: [
            {
                id: '1',
                blogId: '101',
                channelId: 'frontendSalesChannelTypeId',
            },
        ],
    },
    {
        id: '102',
        active: false,
        name: 'blog-102',
        blogNumber: '002',
        visibilities: [
            {
                id: '2',
                blogId: '202',
                channelId: 'frontendSalesChannelTypeId',
            },
        ],
    },
];
const variantProductMocks = [
    {
        id: '201',
        active: true,
        name: 'blog-101.1',
        blogNumber: '001.1',
        parentId: '101',
        visibilities: [
            {
                id: '1',
                blogId: '101',
                channelId: 'frontendSalesChannelTypeId',
            },
        ],
    },
    {
        id: '202',
        active: true,
        name: 'blog-101.2',
        blogNumber: '001.2',
        parentId: '101',
        visibilities: [
            {
                id: '2',
                blogId: '202',
                channelId: 'frontendSalesChannelTypeId',
            },
        ],
    },
];
blogsMock.has = (id) => {
    return blogsMock.some((item) => {
        return item.id === id;
    });
};

const blogMock = {
    visibilities: [
        { id: '01', blogId: '101', channelId: 'apiSalesChannelTypeId' },
        {
            id: '02',
            blogId: '101',
            channelId: 'frontendSalesChannelTypeId',
        },
    ],
};

let repositoryFactoryDeleteMock;

async function createWrapper({ channel, blogs } = {}) {
    repositoryFactoryDeleteMock = jest.fn(async () => {});

    const wrapper = mount(
        await wrapTestComponent('sw-sales-channel-detail-blogs', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot><slot name="grid"></slot></div>',
                    },
                    'sw-container': {
                        template: `
                        <div class="sw-container">
                            <slot></slot>
                        </div>
                    `,
                    },
                    'sw-card-section': {
                        template: `
                        <div class="sw-card-section">
                            <slot></slot>
                        </div>
                    `,
                    },
                    'sw-entity-listing': {
                        props: [
                            'items',
                            'dataSource',
                            'allowEdit',
                            'allowDelete',
                        ],
                        template: `
                        <div class="sw-entity-listing">
                            <template v-for="item in (dataSource || items)">
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>
                    `,
                        data() {
                            return {
                                selection: {},
                            };
                        },
                        methods: {
                            resetSelection() {
                                this.selection = {};
                            },
                        },
                    },
                    'sw-empty-state': {
                        template: `
                        <div class="sw-empty-state">
                            <slot></slot>
                            <slot name="actions"></slot>
                        </div>
                    `,
                    },
                    'sw-pagination': true,
                    'sw-simple-search-field': true,
                    'sw-sales-channel-blogs-assignment-modal': true,
                    'sw-context-menu-item': true,
                    'sw-extension-component-section': true,
                    'sw-ignore-class': true,
                    'sw-checkbox-field': true,
                    'router-link': true,
                    'sw-blog-variant-info': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                create: async () => {},
                                search: async () => {
                                    if (entity === 'blog') {
                                        const entityCollection = blogs ?? [];
                                        entityCollection.criteria = mockCriteria();
                                        entityCollection.total = blogs.length;

                                        return entityCollection;
                                    }

                                    return [];
                                },
                                delete: repositoryFactoryDeleteMock,
                                syncDeleted: async () => {},
                                saveAll: async () => {},
                            };
                        },
                    },
                },
            },
            props: {
                channel: channel ?? {
                    id: 'frontendSalesChannelTypeId',
                },
            },
        },
    );

    function getCreateButton() {
        return wrapper.findByText('button', 'sw-sales-channel.detail.blogs.buttonAddProducts');
    }

    function getEntityListing() {
        return wrapper.getComponent('.sw-entity-listing');
    }

    return { wrapper, getCreateButton, getEntityListing };
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-blogs', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should get blogs successful', async () => {
        const { wrapper } = await createWrapper({
            channel: { id: 'apiSalesChannelTypeId' },
            blogs: blogsMock,
        });
        await flushPromises();

        expect(wrapper.getComponent('.sw-entity-listing').props('dataSource')).toEqual(blogsMock);
    });

    it('should delete blog successful', async () => {
        const { wrapper } = await createWrapper({
            channel: { id: 'apiSalesChannelTypeId' },
            blogs: blogsMock,
        });
        await wrapper.getComponent('.sw-entity-listing').vm.$emit('selection-change', {
            101: blogMock,
        });
        await flushPromises();

        wrapper.vm.blogVisibilityRepository.delete = jest.fn(() => Promise.resolve());
        wrapper.vm.getProducts = jest.fn(() => Promise.resolve());

        await wrapper.vm.onDeleteProduct(blogMock);

        expect(wrapper.vm.getDeleteId(blogMock)).toBe('01');
        expect(wrapper.vm.getProducts).toHaveBeenCalled();

        wrapper.vm.blogVisibilityRepository.delete.mockRestore();
        wrapper.vm.getProducts.mockRestore();
    });

    it('should delete blog failed', async () => {
        const { wrapper } = await createWrapper({
            channel: { id: 'apiSalesChannelTypeId' },
            blogs: blogsMock,
        });

        await wrapper.setData({
            searchTerm: 'Awesome Blog',
        });

        wrapper.vm.blogVisibilityRepository.delete = jest.fn(() => Promise.reject(new Error('Error')));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onDeleteProduct(blogMock);

        expect(wrapper.vm.getDeleteId(blogMock)).toBe('01');
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);

        wrapper.vm.blogVisibilityRepository.delete.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should get delete id correctly', async () => {
        const { wrapper } = await createWrapper({
            channel: { id: 'apiSalesChannelTypeId' },
        });

        const deleteId = wrapper.vm.getDeleteId(blogMock);

        expect(deleteId).toBe('01');
    });

    it('should get blogs when changing search term', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.getProducts = jest.fn();

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onChangeSearchTerm('Awesome Blog');

        expect(wrapper.vm.searchTerm).toBe('Awesome Blog');
        expect(wrapper.vm.blogCriteria.term).toBe('Awesome Blog');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });

    it('should get blogs when changing page', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
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

    it('should be able to add blogs in empty state', async () => {
        global.activeAclRoles = ['channel.editor'];
        const { wrapper, getCreateButton } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: [], searchTerm: null });

        expect(getCreateButton().attributes('disabled')).toBeUndefined();
    });

    it('should not be able to add blogs in empty state', async () => {
        const { wrapper, getCreateButton } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: [], searchTerm: null });

        expect(getCreateButton().attributes('disabled')).toBeDefined();
    });

    it('should be able to add blogs in filled state', async () => {
        global.activeAclRoles = ['channel.editor'];
        const { wrapper, getCreateButton } = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            blogs: blogsMock,
            searchTerm: 'Awesome Blog',
        });

        expect(getCreateButton().attributes('disabled')).toBeUndefined();
    });

    it('should not be able to add blogs in filled state', async () => {
        const { wrapper, getCreateButton } = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            blogs: blogsMock,
            searchTerm: 'Awesome Blog',
        });

        expect(getCreateButton().attributes('disabled')).toBeDefined();
    });

    it('should be able to delete blog', async () => {
        global.activeAclRoles = ['channel.deleter'];
        const { wrapper, getEntityListing } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: blogsMock });

        expect(getEntityListing().props('allowDelete')).toBe(true);
    });

    it('should not be able to delete blog', async () => {
        const { wrapper, getEntityListing } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: blogsMock });

        expect(getEntityListing().props('allowDelete')).toBe(false);
    });

    it('should be able to edit blog', async () => {
        global.activeAclRoles = ['blog.editor'];
        const { wrapper, getEntityListing } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: blogsMock });

        expect(getEntityListing().props('allowEdit')).toBe(true);
    });

    it('should not be able to edit blog', async () => {
        const { wrapper, getEntityListing } = await createWrapper();
        await flushPromises();

        await wrapper.setData({ blogs: blogsMock });

        expect(getEntityListing().props('allowEdit')).toBe(false);
    });

    it('should turn on add blogs modal', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        await wrapper.vm.openAddProductsModal();

        const modal = wrapper.find('sw-sales-channel-blogs-assignment-modal-stub');

        expect(wrapper.vm.showProductsModal).toBe(true);
        expect(modal.exists()).toBeTruthy();
    });

    it('should add blogs successful', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        await wrapper.setData({ blogs: blogsMock });
        await wrapper.vm.onAddProducts([{ id: '103', active: true, blogNumber: '003' }]);

        expect(wrapper.vm.saveProductVisibilities).toHaveBeenCalledWith(
            expect.arrayContaining([expect.objectContaining({ blogId: '103' })]),
        );

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should add blogs failed', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        await expect(wrapper.vm.onAddProducts([])).rejects.toEqual();

        expect(wrapper.vm.showProductsModal).toBe(false);
        expect(wrapper.vm.saveProductVisibilities).not.toHaveBeenCalled();

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should save blog visibilities successful', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.blogVisibilityRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.saveProductVisibilities([]);

        expect(wrapper.vm.blogVisibilityRepository.saveAll).not.toHaveBeenCalled();

        wrapper.vm.blogVisibilityRepository.saveAll.mockRestore();
    });

    it('should save blog visibilities failed', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();
        wrapper.vm.blogVisibilityRepository.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        const getError = async () => {
            try {
                await wrapper.vm.saveProductVisibilities([
                    {
                        visibility: 30,
                        blogId: 'blogId',
                        channelId: 'channelId',
                        channel: {},
                        _isNew: true,
                    },
                ]);

                throw new Error('Method should have thrown an error');
            } catch (error) {
                return error;
            }
        };

        expect((await getError()).message).toBe('Whoops!');

        wrapper.vm.blogVisibilityRepository.saveAll.mockRestore();
    });

    it('should not be able to delete variants which have inherit visibility', async () => {
        const { wrapper } = await createWrapper({
            blogs: [...blogsMock, ...variantProductMocks],
        });
        await flushPromises();

        expect(wrapper.vm.isProductRemovable(variantProductMocks[0])).toBe(false);
        expect(wrapper.vm.isProductRemovable(variantProductMocks[1])).toBe(true);
    });

    it('should render loading state when loading blog entities', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.getComponent('.mt-card').attributes('is-loading')).toBe('true');
        expect(wrapper.find('.sw-empty-state').exists()).toBe(false);
    });

    it('should render empty state when blogs are loaded and empty', async () => {
        const { wrapper } = await createWrapper();
        await flushPromises();

        expect(wrapper.getComponent('.mt-card').attributes('is-loading')).toBeUndefined();
        expect(wrapper.find('.mt-empty-state').exists()).toBe(true);
    });

    it('should return filters from filter registry', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
