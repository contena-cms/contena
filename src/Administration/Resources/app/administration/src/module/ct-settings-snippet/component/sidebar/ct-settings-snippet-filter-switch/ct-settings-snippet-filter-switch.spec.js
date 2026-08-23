import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        await wrapTestComponent('ct-settings-snippet-filter-switch', {
            sync: true,
        }),
        {
            props: {
                name: 'Contena',
            },
        },
    );
}

describe('ct-settings-snippet-filter-switch', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should contain a prop property, called: value', async () => {
        expect(wrapper.vm.value).toBe(false);
        await wrapper.setProps({
            value: true,
        });
        expect(wrapper.vm.value).toBe(true);

        const fieldSwitchInput = wrapper.find('.mt-switch input');
        expect(fieldSwitchInput.attributes('name')).toBe('Contena');
    });
});
