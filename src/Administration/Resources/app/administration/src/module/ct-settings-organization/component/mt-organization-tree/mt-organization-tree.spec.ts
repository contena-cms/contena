import { mount } from '@vue/test-utils';

const company = {
    id: 'company-id',
    parentId: null,
    name: 'Contena',
    translated: { name: '索维拉' },
    code: 'CONTENA',
    organizationUnit: { name: '公司' },
    afterId: null,
    childCount: 1,
};
const department = {
    id: 'department-id',
    parentId: company.id,
    name: 'Engineering',
    translated: { name: '研发部' },
    code: 'ENGINEERING',
    organizationUnit: { name: '部门' },
    afterId: null,
    childCount: 0,
};

async function createWrapper() {
    return mount(await wrapTestComponent('mt-organization-tree', { sync: true }), {
        props: {
            items: [
                company,
                department,
            ],
            selectedOrganizationId: company.id,
            canCreate: true,
            canEdit: true,
            canDelete: true,
        },
        global: {
            mocks: { $t: (key: string) => key },
            stubs: {
                'mt-button': {
                    props: ['disabled'],
                    emits: ['click'],
                    template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                },
                'mt-checkbox': true,
                'mt-icon': true,
            },
        },
    });
}

describe('module/ct-settings-organization/component/mt-organization-tree', () => {
    it('renders a subtle selected row and expands children lazily', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.text()).toContain('索维拉');
        expect(wrapper.findAll('.mt-organization-tree__row')).toHaveLength(1);
        expect(wrapper.find('.mt-organization-tree__row').classes()).toContain('is--selected');

        (wrapper.vm as unknown as { toggleExpanded: (id: string) => void }).toggleExpanded(company.id);
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.mt-organization-tree__row')).toHaveLength(2);
        expect(wrapper.emitted('load-children')?.[0]).toEqual([company.id]);
    });

    it('exposes semantic events for embedding pages', async () => {
        const wrapper = await createWrapper();
        const api = wrapper.vm as unknown as {
            onSelect: (organization: typeof company) => void;
            onAddChild: (organization: typeof company) => void;
            onDelete: (organization: typeof company) => void;
            onChecked: (id: string, checked: boolean) => void;
            onBatchDelete: () => void;
        };

        api.onSelect(company);
        api.onAddChild(company);
        api.onDelete(company);
        api.onChecked(company.id, true);
        api.onBatchDelete();

        expect(wrapper.emitted('select-organization')?.[0]).toEqual([company]);
        expect(wrapper.emitted('add-child')?.[0]).toEqual([company]);
        expect(wrapper.emitted('delete-organization')?.[0]).toEqual([company]);
        expect(wrapper.emitted('batch-delete')?.[0]).toEqual([[company.id]]);
    });
});
