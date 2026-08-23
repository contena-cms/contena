import { mount } from '@vue/test-utils';

const item = {
    name: 'Example',
    id: '084310ac700b4f6a8a008bb7843399e2',
};

async function createWrapper(slots = {}) {
    return mount(await wrapTestComponent('ct-select-result', { sync: true }), {
        props: {
            index: 0,
            item,
        },
        slots,
        global: {
            provide: {
                setActiveItemIndex: jest.fn(),
            },
            stubs: {
                'ct-block': {
                    props: [
                        'name',
                        'data',
                    ],
                    template: '<div><slot /></div>',
                },
                'mt-icon': true,
            },
        },
    });
}

async function createWrapperWithoutActiveItemProvider() {
    return mount(await wrapTestComponent('ct-select-result', { sync: true }), {
        props: {
            index: 0,
            item,
        },
        global: {
            stubs: {
                'ct-block': {
                    props: [
                        'name',
                        'data',
                    ],
                    template: '<div><slot /></div>',
                },
                'mt-icon': true,
            },
        },
    });
}

describe('src/app/component/form/select/base/ct-select-result', () => {
    let wrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('selects the matching result through the keyboard event', async () => {
        const eventBusSpy = jest.spyOn(Contena.Utils.EventBus, 'emit');
        wrapper = await createWrapper();

        Contena.Utils.EventBus.emit('item-select-by-keyboard', 0);
        await flushPromises();

        expect(eventBusSpy).toHaveBeenCalledWith('item-select', item);
        eventBusSpy.mockRestore();
    });

    it('shows the description only when the slot is provided', async () => {
        wrapper = await createWrapper();
        expect(wrapper.find('.ct-select-result__result-item-description').exists()).toBe(false);
        wrapper.unmount();

        wrapper = await createWrapper({ description: 'foobar' });

        expect(wrapper.find('.ct-select-result__result-item-description').text()).toContain('foobar');
    });

    it('does not throw when the active item provider is unavailable', async () => {
        wrapper = await createWrapperWithoutActiveItemProvider();

        await wrapper.find('li').trigger('mouseenter');

        expect(wrapper.find('li').exists()).toBe(true);
    });
});
