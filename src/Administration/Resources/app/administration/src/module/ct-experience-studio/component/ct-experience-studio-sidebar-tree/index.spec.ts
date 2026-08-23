/* eslint-disable @typescript-eslint/no-unsafe-call */
import { shallowMount } from '@vue/test-utils';
import sidebarTreeComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-sidebar-tree', () => {
    const createWrapper = (props: Record<string, unknown> = {}) =>
        shallowMount(sidebarTreeComponent, {
            props,
            global: {
                provide: {
                    acl: { can: () => true },
                },
            },
        });

    it('emits move payload when element is dropped in root area', () => {
        const wrapper = createWrapper();

        wrapper.vm.onRootDrop({ elementId: 'element-id' }, { newParentElementId: null, newSlotName: null, newIndex: null });

        expect(wrapper.emitted('move-element')).toEqual([
            [
                {
                    elementId: 'element-id',
                    newParentElementId: null,
                    newSlotName: null,
                    newIndex: null,
                },
            ],
        ]);
    });

    it('uses external validator for root drops', () => {
        const validateMoveTarget = jest.fn().mockReturnValue(false);
        const wrapper = createWrapper({ validateMoveTarget });

        expect(
            wrapper.vm.validateMoveDrop(
                { elementId: 'element-id' },
                { newParentElementId: null, newSlotName: null, newIndex: null },
            ),
        ).toBe(false);
        expect(validateMoveTarget).toHaveBeenCalledWith({
            elementId: 'element-id',
            newParentElementId: null,
            newSlotName: null,
            newIndex: null,
        });
    });
});
