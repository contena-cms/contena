import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-tree-item', { sync: true }), {
        props: {
            item: {
                data: {
                    id: '1a2b3c',
                },
                children: [],
            },
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'ct-field': true,
                'ct-context-button': true,
                'ct-context-menu-item': true,
                'ct-checkbox-field': true,
                'ct-confirm-field': true,
                'ct-tree-item': await wrapTestComponent('ct-tree-item', {
                    sync: true,
                }),
                'ct-vnode-renderer': true,
                'ct-skeleton': true,
            },
            provide: {
                [routeLocationKey]: { params: {} },
                getItems: () => {},
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    mounted(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    updated(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                },
            },
        },
    });
}

describe('src/app/component/tree/ct-tree-item', () => {
    it('should have an enabled context menu', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.get('.ct-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled context menu', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            disableContextMenu: true,
        });

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeDefined();
    });

    it('should contain the default context menu tooltip text when context menu is disabled', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.attributes()['data-tooltip-message']).toBe('ct-tree.general.actions.actionsDisabledInLanguage');
    });

    it('should contain the custom context menu tooltip text when context menu is disabled', async () => {
        const wrapper = await createWrapper();

        const customTooltipMessage = 'You do not have the rights to edit the tree item.';

        await wrapper.setProps({
            contextMenuTooltipText: customTooltipMessage,
        });

        const contextButton = wrapper.find('.ct-tree-item__context_button');
        expect(contextButton.attributes()['data-tooltip-message']).toBe(customTooltipMessage);
    });

    it('should be able to create new categories', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-tree-item__before-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.ct-tree-item__after-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.ct-tree-item__sub-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.ct-tree-item__without-position-action').exists()).toBeFalsy();
    });

    it('should not be able to create new categories with position', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowCreateWithoutPosition: true,
        });

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-tree-item__before-action').exists()).toBeFalsy();
        expect(contextButton.find('.ct-tree-item__after-action').exists()).toBeFalsy();
        expect(contextButton.find('.ct-tree-item__sub-action').exists()).toBeFalsy();
        expect(contextButton.find('.ct-tree-item__without-position-action').exists()).toBeTruthy();
    });

    it('should be unable to create new categories', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowNewCategories: false,
        });

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-tree-item__before-action').attributes().disabled).toBeDefined();
        expect(contextButton.find('.ct-tree-item__after-action').attributes().disabled).toBeDefined();
        expect(contextButton.find('.ct-tree-item__sub-action').attributes().disabled).toBeDefined();
    });

    it('should be able to delete categories', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-context-menu__group-button-delete').attributes().disabled).toBeUndefined();
    });

    it('should be unable to delete categories', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
        });
        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-context-menu__group-button-delete').attributes().disabled).toBeDefined();
    });

    it('should not show href attribute', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {},
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).toBeFalsy();
    });

    it('should show href attribute', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {},
            getItemUrl: (item) => {
                return 'detail/:id'.replace(':id', item.data.id);
            },
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).not.toBe('detail/1a2b');
        expect(treeLink.attributes().href).toBe('detail/1a2b3c');
    });

    it('should be able to duplicate items', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        await wrapper.setProps({
            allowDuplicate: true,
        });

        expect(contextButton.find('.ct-context-menu__duplicate-action').exists()).toBeTruthy();
    });

    it('should be unable to duplicate items', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.ct-tree-item__context_button');

        expect(contextButton.find('.ct-context-menu__duplicate-action').exists()).toBeFalsy();
    });
});
