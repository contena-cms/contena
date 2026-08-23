import { mount } from '@vue/test-utils';

async function createWrapper(mediaServiceFunctions = {}, props = {}) {
    return mount(await wrapTestComponent('ct-media-media-item', { sync: true }), {
        global: {
            provide: {
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    ...mediaServiceFunctions,
                },
            },
            stubs: {
                'ct-media-base-item': {
                    name: 'ct-media-base-item',
                    emits: [
                        'media-item-click',
                        'media-item-selection-add',
                        'media-item-selection-remove',
                    ],
                    props: {
                        allowMultiSelect: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                        item: {
                            type: Object,
                            required: true,
                        },
                        allowEdit: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                        allowDelete: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                    },
                    template: `
                    <div class="ct-media-base-item">
                        <slot name="preview" v-bind="{ item }"></slot>
                        <slot name="context-menu" v-bind="{ item, allowEdit, allowDelete }"></slot>
                        <slot></slot>
                    </div>`,
                },
                'ct-media-preview-v2': true,
                'ct-text-field': true,
                'mt-text-field': true,
                'ct-context-menu-item': true,
                'ct-media-modal-replace': true,
                'ct-media-modal-delete': true,
                'ct-media-modal-move': true,
                'ct-media-modal-v2': true,
                'ct-extension-icon': true,
                'mt-icon': true,
                'ct-time-ago': true,
            },
        },
        props: {
            item: {
                url: 'https://example.com/Test.png',
                fileName: 'Test.png',
                fileExtension: 'png',
                fileSize: 12345,
                mimeType: 'image/png',
                id: 'media-id',
                hasFile: true,
                private: false,
                ...props.item,
            },
            ...props,
        },
    });
}

describe('components/media/ct-media-media-item', () => {
    it('emits a normal item click for the media library selection model', async () => {
        const wrapper = await createWrapper();
        const originalDomEvent = new MouseEvent('click');

        wrapper.vm.emitItemClick(originalDomEvent, wrapper.props('item'));

        expect(wrapper.emitted('media-item-click')).toEqual([
            [
                {
                    originalDomEvent,
                    item: wrapper.props('item'),
                },
            ],
        ]);
    });

    it.each([
        'media-item-click',
        'media-item-selection-add',
        'media-item-selection-remove',
    ])('forwards %s from the base item', async (eventName) => {
        const wrapper = await createWrapper();
        const payload = { item: wrapper.props('item') };

        wrapper.findComponent({ name: 'ct-media-base-item' }).vm.$emit(eventName, payload);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted(eventName)).toEqual([[payload]]);
    });

    it('should update item with repository data after successful rename', async () => {
        global.activeAclRoles = ['media.editor'];

        const updatedItem = {
            id: 'media-id',
            fileName: 'new file name',
            url: 'https://example.com/new-file-name.png',
        };

        const getMock = jest.fn().mockResolvedValue(updatedItem);
        jest.spyOn(Contena.Service('repositoryFactory'), 'create').mockReturnValue({ get: getMock });

        const wrapper = await createWrapper();
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        const item = { id: 'media-id', isLoading: false };
        await wrapper.vm.onChangeName('new file name', item, () => {});

        expect(getMock).toHaveBeenCalledWith('media-id');
        expect(item.fileName).toBe('new file name');
        expect(item.url).toBe('https://example.com/new-file-name.png');
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'success',
                message: 'global.ct-media-media-item.notification.renamingSuccess.message',
            }),
        );
    });

    it('should throw error if new file name is too long', async () => {
        global.activeAclRoles = ['media.editor'];
        const error = {
            status: 400,
            code: 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG',
            meta: {
                parameters: {
                    length: 255,
                },
            },
        };

        const wrapper = await createWrapper({
            renameMedia: () =>
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                error,
                            ],
                        },
                    },
                }),
        });

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName(
            'new file name',
            {
                isLoading: false,
            },
            () => {},
        );

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-media-item.notification.fileNameTooLong.message',
            }),
        );
    });

    it('should throw general renaming error as fallback', async () => {
        global.activeAclRoles = ['media.editor'];
        const error = {
            status: 400,
            code: 'CONTENT__MEDIA_FILE_FOO_BAR',
        };

        const wrapper = await createWrapper({
            renameMedia: () =>
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                error,
                            ],
                        },
                    },
                }),
        });

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        await wrapper.vm.$nextTick();
        await wrapper.vm.onChangeName(
            'new file name',
            {
                isLoading: false,
            },
            () => {},
        );

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-media-item.notification.renamingError.message',
            }),
        );
    });

    it('onBlur doesnt update the entity if the value did not change', async () => {
        const renameMedia = jest.fn().mockResolvedValue();
        const wrapper = await createWrapper({ renameMedia });
        const item = {
            fileName: 'Test.png',
            hasFile: true,
            private: false,
        };
        const event = { target: { value: item.fileName } };

        wrapper.vm.onChangeName = jest.fn();

        wrapper.vm.onBlur(event, item, () => {});
        expect(wrapper.vm.onChangeName).not.toHaveBeenCalled();
    });

    it('change handler is called if the folder name has changed on blur', async () => {
        const renameMedia = jest.fn().mockResolvedValue();
        jest.spyOn(Contena.Service('repositoryFactory'), 'create').mockReturnValue({
            get: jest.fn().mockResolvedValue({ id: 'media-id', fileName: 'Test.png Test' }),
        });
        const wrapper = await createWrapper({ renameMedia });
        const item = {
            id: 'media-id',
            fileName: 'Test.png',
            hasFile: true,
            private: false,
        };
        const event = { target: { value: `${item.fileName} Test` } };

        wrapper.vm.onBlur(event, item, () => {});
        await flushPromises();

        expect(renameMedia).toHaveBeenCalledWith(item.id, 'Test.png Test');
    });

    it('onChangeName rejects invalid names', async () => {
        const wrapper = await createWrapper();
        const item = {
            fileName: 'Test.png',
            hasFile: true,
            private: false,
        };

        const endInlineEdit = jest.fn();

        const emptyName = { target: { value: '' } };
        wrapper.vm.onBlur(emptyName, item, endInlineEdit);
        expect(endInlineEdit).toHaveBeenCalled();
    });
});
