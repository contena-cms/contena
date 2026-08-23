import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-ai-copilot-warning', { sync: true }));
}

describe('src/app/asyncComponent/feedback/ct-ai-copilot-warning/index.ts', () => {
    /* @type Wrapper */
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be able to override the default text with custom text', async () => {
        await wrapper.setProps({
            text: 'Custom text',
        });

        // Ensure custom text is rendered instead of default text
        expect(wrapper.find('.ct-ai-copilot-warning').text()).toBe('Custom text');
    });
});
