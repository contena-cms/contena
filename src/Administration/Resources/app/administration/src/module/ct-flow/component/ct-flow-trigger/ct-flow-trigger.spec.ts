import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

const wrappers: Array<{ unmount: () => void }> = [];
const events = [
    { name: 'user.recovery.request', aware: [] },
    { name: 'mail.before.send', aware: [] },
    { name: 'mail.after.create.message', aware: [] },
    { name: 'mail.sent', aware: [] },
];

async function createWrapper(hasSequences = false) {
    const component = (await import('./ct-flow-trigger.vue')).default;
    const wrapper = mount(component, {
        props: { eventName: 'user.recovery.request', events, hasSequences },
        global: {
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'mt-text-field': defineComponent({ template: '<div><slot name="suffix" /></div>' }),
                'mt-button': true,
                'mt-icon': true,
                'ct-tree': defineComponent({
                    name: 'ct-tree',
                    template: '<div />',
                }),
                'mt-modal-root': true,
                'mt-modal': true,
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}

describe('module/ct-flow/component/ct-flow-trigger', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () => ({ addPrivilegeMappingEntry: jest.fn() }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('lets the upstream tree expand only the active path and returns search results as breadcrumbs', async () => {
        const wrapper = await createWrapper();
        const trigger = wrapper.vm as unknown as {
            openDropdown: () => void;
            expandedIds: Set<string>;
            treeItems: Array<{ id: string; parentId: string | null }>;
            searchTerm: string;
            searchResults: Array<{ name: string }>;
        };

        trigger.openDropdown();
        await wrapper.vm.$nextTick();
        expect(wrapper.findComponent({ name: 'ct-tree' }).attributes('initially-expanded-root')).toBeUndefined();
        expect(wrapper.findComponent({ name: 'ct-tree' }).attributes('active-tree-item-id')).toBe('user.recovery.request');
        expect(trigger.expandedIds).toEqual(new Set());
        expect(trigger.treeItems).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: 'user', parentId: null }),
                expect.objectContaining({ id: 'user.recovery', parentId: 'user' }),
                expect.objectContaining({ id: 'user.recovery.request', parentId: 'user.recovery' }),
            ]),
        );
        trigger.searchTerm = 'mail sent';
        await wrapper.vm.$nextTick();
        expect(trigger.searchResults.map((event) => event.name)).toEqual(['mail.sent']);
    });

    it('matches the upstream email and user trigger tree structure', async () => {
        const wrapper = await createWrapper();
        const trigger = wrapper.vm as unknown as {
            treeItems: Array<{ id: string; parentId: string | null; name: string }>;
        };

        expect(trigger.treeItems).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ id: 'mail', parentId: null }),
                expect.objectContaining({ id: 'mail.after.create.message', parentId: 'mail.after.create' }),
                expect.objectContaining({ id: 'user.recovery.request', parentId: 'user.recovery' }),
            ]),
        );
    });

    it('requires confirmation before replacing a trigger with configured sequences', async () => {
        const wrapper = await createWrapper(true);
        const trigger = wrapper.vm as unknown as {
            selectEvent: (eventName: string) => void;
            showConfirmModal: boolean;
            confirmTriggerChange: () => void;
        };

        trigger.selectEvent('mail.sent');
        expect(trigger.showConfirmModal).toBe(true);
        expect(wrapper.emitted('update:eventName')).toBeUndefined();

        trigger.confirmTriggerChange();
        expect(wrapper.emitted('update:eventName')?.[0]).toEqual(['mail.sent']);
    });
});
