import { mount } from '@vue/test-utils';
import component from './ct-blog-image.vue';

function createWrapper() {
    return mount(component, {
        global: {
            stubs: {
                'ct-label': {
                    template: '<div class="ct-label"><slot /></div>',
                },
                'ct-context-button': {
                    template: '<div class="ct-context-button"><slot /></div>',
                },
                'ct-context-menu-item': true,
                'ct-media-preview-v2': true,
            },
        },
        props: {
            mediaId: 'b849df93c8bb4c7a94441fb0e82be516',
        },
    });
}

describe('app/component/base/ct-blog-image', () => {
    it('shows the cover action when the media is not already the cover', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ showCoverLabel: true });

        expect(wrapper.find('.ct-blog-image__button-cover').exists()).toBe(true);
    });

    it('hides the cover action when the media is already the cover', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ showCoverLabel: true, isCover: true });

        expect(wrapper.find('.ct-blog-image__button-cover').exists()).toBe(false);
    });

    it('shows the spatial label for spatial media', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ isSpatial: true });

        expect(wrapper.find('.ct-blog-image__spatial-label .ct-label__spatial').exists()).toBe(true);
    });

    it('shows the AR label for AR-ready spatial media', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ isSpatial: true, isArReady: true });

        expect(wrapper.find('.ct-blog-image__spatial-label .ct-label__ar-ready').exists()).toBe(true);
    });
});
