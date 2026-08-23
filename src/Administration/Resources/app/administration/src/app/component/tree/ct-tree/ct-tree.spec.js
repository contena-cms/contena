/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import getTreeItems from './fixtures/treeItems';

async function createWrapper(
    { props, route } = {
        props: {},
    },
) {
    const $route = route ?? {
        params: [
            {
                id: null,
            },
        ],
    };

    return mount(await wrapTestComponent('ct-tree', { sync: true }), {
        attachTo: document.body,
        props: {
            items: getTreeItems(),
            ...props,
        },
        global: {
            stubs: {
                'ct-contextual-field': await wrapTestComponent('ct-contextual-field'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-confirm-field': await wrapTestComponent('ct-confirm-field'),
                'ct-field-error': true,
                'ct-tree-input-field': true,
                'ct-context-menu-item': true,
                'ct-context-button': true,
                'ct-vnode-renderer': await wrapTestComponent('ct-vnode-renderer', { sync: true }),
                'ct-tree-item': await wrapTestComponent('ct-tree-item'),
                'ct-skeleton': await wrapTestComponent('ct-skeleton'),
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'ct-field-copyable': true,
                'ct-skeleton-bar': true,
            },
            mocks: {
                $route: {
                    ...$route,
                },
            },
            provide: {
                [routeLocationKey]: $route,
                validationService: {},
            },
        },
    });
}

describe('src/app/component/tree/ct-tree', () => {
    it('should render tree correctly with only the main item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const treeItems = wrapper.findAll('.ct-tree-item');
        expect(treeItems).toHaveLength(1);

        // parent should be closed
        expect(treeItems.at(0).classes()).not.toContain('is--opened');

        // parent should contain correct name
        expect(treeItems.at(0).find('.ct-tree-item__element').text()).toContain('Platform');
    });

    it('should render tree correctly when user open the main item', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.ct-tree-item .ct-tree-item__toggle').trigger('click');
        await flushPromises();

        // parent should be open
        const openedParent = wrapper.find('.ct-tree-item.is--opened');
        expect(openedParent.isVisible()).toBe(true);

        // parent should contain correct name
        expect(openedParent.find('.ct-tree-item__element').text()).toContain('Platform');

        // two children should be visible
        const childrenItems = openedParent.find('.ct-tree-item__children').findAll('.ct-tree-item');
        expect(childrenItems).toHaveLength(2);

        // first child should contain correct names
        expect(childrenItems.at(0).text()).toContain('Identity');
        expect(childrenItems.at(1).text()).toContain('Operations');
    });

    it('should render tree correctly when user open the main item and children group', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.ct-tree-item .ct-tree-item__toggle').trigger('click');

        const openedParent = wrapper.find('.ct-tree-item.is--opened');
        const childrenItems = openedParent.find('.ct-tree-item__children').findAll('.ct-tree-item');

        // open first child of parent
        await childrenItems.at(0).find('.ct-tree-item__toggle').trigger('click');
        await flushPromises();

        // check if all folders and items are correctly opened
        expect(childrenItems.at(0).text()).toContain('Identity');
        expect(childrenItems.at(1).text()).toContain('Operations');

        const identityFolder = childrenItems.at(0);
        const identityChildren = identityFolder.find('.ct-tree-item__children').findAll('.ct-tree-item');

        // check if children have correct class
        const identityChildrenNames = [
            'Users',
            'Roles',
            'Integrations',
            'Queues',
            'Scheduled tasks',
        ];

        identityChildren.forEach((item, index) => {
            expect(item.classes()).toContain('is--no-children');
            expect(item.text()).toContain(identityChildrenNames[index]);
        });
    });

    it('should select Queues and tick the checkboxes correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.ct-tree-item .ct-tree-item__toggle').trigger('click');

        const openedParent = wrapper.find('.ct-tree-item.is--opened');
        const childrenItems = openedParent.find('.ct-tree-item__children').findAll('.ct-tree-item');

        // open first child of parent
        const identityFolder = childrenItems.at(0);
        await identityFolder.find('.ct-tree-item__toggle').trigger('click');
        await flushPromises();

        // find the Queues item
        const queuesItem = identityFolder.find('.ct-tree-item__children').findAll('.ct-tree-item').at(3);

        expect(queuesItem.text()).toContain('Queues');

        const queuesCheckbox = queuesItem.getComponent('.mt-field--checkbox__container');
        expect(queuesCheckbox.props('checked')).toBe(false);
        await queuesCheckbox.get('input').setValue(true);
        await flushPromises();
        expect(queuesCheckbox.props('checked')).toBe(true);

        // check if parents contains partial checkbox
        const identityFolderCheckbox = identityFolder.findComponent(
            '.ct-tree-item__selection .mt-field--checkbox__container',
        );
        expect(identityFolderCheckbox.props().partial).toBe(true);

        const openedParentCheckbox = openedParent.findComponent('.ct-tree-item__selection .mt-field--checkbox__container');
        expect(openedParentCheckbox.props().partial).toBe(true);
    });

    it('should show the delete button', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-tree-actions__delete-items').exists()).toBeFalsy();

        Object.assign(wrapper.vm, {
            checkedElementsCount: 2,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-tree-actions__delete-items').exists()).toBeTruthy();
    });

    it('should allow to delete the items', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-tree-actions__delete-items').exists()).toBeFalsy();

        Object.assign(wrapper.vm, {
            checkedElementsCount: 2,
        });
        await wrapper.vm.$nextTick();

        await flushPromises();

        expect(wrapper.find('.ct-tree-actions__delete-items').attributes().disabled).toBeUndefined();
    });

    it('should not allow to delete the items', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-tree-actions__delete-items').exists()).toBeFalsy();

        await wrapper.setProps({
            allowDeleteItems: false,
        });

        Object.assign(wrapper.vm, {
            checkedElementsCount: 2,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-tree-actions__delete-items').attributes().disabled).toBeDefined();
    });

    it('should adjust the children count correctly, when moving elements out of a folder', async () => {
        const wrapper = await createWrapper();

        const treeItems = wrapper.props('items');

        const rootItemId = 'a1d1da1e6d434902a2e5ffed7784c951';
        const testItemIds = [
            'd3aabfa637cf435e8ad3c9bf1d2de565',
            '8da86665f27740dd8160c92e27b1c4c8',
        ];
        const rootItem = treeItems.find((element) => element.id === rootItemId);
        const testItems = testItemIds.map((id) => {
            return treeItems.find((element) => element.id === id);
        });
        let expectedRootChildCount = 2;

        expect(rootItem.childCount).toBe(rootItem.data.childCount);
        expect(rootItem.childCount).toBe(expectedRootChildCount);
        expect(rootItem.parentId).toBeNull();

        testItems.forEach((item) => {
            expect(item.childCount).toBe(item.data.childCount);
            expect(item.parentId).toBe(rootItemId);

            // Move the child outside and above its former parent
            wrapper.vm.startDrag({ item });
            wrapper.vm.moveDrag(item, rootItem);
            wrapper.vm.endDrag();

            expectedRootChildCount -= 1;

            expect(item.childCount).toBe(item.data.childCount);
            expect(rootItem.childCount).toBe(expectedRootChildCount);

            expect(item.parentId).toBeNull();
            expect(rootItem.parentId).toBeNull();
        });
    });

    it('should focus on the active tree item when focusin', async () => {
        const operationsId = getTreeItems().find((item) => item.name === 'Operations').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: operationsId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: operationsId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Get currently focused element
        const focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the active tree item
        expect(focusedElementAriaLabel).toContain('Operations');
    });

    it('should focus on the first tree item when nothing is active when focusin', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // Get currently focused element
        let focusedElement = document.activeElement;

        // Focused element should be on body
        expect(focusedElement.tagName).toBe('BODY');

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Get currently focused element
        focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the active tree item
        expect(focusedElementAriaLabel).toContain('Platform');
    });

    it('should use the arrowDown key for moving the focus to the next tree item', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Open the Identity tree item
        await wrapper.get('.ct-tree-item[aria-label="Identity"] .ct-tree-item__toggle').trigger('click');

        // Trigger arrowDown key event on the tree
        await wrapper.get('.ct-tree').trigger('keydown', {
            key: 'ArrowDown',
        });

        // Get currently focused element
        const focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the next tree item
        expect(focusedElementAriaLabel).toContain('Users');
    });

    it('should use the arrowUp key for moving the focus to the previous tree item', async () => {
        const operationsId = getTreeItems().find((item) => item.name === 'Operations').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: operationsId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: operationsId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Trigger arrowUp key event on the tree
        await wrapper.get('.ct-tree').trigger('keydown', {
            key: 'ArrowUp',
        });

        // Get currently focused element
        const focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the next tree item
        expect(focusedElementAriaLabel).toContain('Identity');
    });

    it('should use the arrowRight key for open the tree item', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // New tree item should not be visible
        expect(wrapper.find('.ct-tree-item[aria-label="Users"]').exists()).toBe(false);
        expect(wrapper.find('.ct-tree-item[aria-label="Roles"]').exists()).toBe(false);
        expect(wrapper.find('.ct-tree-item[aria-label="Integrations"]').exists()).toBe(false);
        expect(wrapper.find('.ct-tree-item[aria-label="Queues"]').exists()).toBe(false);
        expect(wrapper.find('.ct-tree-item[aria-label="Scheduled tasks"]').exists()).toBe(false);

        // Open the Identity tree item with arrowRight key
        await wrapper.get('.ct-tree-item[aria-label="Identity"]').trigger('keydown', {
            key: 'ArrowRight',
        });

        // New tree item should be visible
        expect(wrapper.get('.ct-tree-item[aria-label="Users"]').isVisible()).toBe(true);
        expect(wrapper.get('.ct-tree-item[aria-label="Roles"]').isVisible()).toBe(true);
        expect(wrapper.get('.ct-tree-item[aria-label="Integrations"]').isVisible()).toBe(true);
        expect(wrapper.get('.ct-tree-item[aria-label="Queues"]').isVisible()).toBe(true);
        expect(wrapper.get('.ct-tree-item[aria-label="Scheduled tasks"]').isVisible()).toBe(true);
    });

    it('should use the arrowRight key to focus to the first child when tree item is open', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Open the Identity tree item with arrowRight key
        await wrapper.get('.ct-tree-item[aria-label="Identity"]').trigger('keydown', {
            key: 'ArrowRight',
        });

        // Press arrowRight key so that the focus is on the first child
        await wrapper.get('.ct-tree-item[aria-label="Identity"]').trigger('keydown', {
            key: 'ArrowRight',
        });

        // Get currently focused element
        const focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the first child
        expect(focusedElementAriaLabel).toContain('Users');
    });

    it('should use the arrowLeft key to focus on the parent tree item', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Press the arrowLeft key so that the focus is on the parent
        await wrapper.get('.ct-tree-item[aria-label="Identity"]').trigger('keydown', {
            key: 'ArrowLeft',
        });

        // Get currently focused element
        const focusedElement = document.activeElement;

        // Get aria-label of the focused element
        const focusedElementAriaLabel = focusedElement.getAttribute('aria-label');

        // Focused element should be the parent
        expect(focusedElementAriaLabel).toContain('Platform');
    });

    it('should use the arrowLeft key to close the tree item when open', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Press the arrowLeft key so that the focus is on the parent
        await wrapper.get('.ct-tree-item[aria-label="Identity"]').trigger('keydown', {
            key: 'ArrowLeft',
        });

        // Children should be visible
        expect(wrapper.get('.ct-tree-item[aria-label="Identity"]').isVisible()).toBe(true);

        // Press the arrowLeft key so that the tree item is closed
        await wrapper.get('.ct-tree-item[aria-label="Platform"]').trigger('keydown', {
            key: 'ArrowLeft',
        });

        // Children should not be visible
        expect(wrapper.find('.ct-tree-item[aria-label="Identity"]').exists()).toBe(false);
    });

    it('should use the enter key to trigger the route change', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;
        const routeChangeMock = jest.fn();

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
                onChangeRoute: routeChangeMock,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Press the enter key so that the route change is triggered
        await wrapper.get('.ct-tree').trigger('keydown', {
            key: 'Enter',
        });

        // Route change should be triggered
        expect(routeChangeMock).toHaveBeenCalled();
    });

    it('should use the space key to toggle the checkbox', async () => {
        const identityId = getTreeItems().find((item) => item.name === 'Identity').id;

        const wrapper = await createWrapper({
            props: {
                activeTreeItemId: identityId,
                initiallyExpandedRoot: true,
            },
            route: {
                params: {
                    id: identityId,
                },
            },
        });
        await flushPromises();

        // Trigger focusin event on the tree
        await wrapper.get('.ct-tree').trigger('focusin');
        await flushPromises();

        // Check if tree item has no checked value
        let treeItem = wrapper.get('.ct-tree-item[aria-label="Identity"] input[type="checkbox"]');
        expect(treeItem.element.checked).toBe(false);

        // Press the space key so that the route change is triggered
        await wrapper.get('.ct-tree').trigger('keydown', {
            key: ' ',
        });

        // Check if tree item has checked value
        treeItem = wrapper.get('.ct-tree-item[aria-label="Identity"] input[type="checkbox"]');
        expect(treeItem.element.checked).toBe(true);
    });
});
