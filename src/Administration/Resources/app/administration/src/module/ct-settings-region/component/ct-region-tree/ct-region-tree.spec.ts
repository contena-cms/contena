import { mount } from '@vue/test-utils';

const region = {
    id: 'region-id',
    name: 'Guangdong',
    translated: { name: '广东省' },
    code: '44',
    afterId: null,
    childCount: 1,
};

async function createWrapper() {
    return mount(await wrapTestComponent('ct-region-tree', { sync: true }), {
        props: {
            items: [region],
            selectedRegionId: region.id,
            canCreate: true,
            canEdit: true,
            canDelete: true,
        },
        global: {
            mocks: { $t: (key: string) => key },
            stubs: {
                'ct-tree': {
                    props: ['items'],
                    template:
                        '<div><slot name="headline" /><slot name="items" :tree-items="items" :check-item="() => {}" :selected-items-path-ids="[]" :checked-item-ids="[]" /></div>',
                },
                'ct-tree-item': {
                    props: ['item'],
                    template:
                        '<div><slot name="content" :item="{ id: item.id, data: item }" /><slot name="actions" :item="{ id: item.id, data: item }" :delete-element="() => {}" /></div>',
                },
                'ct-context-button': { template: '<div><slot /></div>' },
                'ct-context-menu-item': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                'mt-button': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
            },
        },
    });
}

describe('module/ct-settings-region/component/ct-region-tree', () => {
    it('renders the translated Region name and code', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.text()).toContain('广东省');
        expect(wrapper.text()).toContain('44');
        expect(wrapper.find('.ct-region-tree__item').classes()).toContain('is--selected');
    });

    it('emits the selected tree item', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.ct-region-tree__item').trigger('click');

        expect(wrapper.emitted('select-region')?.[0]?.[0]).toEqual({ id: region.id, data: region });
    });

    it('keeps node actions as semantic events for an embedding page', async () => {
        const wrapper = await createWrapper();
        const treeItem = { id: region.id, data: region };
        const publicApi = wrapper.vm as unknown as {
            onAddChildRegion: (item: typeof treeItem) => void;
            onDeleteRegion: (item: typeof treeItem) => void;
            onBatchDelete: (selection: string[]) => void;
        };

        publicApi.onAddChildRegion(treeItem);
        publicApi.onDeleteRegion(treeItem);
        publicApi.onBatchDelete([region.id]);

        expect(wrapper.emitted('add-child-region')?.[0]?.[0]).toEqual(treeItem);
        expect(wrapper.emitted('delete-region')?.[0]?.[0]).toEqual(treeItem);
        expect(wrapper.emitted('batch-delete')?.[0]?.[0]).toEqual([region.id]);
    });

    it('only renders the batch action headline after items are selected', async () => {
        const wrapper = await createWrapper();
        const publicApi = wrapper.vm as unknown as { onCheckedElementsCount: (count: number) => void };

        expect(wrapper.text()).not.toContain('ct-settings-region.tree.general.treeHeadSelected');

        publicApi.onCheckedElementsCount(2);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('ct-settings-region.tree.general.treeHeadSelected');
    });
});
