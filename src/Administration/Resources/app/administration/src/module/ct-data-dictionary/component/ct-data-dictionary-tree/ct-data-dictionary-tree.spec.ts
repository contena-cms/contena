import { computed, defineComponent } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import tree from './ct-data-dictionary-tree.vue';

const treeStub = defineComponent({
    props: [
        'items',
        'sortable',
    ],
    setup(props) {
        return {
            normalizedItems: computed(() =>
                (props.items as Array<{ id: string; data?: { entity?: unknown }; entity?: unknown }>).map((item) => ({
                    id: item.id,
                    data: item.data?.entity ? item.data : { entity: item.entity ?? item },
                })),
            ),
        };
    },
    template: '<div class="test-tree"><slot name="items" :tree-items="normalizedItems" :checked-item-ids="[]" /></div>',
});

describe('module/ct-data-dictionary/component/ct-data-dictionary-tree', () => {
    let wrapper: VueWrapper | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('exposes the contena tree actions through explicit extension events', () => {
        const item = {
            id: 'item-id',
            label: '男',
            code: 'male',
            active: true,
        };

        wrapper = mount(tree, {
            props: {
                items: [
                    item,
                ],
                rootId: 'dictionary-id',
                rootLabel: '性别',
                activeItemId: item.id,
                canEdit: true,
                canCreate: true,
                canDelete: true,
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-tree': treeStub,
                    'ct-tree-item': defineComponent({
                        template: '<div><slot name="content" :item="item" /><slot name="actions" :item="item" /></div>',
                        props: ['item'],
                    }),
                    'ct-context-button': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-context-menu-item': defineComponent({
                        template: '<button @click="$emit(\'click\')"><slot /></button>',
                    }),
                    'ct-skeleton': true,
                },
            },
        });

        expect(wrapper.find('.test-tree').exists()).toBe(true);
        expect((wrapper.vm as unknown as { sortable: boolean }).sortable).toBe(true);
        expect((wrapper.vm as unknown as { treeItems: Array<{ id: string }> }).treeItems[0].id).toBe('dictionary-id');

        (wrapper.vm as unknown as { onAddChildTreeItem: (item: unknown) => void }).onAddChildTreeItem({
            id: 'dictionary-id',
            data: { isDictionaryRoot: true, entity: {} },
        });

        expect(wrapper.emitted('add-child')).toEqual([[null]]);
    });
});
