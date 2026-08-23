import { mount } from '@vue/test-utils';
async function createWrapper() {
    return mount(await wrapTestComponent('ct-media-folder-info', { sync: true }), {
        props: {
            mediaFolder: {
                id: 'jest',
                name: 'Test folder',
                getEntityName: () => 'media_folder',
            },
            editable: false,
        },
        global: {
            provide: {
                mediaService: {},
            },
            stubs: {
                'ct-media-collapse': true,
                'ct-media-quickinfo-metadata-item': true,
                'ct-confirm-field': true,
                'ct-media-modal-folder-settings': true,
                'ct-media-modal-folder-dissolve': true,
                'ct-media-modal-move': true,
                'ct-media-modal-delete': true,
            },
        },
    });
}

describe('src/module/ct-media/component/sidebar/ct-media-folder-info', () => {
    it('should not have error class by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': false,
        });
    });

    it('should have error class while having folder name error', async () => {
        Contena.Store.get('error').addApiError({
            expression: 'media_folder.jest.name',
            error: {
                code: 'some-error-code',
            },
        });

        const wrapper = await createWrapper(true);

        expect(wrapper.vm.nameItemClasses).toStrictEqual({
            'has--error': true,
        });
    });
});
