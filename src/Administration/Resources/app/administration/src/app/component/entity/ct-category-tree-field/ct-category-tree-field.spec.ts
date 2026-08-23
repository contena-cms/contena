import { defineComponent } from 'vue';
/* global Entity */
import { mount, type VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import component from './index';

interface CategoryTreeFieldVm {
    categoriesCollection: EntityCollection<'category'>;
    categories: Array<Entity<'category'> & { disabled?: boolean }>;
    term: string;
    selectedCategoriesTotal: number;
    selectedCategoriesItemsIds: string[];
    onCheckItem: (item: { id: string; checked: boolean; data: Partial<Entity<'category'>> }) => boolean;
    removeItem: (item: Partial<Entity<'category'>> & { id: string }) => void;
}

const categoryData = [
    {
        id: 'categoryId-2',
        attributes: {
            id: 'categoryId-2',
            type: 'page',
        },
        translated: {
            name: 'categoryName-2',
        },
        name: 'categoryName-2',
        type: 'page',
        parentId: null,
        afterCategoryId: null,
        childCount: 0,
        relationships: {},
    },
    {
        id: 'categoryId-3',
        attributes: {
            id: 'categoryId-3',
            type: 'page',
        },
        translated: {
            name: 'categoryName-3',
        },
        name: 'categoryName-3',
        type: 'page',
        parentId: null,
        afterCategoryId: 'categoryId-2',
        childCount: 0,
        relationships: {},
    },
    {
        id: 'categoryId-4',
        attributes: {
            id: 'categoryId-4',
            type: 'folder',
        },
        translated: {
            name: 'categoryName-4',
        },
        name: 'categoryName-4',
        type: 'folder',
        parentId: null,
        afterCategoryId: 'categoryId-3',
        childCount: 0,
        relationships: {},
    },
];

function createCategoryCollection(items: Entity<'category'>[] = []): EntityCollection<'category'> {
    return new EntityCollection('/category', 'category', Contena.Context.api, null, items, items.length, null);
}

const categoryCollection = () => createCategoryCollection(categoryData as unknown as Entity<'category'>[]);
const assignmentCollection = () =>
    new EntityCollection<'category_content_layout'>(
        '/category-content-layout',
        'category_content_layout',
        Contena.Context.api,
        null,
        [],
        0,
        null,
    );

const floatingUiStub = defineComponent({
    props: {
        isOpened: {
            type: Boolean,
            default: false,
        },
    },
    template: `
        <div>
            <slot name="trigger" />
            <slot v-if="isOpened" />
        </div>
    `,
});

const blockStub = defineComponent({
    inheritAttrs: false,
    props: {
        name: {
            type: String,
            default: null,
        },
        data: {
            type: Object,
            default: null,
        },
    },
    template: '<slot />',
});

const treeStub = defineComponent({
    template: '<div class="ct-tree"></div>',
});

const controlStub = defineComponent({
    template: '<span><slot /></span>',
});

const wrappers: VueWrapper<CategoryTreeFieldVm>[] = [];

async function createWrapper(props = {}) {
    const wrapper = mount(component, {
        attachTo: document.body,
        props: {
            placeholder: 'some-placeholder',
            categoriesCollection: createCategoryCollection(),
            ...props,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: (entity: string) => ({
                        search: () => Promise.resolve(entity === 'category' ? categoryCollection() : assignmentCollection()),
                    }),
                },
            },
            stubs: {
                'ct-block': blockStub,
                'mt-floating-ui': floatingUiStub,
                'mt-badge': controlStub,
                'mt-checkbox': controlStub,
                'mt-icon': controlStub,
                'mt-loader': controlStub,
                'ct-contextual-field': await wrapTestComponent('ct-contextual-field'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-field-error': true,
                'ct-highlight-text': true,
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'ct-tree': treeStub,
                'ct-tree-item': treeStub,
                'ct-loader': true,
                'ct-color-badge': true,
                'ct-skeleton': true,
                'ct-vnode-renderer': true,
                'ct-context-button': true,
                'ct-context-menu-item': true,
                'ct-confirm-field': true,
                'ct-tree-input-field': true,
            },
        },
    }) as unknown as VueWrapper<CategoryTreeFieldVm>;

    wrappers.push(wrapper);
    return wrapper;
}

describe('src/app/component/entity/ct-category-tree-field', () => {
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('should close the dropdown when selecting in the single select mode', async () => {
        const wrapper = await createWrapper({ singleSelect: true });
        await flushPromises();

        expect(wrapper.find('.ct-category-tree-field__results-popover').exists()).toBe(false);

        wrapper.vm.term = 'some-search-term';
        await wrapper.find('.ct-category-tree__input-field').trigger('focus');
        await flushPromises();

        expect(wrapper.find('.ct-category-tree-field__results-popover').exists()).toBe(true);

        wrapper.vm.onCheckItem({
            id: 'categoryId-0',
            checked: true,
            data: { id: 'categoryId-0', translated: { name: 'some-data' } },
        });
        await flushPromises();

        expect(wrapper.find('.ct-category-tree-field__results-popover').exists()).toBe(false);
    });

    it('should remove the category item', async () => {
        const initialCategories = [
            {
                id: 'categoryId-0',
                attributes: { id: 'categoryId-0' },
                translated: { name: 'categoryName-0' },
                relationships: {},
            },
            {
                id: 'categoryId-1',
                attributes: { id: 'categoryId-1' },
                translated: { name: 'categoryName-1' },
                relationships: {},
            },
        ];
        const wrapper = await createWrapper({
            categoriesCollection: createCategoryCollection(initialCategories as unknown as Entity<'category'>[]),
        });
        await flushPromises();

        expect(wrapper.vm.categoriesCollection).toHaveLength(2);

        wrapper.vm.removeItem(initialCategories[0]);

        expect(wrapper.vm.categoriesCollection).toHaveLength(1);
        expect(wrapper.emitted('update:categoriesCollection')).toHaveLength(1);
        expect(wrapper.emitted('update:categoriesCollection')?.[0]?.[0]).toHaveLength(1);
    });

    it('should display more category items', async () => {
        const initialCategories = [
            {
                id: 'categoryId-0',
                attributes: { id: 'categoryId-0' },
                translated: { name: 'categoryName-0' },
                relationships: {},
            },
            {
                id: 'categoryId-1',
                attributes: { id: 'categoryId-1' },
                translated: { name: 'categoryName-1' },
                relationships: {},
            },
        ];
        const wrapper = await createWrapper({
            contentLayoutId: 'content-layout-id',
            categoriesCollection: createCategoryCollection(initialCategories as unknown as Entity<'category'>[]),
        });
        await flushPromises();

        wrapper.vm.selectedCategoriesTotal = 5;
        await wrapper.vm.$nextTick();

        await wrapper.find('.ct-category-tree-field__label-more').trigger('click');

        expect(wrapper.emitted('categories-load-more')).toBeTruthy();
    });

    it('should have checked categories', async () => {
        const selectedCategories = [
            {
                id: 'categoryId-2',
                attributes: { id: 'categoryId-2' },
                translated: { name: 'categoryName-2' },
                relationships: {},
            },
            {
                id: 'categoryId-4',
                attributes: { id: 'categoryId-4' },
                translated: { name: 'categoryName-4' },
                relationships: {},
            },
        ];
        const wrapper = await createWrapper({
            categoriesCollection: createCategoryCollection(selectedCategories as unknown as Entity<'category'>[]),
        });

        expect(Array.from(wrapper.vm.selectedCategoriesItemsIds)).toStrictEqual([
            'categoryId-2',
            'categoryId-4',
        ]);
    });

    it('should disable categories not included in allowedTypes list', async () => {
        const wrapper = await createWrapper({
            categoriesCollection: createCategoryCollection(categoryData as unknown as Entity<'category'>[]),
            allowedTypes: ['page'],
        });
        await flushPromises();

        expect(wrapper.vm.categories).toHaveLength(categoryData.length);
        expect(wrapper.vm.categories.filter((category) => category.disabled)).toHaveLength(1);
    });
});
