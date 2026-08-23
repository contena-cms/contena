import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('mt-tabs', { sync: true }), {
        props: {
            items: [],
            positionIdentifier: 'jest-test-component',
        },
    });
}

describe('src/app/component/meteor-wrapper/mt-tabs', () => {
    it('should pass the items from the props to the final component', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            items: [
                { label: 'Tab 1', name: 'tab1' },
                { label: 'Tab 2', name: 'tab2' },
            ],
        });

        const mtTabsOriginal = wrapper.findComponent({ ref: 'mtTabsOriginal' });
        expect(mtTabsOriginal.props('items')).toEqual([
            { label: 'Tab 1', name: 'tab1' },
            { label: 'Tab 2', name: 'tab2' },
        ]);
    });
});
