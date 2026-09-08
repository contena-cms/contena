import { DOMWrapper, mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-help-center-v2', { sync: true }), {
        attachTo: document.body,
        global: {
            stubs: {
                'ct-shortcut-overview': {
                    props: ['showModal'],
                    template: '<div class="ct-shortcut-overview-stub" />',
                },
            },
        },
    });
}

function menuItems() {
    return new DOMWrapper(document.body).findAll('.mt-action-menu-item');
}

describe('src/app/component/utils/ct-help-center', () => {
    let wrapper;

    beforeEach(() => {
        const store = Contena.Store.get('adminHelpCenter');
        store.showHelpSidebar = false;
        store.showShortcutModal = false;
    });

    afterEach(() => {
        wrapper?.unmount();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    it('should open the help center menu when the trigger is clicked', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(menuItems()).toHaveLength(0);

        await wrapper.find('.ct-help-center__button').trigger('click');
        await flushPromises();

        expect(Contena.Store.get('adminHelpCenter').showHelpSidebar).toBe(true);
        expect(menuItems().length).toBeGreaterThan(0);
    });

    it('opens and closes the keyboard shortcut overview', async () => {
        wrapper = await createWrapper();

        wrapper.vm.openShortcutModal();
        await wrapper.vm.$nextTick();

        expect(Contena.Store.get('adminHelpCenter').showShortcutModal).toBe(true);

        wrapper.vm.closeShortcutModal();
        await wrapper.vm.$nextTick();

        expect(Contena.Store.get('adminHelpCenter').showShortcutModal).toBe(false);
    });
});
