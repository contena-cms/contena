import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('ct-settings-tag-detail-modal', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            create: () => {
                                return {
                                    isNew: () => true,
                                };
                            },

                            save: jest.fn(() => Promise.resolve()),
                        }),
                    },
                    syncService: {
                        sync: jest.fn(),
                    },
                    acl: {
                        can: () => {
                            return true;
                        },
                    },
                },
                stubs: {
                    'mt-modal-root': {
                        props: {
                            isOpen: Boolean,
                        },
                        template: `
                    <div v-if="isOpen" class="mt-modal-root-stub">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-modal': {
                        props: {
                            title: String,
                            width: String,
                        },
                        template: `
                    <div class="mt-modal-stub">
                        <slot></slot>
                        <slot name="footer"></slot>
                        </div>
                    `,
                    },
                    'mt-modal-close': true,
                    'mt-modal-action': true,
                    'mt-tabs': true,
                    'ct-text-field': true,
                    'ct-settings-tag-detail-assignments': true,
                },
            },
        },
    );
}

describe('module/ct-settings-tag/component/ct-settings-tag-detail-modal', () => {
    it('uses the upstream tab layout', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const tabs = wrapper.find('mt-tabs-stub');

        expect(wrapper.find('.ct-settings-tag-detail-modal__tabs').exists()).toBe(true);
        expect(wrapper.find('.ct-settings-tag-detail-modal__tabs-content').exists()).toBe(true);
        expect(tabs.attributes('position-identifier')).toBe('ct-settings-tag-detail-modal');
        expect(tabs.attributes('small')).toBe('true');
    });

    it('wraps footer actions in a right-aligned layout container', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-settings-tag-detail-modal__footer').exists()).toBe(true);
    });

    it('should set tag, to be added and to be deleted on create', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.tag).not.toBeNull();

        const initialAssignments = {
            media: {},
            rules: {},
            users: {},
        };

        expect(wrapper.vm.assignmentsToBeAdded).toEqual(initialAssignments);
        expect(wrapper.vm.assignmentsToBeDeleted).toEqual(initialAssignments);
    });

    it('should emit event on save', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.assignmentsToBeDeleted = {
            media: { '0b7957f43b9b489fb7bc02a0a233274e': {} },
            rules: {},
            users: {},
        };
        await wrapper.vm.$nextTick();

        const done = jest.fn();
        await wrapper.vm.onSave(done);

        expect(wrapper.vm.syncService.sync).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.tagRepository.save).toHaveBeenCalledTimes(1);

        const onSaveEvents = wrapper.emitted('finish');
        expect(onSaveEvents).toHaveLength(1);
        expect(done).toHaveBeenCalledTimes(1);
    });

    it('should emit event on cancel', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.onCancel();

        const onCancelEvents = wrapper.emitted('close');
        expect(onCancelEvents).toHaveLength(1);
    });

    it('should increase and decrease counts from to be added and to be deleted', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({
            counts: { media: 7 },
        });

        expect(wrapper.vm.computedCounts.media).toBe(7);

        wrapper.vm.assignmentsToBeDeleted = {
            media: { a: {}, b: {} },
            invalid: { a: {} },
        };
        wrapper.vm.assignmentsToBeAdded = {
            media: { a: {}, b: {}, c: {}, d: {} },
            invalid: { a: {} },
        };
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.computedCounts.media).toBe(9);
    });

    it('should add and remove assignments', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.assignmentsToBeDeleted = {
            media: { a: { id: 'a' }, b: { id: 'b' } },
        };
        wrapper.vm.assignmentsToBeAdded = {
            media: { c: { id: 'c' }, d: { id: 'd' } },
        };
        await wrapper.vm.$nextTick();

        wrapper.vm.addAssignment('media', 'b', { id: 'b' });
        wrapper.vm.addAssignment('media', 'e', { id: 'e' });
        wrapper.vm.removeAssignment('media', 'd', { id: 'd' });
        wrapper.vm.removeAssignment('media', 'f', { id: 'f' });

        expect(wrapper.vm.assignmentsToBeDeleted.media).toEqual({
            a: { id: 'a' },
            f: { id: 'f' },
        });
        expect(wrapper.vm.assignmentsToBeAdded.media).toEqual({
            c: { id: 'c' },
            e: { id: 'e' },
        });
    });
});
