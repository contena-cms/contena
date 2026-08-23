import { mount } from '@vue/test-utils';
import { MtRadioGroupRoot, MtRadioGroupList, MtRadioGroupItem } from '@contena/meteor-component-library';

const uploadTaskMock = {
    running: false,
    src: File,
    uploadTag: 'upload-tag-ct-media-index',
    targetId: 'aaaef50651e04f59bbc9c309b5110e23',
    fileName: 'my-demo-image',
    extension: 'jpg',
    error: null,
    successAmount: 0,
    failureAmount: 1,
    totalAmount: 1,
};

describe('components/utils/ct-duplicated-media-v2', () => {
    let wrapper;
    let uploads = {};

    beforeEach(async () => {
        uploads = {};
        wrapper = mount(await wrapTestComponent('ct-duplicated-media-v2', { sync: true }), {
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve([{ id: 'foo' }]),
                                get: () =>
                                    Promise.resolve({
                                        id: 'foo',
                                        hasFile: true,
                                    }),
                                delete: () => Promise.resolve(),
                            };
                        },
                    },
                    mediaPresignedUploadService: {
                        prepareUpload: jest.fn(),
                        uploadToPresignedUrl: jest.fn(),
                        finalizeUpload: jest.fn(),
                    },
                    mediaService: {
                        addDefaultListener: jest.fn(),
                        removeDefaultListener: jest.fn(),
                        runUploads: jest.fn(),
                        addUpload: (tag, uploadTask) => {
                            if (!uploads[tag]) uploads[tag] = [];

                            uploads[tag].push(uploadTask);
                        },
                        provideName: async (fileName) => {
                            return { fileName: `${fileName}_(2)` };
                        },
                        keepFile: jest.fn(),
                    },
                },
                stubs: {
                    'ct-modal': {
                        template: `
                            <div class="ct-modal">
                                <slot name="modal-header">
                                    <slot name="modal-title"></slot>
                                </slot>
                                <slot name="modal-body">
                                     <slot></slot>
                                </slot>
                                <slot name="modal-footer">
                                </slot>
                            </div>
                        `,
                    },
                    'ct-container': true,
                    'ct-media-preview-v2': true,
                    MtRadioGroupRoot,
                    MtRadioGroupList,
                    MtRadioGroupItem,
                    'ct-base-field': await wrapTestComponent('ct-base-field'),
                    'ct-field-error': true,
                    'ct-media-media-item': true,
                    'ct-checkbox-field': true,
                    'router-link': true,
                    'ct-loader': true,
                    'ct-help-text': true,
                    'ct-inheritance-switch': true,
                    'ct-ai-copilot-badge': true,
                },
            },
        });
    });

    it('should upload the renamed file', async () => {
        await wrapper.vm.renameFile(uploadTaskMock);

        const matchingUploadTask = uploads[uploadTaskMock.uploadTag].find((upload) => {
            return upload.targetId === uploadTaskMock.targetId;
        });

        expect(matchingUploadTask.fileName).toBe(`${uploadTaskMock.fileName}_(2)`);
        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-ct-media-index');
    });

    it('should keep the existing file', async () => {
        wrapper.vm.defaultOption = 'Keep';
        Object.assign(wrapper.vm, { failedUploadTasks: [uploadTaskMock] });
        await wrapper.vm.$nextTick();

        await wrapper.vm.solveDuplicate();
        await wrapper.vm.$nextTick();

        const expectedTask = { ...uploadTaskMock, ...{ targetId: 'foo' } };

        expect(wrapper.vm.mediaService.keepFile).toHaveBeenCalledWith(expectedTask.uploadTag, expectedTask);
    });

    it('should replace the file on the server with the local file', async () => {
        wrapper.vm.defaultOption = 'Replace';
        Object.assign(wrapper.vm, { failedUploadTasks: [uploadTaskMock] });
        await wrapper.vm.$nextTick();
        await flushPromises();

        const radio = wrapper.find('input[type="radio"]');
        await radio.setValue('checked');

        const replaceButton = wrapper.find('.ct-duplicated-media-v2__upload');
        await replaceButton.trigger('click');

        expect(wrapper.vm.mediaService.runUploads).toHaveBeenCalledWith('upload-tag-ct-media-index');
    });
});
