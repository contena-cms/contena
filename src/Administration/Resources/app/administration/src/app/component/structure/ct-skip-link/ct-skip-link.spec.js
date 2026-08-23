import 'src/app/component/structure/ct-skip-link';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        {
            template: `
            <ct-skip-link />
            <main id="main" tabindex="-1"></main>`,
        },
        {
            global: {
                stubs: {
                    'ct-skip-link': await wrapTestComponent('ct-skip-link'),
                },
            },
            attachTo: document.body,
        },
    );
}

describe('src/app/component/structure/ct-skip-link/index.ts', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should handle focus class on focus and blur events', async () => {
        const skipLink = wrapper.find('.ct-skip-link');

        await skipLink.trigger('focus');

        expect(skipLink.classes()).toContain('ct-skip-link__focussed');

        await skipLink.trigger('blur');

        expect(skipLink.classes()).not.toContain('ct-skip-link__focussed');
    });

    it('should focus element with id main on click', async () => {
        const skipLink = wrapper.find('.ct-skip-link');

        await skipLink.trigger('click');

        expect(document.activeElement.id).toBe('main');
    });
});
