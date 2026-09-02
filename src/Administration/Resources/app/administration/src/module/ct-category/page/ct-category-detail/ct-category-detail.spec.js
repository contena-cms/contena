/**
 * @ct-package discovery
 */
import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import './store';

describe('src/module/ct-category/page/ct-category-detail', () => {
    let pageWrapper;
    const saveMock = jest.fn(() => Promise.resolve());
    const getMock = jest.fn((entityName) => {
        if (entityName === 'landing_page') {
            return Promise.resolve({
                id: 'landing-page-id',
                channels: [],
            });
        }

        return Promise.resolve({
            id: 'category-id',
            navigationChannels: [],
            footerChannels: [],
            serviceChannels: [],
        });
    });

    async function createWrapper(props = {}) {
        const component = await wrapTestComponent('ct-category-detail', { sync: true });
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    name: 'ct.category.detail',
                    path: '/',
                    component,
                    props,
                    meta: { $module: { title: 'ct-category.general.mainMenuItemIndex' } },
                },
            ],
        });
        await router.push('/');

        pageWrapper = mount(
            { template: '<router-view />' },
            {
                global: {
                    plugins: [router],
                    stubs: {
                        'ct-page': {
                            template: `
                    <div>
                        <slot name="smart-bar-actions"></slot>
                        <slot></slot>
                        <slot name="side-content"></slot>
                    </div>`,
                        },
                        'ct-category-tree': {
                            template: '<div class="ct-category-tree"></div>',
                            props: [
                                'allowEdit',
                                'allowCreate',
                                'allowDelete',
                            ],
                        },
                        'ct-button-process': {
                            template: '<div class="ct-button-process"><slot></slot></div>',
                            props: ['disabled'],
                        },
                        'ct-sidebar-collapse': {
                            template: `
                    <div class="ct-sidebar-collapse">
                        <slot name="header"></slot>
                        <slot name="actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                        },
                        'ct-collapse': await wrapTestComponent('ct-collapse'),
                        'ct-landing-page-tree': true,
                        'ct-search-bar': true,
                        'ct-language-switch': true,
                        'ct-skeleton': true,
                        'ct-category-view': true,
                        'ct-category-entry-point-overwrite-modal': true,
                        'ct-landing-page-view': true,
                        'ct-discard-changes-modal': true,
                        'ct-empty-state': true,
                    },
                    provide: {
                        repositoryFactory: {
                            create: (entityName) => ({
                                search: () =>
                                    Promise.resolve({
                                        get: () => ({ sections: [] }),
                                    }),
                                save: saveMock,
                                get: (...args) => getMock(entityName, ...args),
                            }),
                        },
                        seoUrlService: {},
                    },
                },
            },
        );

        await flushPromises();

        return pageWrapper.findComponent(component);
    }

    beforeEach(() => {
        global.activeAclRoles = [];
        getMock.mockClear();
    });

    afterEach(() => {
        pageWrapper?.unmount();
    });

    it('loads the active category when the detail route is mounted', async () => {
        Contena.Store.get('ctCategoryDetail').landingPage = { id: 'previous-landing-page-id' };

        await createWrapper({ categoryId: 'category-id' });
        await flushPromises();

        expect(getMock).toHaveBeenCalledWith('category', 'category-id', Contena.Context.api, expect.anything());
        expect(Contena.Store.get('ctCategoryDetail').category.id).toBe('category-id');
        expect(Contena.Store.get('ctCategoryDetail').landingPage).toBeNull();
    });

    it('loads the active landing page when the detail route is mounted', async () => {
        Contena.Store.get('ctCategoryDetail').category = { id: 'previous-category-id' };

        await createWrapper({ landingPageId: 'landing-page-id' });
        await flushPromises();

        expect(getMock).toHaveBeenCalledWith('landing_page', 'landing-page-id', Contena.Context.api, expect.anything());
        expect(Contena.Store.get('ctCategoryDetail').landingPage.id).toBe('landing-page-id');
        expect(Contena.Store.get('ctCategoryDetail').category).toBeNull();
    });

    it('should not allow to modify', async () => {
        const wrapper = await createWrapper();

        Contena.Store.get('ctCategoryDetail').category = {};

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.getComponent('.ct-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(true);

        const categoryTree = wrapper.getComponent('.ct-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(false);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to edit', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        Contena.Store.get('ctCategoryDetail').category = {};

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.getComponent('.ct-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.ct-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(false);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to create', async () => {
        global.activeAclRoles = [
            'category.creator',
            'category.editor',
        ];

        const wrapper = await createWrapper();

        Contena.Store.get('ctCategoryDetail').category = {};

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.getComponent('.ct-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.ct-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(false);
    });

    it('should allow to delete', async () => {
        global.activeAclRoles = [
            'category.creator',
            'category.editor',
            'category.deleter',
        ];

        const wrapper = await createWrapper();

        Contena.Store.get('ctCategoryDetail').category = {};

        await wrapper.vm.$nextTick();

        const saveButton = wrapper.getComponent('.ct-category-detail__save-action');

        expect(saveButton.props('disabled')).toBe(false);

        const categoryTree = wrapper.getComponent('.ct-category-tree');

        expect(categoryTree.props('allowCreate')).toBe(true);
        expect(categoryTree.props('allowEdit')).toBe(true);
        expect(categoryTree.props('allowDelete')).toBe(true);
    });

    it('should load landing page translations for CMS inheritance handling', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.landingPageCriteria.associations.some(({ association }) => association === 'translations')).toBe(
            true,
        );
    });
});
