/**
 * @ct-package framework
 */

import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref } from 'vue';
import useMetaInfo from './use-meta-info';

function mountWithMetaInfo(getMetaInfo: () => { title?: string }): { unmount: () => void } {
    return mount(
        defineComponent({
            template: '<div />',
            setup() {
                useMetaInfo(getMetaInfo);
            },
        }),
    ) as unknown as { unmount: () => void };
}

describe('src/app/composables/use-meta-info', () => {
    beforeEach(() => {
        document.title = 'untouched';
    });

    it('sets the document title from the getter', () => {
        mountWithMetaInfo(() => ({ title: 'Article | Content | Contena' }));

        expect(document.title).toBe('Article | Content | Contena');
    });

    it('follows a reactive dependency of the getter', async () => {
        const identifier = ref('Draft');

        mountWithMetaInfo(() => ({ title: identifier.value }));
        expect(document.title).toBe('Draft');

        identifier.value = 'Saved article';
        await nextTick();

        expect(document.title).toBe('Saved article');
    });

    it('leaves the title alone when the getter returns no title', () => {
        mountWithMetaInfo(() => ({}));

        expect(document.title).toBe('untouched');
    });

    it('stops following the getter once the component is unmounted', async () => {
        const identifier = ref('Draft');
        const wrapper = mountWithMetaInfo(() => ({ title: identifier.value }));

        wrapper.unmount();
        identifier.value = 'Saved article';
        await nextTick();

        expect(document.title).toBe('Draft');
    });
});
