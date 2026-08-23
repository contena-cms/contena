import { mount } from '@vue/test-utils';
import findByText from '../../../../../test/_helper_/find-by-text';

const uploadSpy = jest.fn(() => Promise.resolve({}));
const updateExtensionDataSpy = jest.fn(() => Promise.resolve({}));
const userConfigSaveSpy = jest.fn(() => Promise.resolve({}));

async function createWrapper(userConfig = {}) {
    const userConfigRepository = {
        search: jest.fn().mockResolvedValue(Array.isArray(userConfig) ? userConfig : []),
        create: jest.fn().mockReturnValue({}),
        save: userConfigSaveSpy,
    };

    const wrapper = mount(await wrapTestComponent('ct-extension-file-upload', { sync: true }), {
        global: {
            stubs: {
                'mt-checkbox': {
                    props: [
                        'checked',
                        'label',
                    ],
                    emits: ['update:checked'],
                    template:
                        '<label><input type="checkbox" :checked="checked" @change="$emit(\'update:checked\', $event.target.checked)" />{{ label }}</label>',
                },
                'ct-modal': {
                    props: ['title'],
                    template:
                        '<div><div class="ct-modal__title">{{ title }}</div><div class="ct-modal__body"><slot/></div><slot name="modal-footer"></slot></div>',
                },
                'ct-loader': true,
                'router-link': true,
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
            },
            provide: {
                extensionStoreActionService: {
                    upload: uploadSpy,
                },
                repositoryFactory: {
                    create: () => userConfigRepository,
                },
            },
        },
        attachTo: document.body,
    });

    await flushPromises();

    return wrapper;
}

function createFile(size = 44320, name = 'test-plugin.zip', type = 'application/zip') {
    return new File([new ArrayBuffer(size)], name, {
        type: type,
    });
}

describe('src/module/ct-extension/component/ct-extension-file-upload', () => {
    beforeAll(() => {
        Contena.Service().register('contenaExtensionService', () => {
            return {
                updateExtensionData: updateExtensionDataSpy,
            };
        });
    });

    beforeEach(async () => {
        Contena.Store.get('notification').notifications = {};
        Contena.Store.get('notification').growlNotifications = {};
    });

    it('should show warning modal and then call the file input form', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.get('.ct-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        expect(wrapper.find('.ct-extension-file-upload-confirm-modal').exists()).toBe(false);

        // click on upload
        const uploadButton = wrapper.get('.ct-extension-file-upload__button');
        await uploadButton.trigger('click');

        await wrapper.vm.$nextTick();
        await flushPromises();

        const warningModal = wrapper.get('.ct-extension-file-upload-confirm-modal');
        expect(warningModal.get('.ct-extension-file-upload-confirm-modal__actions').exists()).toBe(true);

        // fileInput has not been clicked before
        expect(fileInput.element.click).not.toHaveBeenCalled();

        const continueButton = findByText(warningModal, 'button', 'global.default.confirm');
        await continueButton.trigger('click');

        // expect that the input gets clicked
        expect(fileInput.element.click).toHaveBeenCalled();
    });

    it('should not show warning modal if its hidden by user config', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.find('.ct-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        wrapper.vm.pluginUploadUserConfig = {
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: true,
            },
        };

        // fileInput has not been clicked before
        expect(fileInput.element.click).not.toHaveBeenCalled();

        // click on upload
        const uploadButton = wrapper.find('.ct-extension-file-upload__button');
        await uploadButton.trigger('click');

        const warningModal = wrapper.find('.ct-extension-file-upload-confirm-modal');
        expect(warningModal.exists()).toBe(false);

        // expect that the input gets clicked
        expect(fileInput.element.click).toHaveBeenCalled();
    });

    it('should update user config on file upload', async () => {
        const wrapper = await createWrapper();

        // spy for file input click
        const fileInput = wrapper.get('.ct-extension-file-upload__file-input');
        jest.spyOn(fileInput.element, 'click');

        wrapper.vm.pluginUploadUserConfig = {
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: false,
            },
        };

        // click on upload
        const uploadButton = wrapper.get('.ct-extension-file-upload__button');
        await uploadButton.trigger('click');

        const warningModal = wrapper.get('.ct-extension-file-upload-confirm-modal');

        const hideCheckbox = warningModal.get("input[type='checkbox']");
        await hideCheckbox.setChecked();

        await wrapper.vm.handleUpload([createFile()]);

        expect(userConfigSaveSpy).toHaveBeenCalled();
        expect(userConfigSaveSpy.mock.calls[0][0]).toEqual({
            key: 'extension.plugin_upload',
            userId: 'abc',
            value: {
                hide_upload_warning: true,
            },
        });
    });

    it('should upload the correct file when user selects a file', async () => {
        const wrapper = await createWrapper();

        // upload a file
        const fileInput = wrapper.find('.ct-extension-file-upload__file-input');
        const mockFile = createFile();

        Object.defineProperty(fileInput.element, 'files', {
            value: [mockFile],
        });

        // trigger file change
        await fileInput.trigger('change');

        // check if upload gets called with correct file
        const formDataMock = new FormData();
        formDataMock.append('file', mockFile);

        expect(uploadSpy).toHaveBeenCalledWith(formDataMock);

        // check if installed extensions get updated
        expect(updateExtensionDataSpy).toHaveBeenCalled();
    });

    it('should throw an error if the upload goes wrong', async () => {
        const wrapper = await createWrapper();

        // no growl message was thrown
        expect(Object.keys(Contena.Store.get('notification').growlNotifications)).toHaveLength(0);

        // return an error from the upload
        uploadSpy.mockImplementationOnce(() =>
            Promise.reject({
                response: {
                    data: {
                        errors: [
                            'Wrong file format',
                        ],
                    },
                },
            }),
        );

        // upload a wrong file
        const fileInput = wrapper.find('.ct-extension-file-upload__file-input');
        Object.defineProperty(fileInput.element, 'files', {
            value: ['wrongFile'],
        });

        // trigger file change
        await fileInput.trigger('change');

        // check if error notification gets thrown
        await wrapper.vm.$nextTick();
        const growlNotifications = Contena.Store.get('notification').growlNotifications;

        expect(Object.keys(growlNotifications)).toHaveLength(1);
        Object.keys(growlNotifications).forEach((key) => {
            expect(growlNotifications[key]).toHaveProperty('message');
            expect(growlNotifications[key].message).toBe('ct-extension.errors.messageGenericFailure');
            expect(growlNotifications[key]).toHaveProperty('title');
            expect(growlNotifications[key].title).toBe('global.default.error');
            expect(growlNotifications[key]).toHaveProperty('variant');
            expect(growlNotifications[key].variant).toBe('error');
        });
    });
});
