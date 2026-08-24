import { mount } from '@vue/test-utils';
import Sortable from 'sortablejs';
import component from './ct-table-column-setting.vue';
import type { TableColumnSetting } from './ct-table-column-setting.types';

jest.mock('sortablejs', () => ({
    __esModule: true,
    default: { create: jest.fn(() => ({ destroy: jest.fn() })) },
}));

const mockSortableCreate = Sortable.create as jest.Mock;

const columns: TableColumnSetting[] = [
    { key: 'user', title: 'User', checked: true, fixed: 'left', required: true },
    { key: 'contact', title: 'Contact', checked: true },
    { key: 'active', title: 'Status', checked: false },
    { key: 'action', title: 'Actions', checked: true, fixed: 'right', required: true },
];

const defaults: TableColumnSetting[] = columns.map((column) => ({ ...column, checked: true }));

function createWrapper() {
    return mount(component, {
        props: {
            columns,
            defaultColumns: defaults,
            title: 'Column settings',
            allLabel: 'All columns',
            resetLabel: 'Reset',
            cancelLabel: 'Cancel',
            applyLabel: 'Apply',
            fixedLeftLabel: 'Fix left',
            fixedRightLabel: 'Fix right',
        },
        global: {
            stubs: {
                'a-tooltip': { template: '<div><slot /></div>' },
                'a-popover': { template: '<div><slot /><slot name="title" /><slot name="content" /></div>' },
                'a-button': { template: '<button><slot name="icon" /><slot /></button>' },
                'a-checkbox': { template: '<label><slot /></label>' },
                'a-divider': true,
                'ct-icon': true,
            },
        },
    });
}

describe('app/component/base/ct-table-column-setting', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('opens with an isolated draft and initializes sortable ordering', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onOpenChange(true);
        wrapper.vm.draftColumns[1].checked = false;

        expect(wrapper.vm.draftColumns).not.toBe(columns);
        expect(columns[1].checked).toBe(true);
        expect(mockSortableCreate).toHaveBeenCalledTimes(1);
    });

    it('tracks all and indeterminate states for optional columns', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onOpenChange(true);
        expect(wrapper.vm.allOptionalColumnsVisible).toBe(false);
        expect(wrapper.vm.someOptionalColumnsVisible).toBe(true);

        wrapper.vm.toggleAll(true);
        expect(wrapper.vm.allOptionalColumnsVisible).toBe(true);

        wrapper.vm.toggleAll(false);
        expect(wrapper.vm.allOptionalColumnsVisible).toBe(false);
        expect(wrapper.vm.someOptionalColumnsVisible).toBe(false);
        const draftColumns = wrapper.vm.draftColumns as TableColumnSetting[];
        expect(draftColumns.filter((column) => column.required).every((column) => column.checked)).toBe(true);
    });

    it('does not hide required columns and toggles fixed positions', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.onOpenChange(true);

        wrapper.vm.toggleColumn('user', false);
        wrapper.vm.toggleFixed('contact', 'right');

        expect(wrapper.vm.draftColumns[0].checked).toBe(true);
        expect(wrapper.vm.draftColumns[1].fixed).toBe('right');

        wrapper.vm.toggleFixed('contact', 'right');
        expect(wrapper.vm.draftColumns[1].fixed).toBe(false);
    });

    it('resets to defaults and cancel discards the draft', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.onOpenChange(true);

        wrapper.vm.toggleColumn('contact', false);
        wrapper.vm.resetDraft();
        expect(wrapper.vm.draftColumns).toEqual(defaults);

        wrapper.vm.toggleColumn('contact', false);
        wrapper.vm.cancel();
        await wrapper.vm.onOpenChange(true);
        expect(wrapper.vm.draftColumns).toEqual(columns);
    });

    it('applies a cloned ordered configuration', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.onOpenChange(true);

        const [active] = wrapper.vm.draftColumns.splice(2, 1);
        wrapper.vm.draftColumns.splice(1, 0, active);
        wrapper.vm.apply();

        const emitted = wrapper.emitted('apply')?.[0]?.[0] as TableColumnSetting[] | undefined;
        expect(emitted?.map(({ key }) => key)).toEqual([
            'user',
            'active',
            'contact',
            'action',
        ]);
        expect(emitted).not.toBe(wrapper.vm.draftColumns);
        expect(emitted?.[0]).not.toBe(wrapper.vm.draftColumns[0]);
    });
});
