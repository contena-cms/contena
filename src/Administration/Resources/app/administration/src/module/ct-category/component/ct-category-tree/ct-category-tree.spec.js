/* eslint-disable ct-test-rules/test-file-max-lines-warning */

/**
 * @ct-package discovery
 */
import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory, routeLocationKey, routerKey } from 'vue-router';
import '../../page/ct-category-detail/store';

function createCategory({ id, name, parentId = null, childCount = 0, afterCategoryId = null, path = null }) {
    return {
        id,
        name,
        translated: { name },
        parentId,
        childCount,
        afterCategoryId,
        path,
        active: true,
        navigationChannels: [],
        footerChannels: [],
        serviceChannels: [],
    };
}

const interactiveCategories = {
    firstRoot: createCategory({
        id: 'first-root',
        name: 'First root',
        childCount: 1,
    }),
    secondRoot: createCategory({
        id: 'second-root',
        name: 'Second root',
        childCount: 1,
        afterCategoryId: 'first-root',
    }),
    firstChild: createCategory({
        id: 'first-child',
        name: 'First child',
        parentId: 'first-root',
        path: '|first-root|',
    }),
    secondChild: createCategory({
        id: 'second-child',
        name: 'Second child',
        parentId: 'second-root',
        childCount: 1,
        path: '|second-root|',
    }),
    secondGrandChild: createCategory({
        id: 'second-grand-child',
        name: 'Second grandchild',
        parentId: 'second-child',
        path: '|second-root|second-child|',
    }),
};

async function setVmState(wrapper, state) {
    Object.assign(wrapper.vm, state);
    await wrapper.vm.$nextTick();
}

async function createWrapper(categories = null, props = {}) {
    const routes = [
        {
            path: '/',
            component: { template: '<div></div>' },
        },
        {
            name: 'ct.category.detail',
            path: '/category/detail/:id',
        },
    ];

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(await wrapTestComponent('ct-category-tree', { sync: true }), {
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'ct-loader': true,
                'ct-skeleton': true,
                'ct-tree': {
                    inheritAttrs: false,
                    props: [
                        'items',
                        'initiallyExpandedRoot',
                        'sortable',
                        'allowDeleteCategories',
                        'activeTreeItemId',
                        'translationContext',
                        'onChangeRoute',
                        'disableContextMenu',
                        'searchable',
                        'afterIdProperty',
                    ],
                    template: `
                        <div
                            class="ct-tree"
                            :data-initially-expanded-root="String(initiallyExpandedRoot)"
                            :sortable="sortable || undefined"
                            :allow-delete-categories="allowDeleteCategories || undefined"
                        >
                          <slot name="items" :treeItems="items" :checkItem="() => {}"></slot>
                        </div>
                    `,
                },
                'ct-tree-item': true,
            },
            provide: {
                [routerKey]: router,
                [routeLocationKey]: {
                    name: 'ct.category.index',
                    params: {},
                    query: {},
                },
                syncService: {},
                repositoryFactory: {
                    create: () => ({
                        search: () =>
                            Promise.resolve([
                                {
                                    id: '1a',
                                },
                            ]),
                        delete: () => Promise.resolve(),
                        get: (id) => {
                            if (!categories) {
                                return Promise.resolve();
                            }

                            const children = Object.values(categories).filter((category) => category.parentId === id);

                            return Promise.resolve({
                                ...categories[id],
                                children,
                            });
                        },
                        saveAll: () => Promise.resolve(),
                        syncDeleted: () => Promise.resolve(),
                    }),
                },
            },
        },
        props: {
            currentLanguageId: '1a2b3c',
            ...props,
        },
    });
}

async function createInteractiveWrapper({ props = {} } = {}) {
    const routes = [
        {
            path: '/',
            component: { template: '<div></div>' },
        },
        {
            name: 'ct.category.detail',
            path: '/category/detail/:id',
        },
    ];

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    return mount(await wrapTestComponent('ct-category-tree', { sync: true }), {
        attachTo: document.body,
        global: {
            mocks: {
                $router: router,
            },
            stubs: {
                'ct-loader': true,
                'ct-skeleton': true,
                'ct-tree': await wrapTestComponent('ct-tree'),
                'ct-tree-item': await wrapTestComponent('ct-tree-item'),
                'ct-tree-input-field': true,
                'ct-context-menu-item': true,
                'ct-context-button': true,
                'ct-confirm-field': true,
                'ct-vnode-renderer': await wrapTestComponent('ct-vnode-renderer', { sync: true }),
            },
            provide: {
                [routerKey]: router,
                [routeLocationKey]: {
                    name: 'ct.category.index',
                    params: {},
                    query: {},
                },
                syncService: {},
                validationService: {},
                repositoryFactory: {
                    create: () => ({
                        search: (criteria) => {
                            const parentId = criteria.filters.find((filter) => filter.field === 'parentId')?.value ?? null;

                            return Promise.resolve(
                                Object.values(interactiveCategories).filter((category) => category.parentId === parentId),
                            );
                        },
                        get: (id) => {
                            const children = Object.values(interactiveCategories).filter((category) => {
                                return category.parentId === id;
                            });

                            return Promise.resolve({
                                ...interactiveCategories[id],
                                children,
                            });
                        },
                        delete: () => Promise.resolve(),
                        saveAll: () => Promise.resolve(),
                        syncDeleted: () => Promise.resolve(),
                    }),
                },
            },
        },
        props: {
            currentLanguageId: '1a2b3c',
            ...props,
        },
    });
}

describe('src/module/ct-category/component/ct-category-tree', () => {
    beforeEach(() => {
        Contena.Store.get('ctCategoryDetail').$reset();
    });

    it('should be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const tree = wrapper.find('.ct-tree');
        expect(tree.attributes().sortable).toBeDefined();
    });

    it('should not be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        expect(wrapper.vm.sortable).toBe(false);
    });

    it('should be able to delete the items in ct-tree', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const tree = wrapper.find('.ct-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete the items in ct-tree', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const tree = wrapper.find('.ct-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should be able to create new categories in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeDefined();
    });

    it('should not be able to create new categories in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowCreate: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete categories in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete categories in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeDefined();
    });

    it('should not show the checkbox in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['display-checkbox']).toBeUndefined();
    });

    it('should show the custom tooltip text in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('ct-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in ct-tree-item', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('ct-tree-item-stub');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right category url', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).toBe('#/category/detail/1a2b');
    });

    it('should get wrong category url', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getCategoryUrl({ id: '1a2b' });
        expect(itemUrl).not.toBe('#/detail/1a2b');
    });

    it('should keep the first root category closed when loading children in another root category', async () => {
        const wrapper = await createInteractiveWrapper();
        await flushPromises();

        const getFirstRoot = () => wrapper.get('[data-item-id="first-root"]');

        expect(getFirstRoot().attributes('aria-expanded')).toBe('true');

        await getFirstRoot().get('.ct-tree-item__toggle').trigger('click');
        await flushPromises();

        expect(getFirstRoot().attributes('aria-expanded')).toBe('false');

        await wrapper.get('[data-item-id="second-child"] .ct-tree-item__toggle').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-item-id="second-grand-child"]').isVisible()).toBe(true);
        expect(getFirstRoot().attributes('aria-expanded')).toBe('false');
    });

    it('should not initially expand root categories when a category is selected', async () => {
        Contena.Store.get('ctCategoryDetail').category = interactiveCategories.secondChild;

        const wrapper = await createWrapper(interactiveCategories, {
            categoryId: 'second-child',
        });
        await flushPromises();

        expect(wrapper.get('.ct-tree').attributes('data-initially-expanded-root')).toBe('false');
    });

    [
        { serviceChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7a' }] },
        {
            navigationChannels: [
                { id: '4d9ef75adbb149aa99785a0a969b3b7b' },
            ],
        },
        { footerChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7c' }] },
    ].forEach((entryPoint) => {
        it(`should not be able to delete a category having ${Object.keys(entryPoint)[0]} as initial entry point`, async () => {
            const wrapper = await createWrapper();
            const notificationSpy = jest
                .spyOn(Contena.Store.get('notification'), 'createNotification')
                .mockReturnValue(null);

            await setVmState(wrapper, {
                isLoadingInitialData: false,
            });

            const category = {
                id: '1a',
                isNew: () => false,
                parentId: 'parent',
                ...entryPoint,
            };

            await wrapper.vm.onDeleteCategory({ data: category, children: [] });

            expect(notificationSpy).toHaveBeenCalledTimes(1);
            expect(notificationSpy).toHaveBeenCalledWith({
                variant: 'error',
                title: 'global.default.error',
                message: 'ct-category.general.errorNavigationEntryPoint',
            });

            notificationSpy.mockRestore();
        });
    });

    it('should not be able to delete a category having serviceChannels as initial entry point', async () => {
        const wrapper = await createWrapper();
        const notificationSpy = jest.spyOn(Contena.Store.get('notification'), 'createNotification').mockReturnValue(null);

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const entryPoint = {
            serviceChannels: [{ id: '4d9ef75adbb149aa99785a0a969b3b7a' }],
        };
        const category = {
            id: '1a',
            isNew: () => false,
            parentId: 'parent',
            ...entryPoint,
        };

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        expect(notificationSpy).toHaveBeenCalledTimes(1);
        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'ct-category.general.errorNavigationEntryPoint',
        });

        notificationSpy.mockRestore();
    });

    it('should be able to delete a category having an empty entry point', async () => {
        const wrapper = await createWrapper();
        const notificationSpy = jest.spyOn(Contena.Store.get('notification'), 'createNotification').mockReturnValue(null);

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        const category = {
            id: '1a',
            isNew: () => false,
        };

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        expect(notificationSpy).not.toHaveBeenCalled();
        expect(wrapper.emitted()['category-checked-elements-count']).toBeUndefined();

        notificationSpy.mockRestore();
    });

    it('should be able to set elements count when delete category is checked', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });
        wrapper.vm.$refs.categoryTree.checkedElementsCount = 2;

        const category = {
            id: '1a',
            isNew: () => false,
        };

        await wrapper.vm.onDeleteCategory({
            data: category,
            children: [],
            checked: true,
        });

        const emitted = wrapper.emitted()['category-checked-elements-count'];

        expect(emitted).toBeTruthy();
        expect(emitted).toEqual([[1]]);
        expect(wrapper.vm.$refs.categoryTree.checkedElementsCount).toBe(1);
    });

    it('should not allow checked elements count to become negative when deleting the last checked category', async () => {
        const wrapper = await createWrapper();

        await setVmState(wrapper, {
            isLoadingInitialData: false,
        });

        wrapper.vm.$refs.categoryTree.checkedElementsCount = 0;

        const category = {
            id: '1a',
            isNew: () => false,
        };

        await wrapper.vm.onDeleteCategory({
            data: category,
            children: [],
            checked: true,
        });

        const emitted = wrapper.emitted()['category-checked-elements-count'];

        expect(emitted).toBeTruthy();
        expect(emitted).toEqual([[0]]);
        expect(wrapper.vm.$refs.categoryTree.checkedElementsCount).toBe(0);
    });

    it('should fix the sorting right after deleting a single category', async () => {
        const wrapper = await createWrapper();

        const category = {
            id: '2',
            isNew: () => false,
            parentId: '1',
            afterCategoryId: '1',
        };

        await setVmState(wrapper, {
            loadedCategories: {
                1: { id: '1', parentId: '1', afterCategoryId: null },
                2: { id: '2', parentId: '1', afterCategoryId: '1' },
                // The `afterCategoryId` is "1" here, because in the actual code it was already fixed before
                // `onDeleteCategory` is executed, see `ct-tree`::deleteElement()
                3: { id: '3', parentId: '1', afterCategoryId: '1' },
                4: { id: '4', parentId: '1', afterCategoryId: '3' },
            },
        });

        await wrapper.vm.onDeleteCategory({ data: category, children: [] });

        expect(wrapper.vm.loadedCategories[3].afterCategoryId).toBe('1');
    });

    it('should fix the sorting right after deleting multiple categories', async () => {
        const wrapper = await createWrapper();

        const categories = {
            2: {},
            4: {},
            5: {},
        };

        await setVmState(wrapper, {
            loadedCategories: {
                1: {
                    id: '1',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: null,
                },
                2: {
                    id: '2',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: '1',
                },
                3: {
                    id: '3',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: '2',
                },
                4: {
                    id: '4',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: '3',
                },
                5: {
                    id: '5',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: '4',
                },
                6: {
                    id: '6',
                    parentId: '1',
                    navigationChannels: null,
                    afterCategoryId: '5',
                },
            },
        });

        await wrapper.vm.deleteCheckedItems(categories);

        expect(wrapper.vm.loadedCategories[3].afterCategoryId).toBe('1');
        expect(wrapper.vm.loadedCategories[6].afterCategoryId).toBe('3');
    });

    it('should open the tree for active category on category change', async () => {
        const loadedCategories = {
            1: {
                id: '1',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: null,
            },
            2: {
                id: '2',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: '1',
            },
            3: {
                id: '3',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: '2',
            },
            4: {
                id: '4',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: '3',
            },
            5: {
                id: '5',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: '4',
            },
            6: {
                id: '6',
                parentId: '1',
                navigationChannels: null,
                afterCategoryId: '5',
            },
        };
        const toLoadCategories = {
            7: {
                id: '7',
                parentId: '2',
                navigationChannels: null,
                afterCategoryId: '2',
                path: '|1|2|',
            },
            8: {
                id: '8',
                parentId: '2',
                navigationChannels: null,
                afterCategoryId: '7',
                path: '|1|2|',
            },
            9: {
                id: '9',
                parentId: '8',
                navigationChannels: null,
                afterCategoryId: '8',
                path: '|1|2|8|',
            },
            10: {
                id: '10',
                parentId: '8',
                navigationChannels: null,
                afterCategoryId: '9',
                path: '|1|2|8|',
            },
            11: {
                id: '11',
                parentId: '8',
                navigationChannels: null,
                afterCategoryId: '10',
                path: '|1|2|8|',
            },
        };
        const categories = {
            ...loadedCategories,
            ...toLoadCategories,
        };

        const wrapper = await createWrapper(categories);

        await setVmState(wrapper, {
            loadedCategories: loadedCategories,
        });

        const initialLoadedCategoryCount = Object.values(wrapper.vm.loadedCategories).length;

        await wrapper.vm.$nextTick();

        wrapper.vm.$refs.categoryTree.openTreeById = jest.fn();

        Contena.Store.get('ctCategoryDetail').category = toLoadCategories[10];

        await flushPromises();

        expect(Object.values(wrapper.vm.loadedCategories)).toHaveLength(
            initialLoadedCategoryCount + Object.keys(toLoadCategories).length,
        );
        expect(wrapper.vm.$refs.categoryTree.openTreeById).toHaveBeenCalled();
    });
});
