import 'src/app/mixin/ct-inline-snippet.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(
        {
            template: `
            <div class="ct-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Contena.Mixin.getByName('ct-inline-snippet'),
            ],
            data() {
                return {
                    name: 'ct-mock-field',
                };
            },
        },
        {
            attachTo: document.body,
        },
    );
}

describe('src/app/mixin/ct-inline-snippet.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        Contena.Context.app.fallbackLocale = 'de-DE';
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should return the inline snippet', () => {
        const result = wrapper.vm.getInlineSnippet('ct.example');

        expect(result).toBe('ct.example');
    });

    it('should return empty string when using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet('');

        expect(result).toBe('');
    });

    it('should return correct value with locale using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'en-GB': 'English',
        });

        expect(result).toBe('English');
    });

    it('should return correct fallback value with locale using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'fr-FR': 'French',
            'de-DE': 'German',
        });

        expect(result).toBe('German');
    });

    it('should return first value when no fallback is defined using the getInlineSnippet method without value', () => {
        const result = wrapper.vm.getInlineSnippet({
            'fr-FR': 'French',
        });

        expect(result).toBe('French');
    });
});
