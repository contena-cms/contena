import { mount } from '@vue/test-utils';

describe('src/module/ct-media/component/ct-media-modal-v2', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('ct-media-modal-v2', { sync: true }), {
            props: {
                uploadTag: 'my-upload',
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'mt-modal': true,
                    'mt-modal-root': true,
                    'mt-tabs': true,
                    'ct-media-sidebar': true,
                    'ct-media-upload-v2': true,
                    'ct-upload-listener': true,
                    'ct-media-grid': true,
                    'ct-media-breadcrumbs': true,
                    'ct-simple-search-field': true,
                    'ct-media-library': true,
                    'ct-media-media-item': true,
                },
                provide: {
                    mediaService: {},
                },
            },
        });
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('ct-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('ct-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });
});
