import { mount } from '@vue/test-utils';

const mediaService = {
    runUploads: jest.fn().mockResolvedValue(),
    renameMedia: jest.fn().mockResolvedValue(),
};

const createWrapper = async () => {
    return mount(await wrapTestComponent('ct-media-modal-replace', { sync: true }), {
        props: {
            itemToReplace: {
                id: 'media-id-123',
                fileName: 'image',
                fileExtension: 'png',
                isLoading: false,
            },
        },
        global: {
            stubs: {
                'ct-modal': true,
                'ct-media-upload-v2': true,
                'mt-button': true,
            },
            provide: {
                mediaService,
                mediaPresignedUploadService: {},
                repositoryFactory: {
                    create: jest.fn(),
                },
            },
        },
    });
};

describe('components/media/ct-media-modal-replace', () => {
    beforeEach(() => {
        Contena.Store.get('context').app.config = {
            settings: { presignedUploadSupported: false },
        };
    });

    it('uploads via mediaService and renames back to the original filename', async () => {
        jest.spyOn(Contena.Utils, 'createId').mockReturnValue('random-conflict-free-id');
        const wrapper = await createWrapper();

        const uploadData = [{ fileName: 'contena', extension: 'png', src: 'blob:...' }];
        wrapper.vm.onNewUpload({ data: uploadData });

        expect(uploadData[0].fileName).toBe('random-conflict-free-id');

        await wrapper.vm.replaceMediaItem();

        expect(mediaService.runUploads).toHaveBeenCalledWith('media-id-123');
        expect(mediaService.renameMedia).toHaveBeenCalledWith('media-id-123', 'image');
    });
});
