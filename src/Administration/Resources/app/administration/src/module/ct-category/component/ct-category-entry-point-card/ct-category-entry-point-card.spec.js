/**
 * @ct-package discovery
 */
import { mount } from '@vue/test-utils';

const { Context } = Contena;
const { EntityCollection } = Contena.Data;

async function createWrapper(category = {}) {
    const defaultCategory = {
        navigationChannels: [],
        footerChannels: [],
        serviceChannels: [],
    };
    const mergedCategory = {
        ...defaultCategory,
        ...category,
    };

    return mount(await wrapTestComponent('ct-category-entry-point-card', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'ct-cms-list-item': true,
                'ct-single-select': {
                    template: '<div class="ct-single-select"></div>',
                    props: ['disabled'],
                },
                'ct-category-channel-multi-select': true,
                'router-link': true,
                'ct-category-entry-point-modal': true,
            },
        },
        props: {
            category: mergedCategory,
        },
    });
}

describe('src/module/ct-category/component/ct-category-entry-point-card', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an disabled navigation selection', async () => {
        const wrapper = await createWrapper();

        const selection = wrapper.getComponent('.ct-category-entry-point-card__entry-point-selection');

        expect(selection.props('disabled')).toBe(true);
    });

    it('should have an enabled navigation selection', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const selection = wrapper.getComponent('.ct-category-entry-point-card__entry-point-selection');

        expect(selection.props('disabled')).toBe(false);
    });

    it('should have no initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('');
    });

    it('should have main navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const channels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationChannels: channels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationChannels');
    });

    it('opens the upstream home page configuration for a main navigation Channel', async () => {
        global.activeAclRoles = ['category.editor'];

        const channels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: 'web-channel',
                name: 'Web',
                translated: {
                    name: 'Web',
                },
            },
        ]);
        const wrapper = await createWrapper({
            id: 'category-1',
            navigationChannels: channels,
        });

        wrapper.vm.openConfigureHomeModal();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.configureHomeModalVisible).toBe(true);
        expect(wrapper.find('ct-category-entry-point-modal-stub').exists()).toBe(true);

        wrapper.vm.closeConfigureHomeModal();
        expect(wrapper.vm.configureHomeModalVisible).toBe(false);
    });

    it('should have footer navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const channels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            footerChannels: channels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('footerChannels');
    });

    it('should have service navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const channels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            serviceChannels: channels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('serviceChannels');
    });

    it('should reset its sales channel collections', async () => {
        global.activeAclRoles = ['category.editor'];

        const navigationChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const footerChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const serviceChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationChannels,
            footerChannels,
            serviceChannels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationChannels');
        wrapper.vm.resetChannelCollections();
        // it should stay on 'navigationChannels' but the other collections should be cleared.
        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationChannels');

        expect(navigationChannels).toHaveLength(1);
        expect(footerChannels).toHaveLength(0);
        expect(serviceChannels).toHaveLength(0);
    });

    it('should add newly selected sales channels', async () => {
        global.activeAclRoles = ['category.editor'];

        const navigationChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const footerChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const serviceChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const selectionChannels = new EntityCollection('/channel', 'channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationChannels,
            footerChannels,
            serviceChannels,
        });

        wrapper.vm.onChannelChange(selectionChannels);

        // the category should now have two sales channels in its 'navigationChannel' collection.
        expect(wrapper.vm.category[wrapper.vm.selectedEntryPoint]).toHaveLength(2);
    });
});
