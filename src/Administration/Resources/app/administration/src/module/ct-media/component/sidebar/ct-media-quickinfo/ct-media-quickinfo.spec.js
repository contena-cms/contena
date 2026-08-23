/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
const itemMock = (options = {}) => {
    const itemOptions = {
        getEntityName: () => {
            return 'media';
        },
        id: '4a12jd3kki9yyy765gkn5hdb',
        fileName: 'demo.jpg',
        fileExtension: 'jpg',
        avatarUsers: [],
        categories: [],
        productManufacturers: [],
        productMedia: [],
        mailTemplateMedia: [],
        documentBaseConfigs: [],
        paymentMethods: [],
        shippingMethods: [],
        ...options,
    };

    return Object.assign(itemOptions, options);
};

const arPlacementOptions = [
    { id: 'horizontal', value: 'horizontal', label: 'Horizontal' },
    { id: 'vertical', value: 'vertical', label: 'Vertical' },
];
const originalCreateObjectURL = window.URL.createObjectURL;
const originalRevokeObjectURL = window.URL.revokeObjectURL;

async function createWrapper(itemMockOptions, mediaServiceFunctions = {}, mediaRepositoryProvideFunctions = {}) {
    return mount(await wrapTestComponent('ct-media-quickinfo', { sync: true }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        ...mediaRepositoryProvideFunctions,
                    }),
                },
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({
                            'core.store.media.defaultEnableAugmentedReality': 'false',
                        });
                    },
                    getConfig: () => {
                        return Promise.resolve([
                            {
                                elements: [
                                    {
                                        name: 'core.media.defaultARPlacement',
                                        config: {
                                            options: arPlacementOptions,
                                        },
                                    },
                                ],
                            },
                        ]);
                    },
                },
                mediaService: {
                    renameMedia: () => Promise.resolve(),
                    prepareDownloadMedia: jest.fn(),
                    ...mediaServiceFunctions,
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
            },
            stubs: {
                'mt-button': true,
                'ct-page': {
                    template: `
                        <div class="ct-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">CONTENT</slot>
                            <slot></slot>
                        </div>`,
                },

                'ct-media-collapse': {
                    template: `
                        <div class="ct-media-quickinfo">
                            <slot name="content"></slot>
                        </div>`,
                },
                'ct-media-quickinfo-metadata-item': true,
                'ct-media-preview-v2': true,
                'ct-modal': true,
                'ct-media-tag': true,
                'ct-custom-field-set-renderer': true,
                'ct-field-error': true,

                'ct-base-field': await wrapTestComponent('ct-base-field', {
                    sync: true,
                }),
                'ct-inherit-wrapper': await wrapTestComponent('ct-inherit-wrapper', { sync: true }),
                'ct-confirm-field': true,
                'ct-media-modal-replace': true,
                'ct-help-text': true,
                'ct-media-modal-delete': true,
                'ct-external-link': true,
                'ct-media-quickinfo-usage': true,
                'ct-media-modal-move': true,
                'ct-media-modal-v2': true,
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
            },
        },

        props: {
            item: itemMock(itemMockOptions),
            editable: true,
        },
    });
}

/**
 * @returns {[[object,boolean, boolean]]} [i][0] Array of options for the mockItem, [i][1] flag for if 'isSpatial', [i][2] flag for if 'isArReady'
 */
function provide2DMockOptions() {
    return [
        [
            {},
            false,
            false,
        ],
    ];
}

/**
 * @returns {[[object,boolean, boolean, string]]} [i][0] Array of options for the mockItem, [i][1] flag for if 'isSpatial', [i][2] flag for if 'isArReady', [i][3] flag for 'arPlacement'
 */
function provide3DMockOptions() {
    return [
        [
            {
                fileName: 'smth.glb',
                fileExtension: 'glb',
                mimeType: 'model/gltf-binary',
            },
            true,
            false,
            'horizontal',
        ],
        [
            {
                fileName: 'smth.glb',
                fileExtension: 'glb',
                mimeType: 'model/gltf-binary',
            },
            true,
            false,
            'vertical',
        ],
        [
            {
                fileName: 'smth.glb',
                mimeType: 'model/gltf-binary',
                url: 'http://contena.example.com/media/file/2b71335f118c4940b425c55352e69e44/media-1-three-d.glb',
            },
            true,
            true,
            'horizontal',
        ],
        [
            {
                fileName: 'smth.glb',
                mimeType: 'model/gltf-binary',
                url: 'http://contena.example.com/media/file/2b71335f118c4940b425c55352e69e44/media-1-three-d.glb',
            },
            true,
            true,
            'vertical',
        ],
    ];
}

describe('module/ct-media/components/ct-media-quickinfo', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    afterEach(() => {
        jest.restoreAllMocks();
        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: originalCreateObjectURL,
        });
        Object.defineProperty(window.URL, 'revokeObjectURL', {
            configurable: true,
            writable: true,
            value: originalRevokeObjectURL,
        });
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).toContain('ct-media-sidebar__quickaction--disabled');
    });

    it('should be able to delete', async () => {
        global.activeAclRoles = ['media.deleter'];

        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.quickaction--delete');
        expect(deleteMenuItem.classes()).not.toContain('ct-media-sidebar__quickaction--disabled');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).toContain('ct-media-sidebar__quickaction--disabled');
    });

    it('should be able to edit', async () => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.quickaction--move');
        expect(editMenuItem.classes()).not.toContain('ct-media-sidebar__quickaction--disabled');
    });

    it.each([
        {
            status: 500,
            code: 'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
        },
        {
            status: 500,
            code: 'CONTENT__MEDIA_EMPTY_FILE',
        },
    ])('should map error %p', async (error) => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper(
            {},
            {
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
            },
        );
        await flushPromises();

        await wrapper.vm.onChangeFileName('newFileName');

        expect(wrapper.vm.fileNameError).toStrictEqual(error);
    });

    it.each([
        ...provide2DMockOptions(),
        ...provide3DMockOptions(),
    ])('should display ar-ready toggle if item is a 3D file', async (mockOptions, isSpatial) => {
        global.activeAclRoles = ['media.editor'];

        const wrapper = await createWrapper(mockOptions);
        await flushPromises();

        expect(wrapper.find('.ct-media-sidebar__quickactions-switch.ar-ready-toggle').exists()).toBe(isSpatial);
    });

    it.each(provide3DMockOptions())(
        'should trigger update:item event when ar-toggle is changed',
        async (mockOptions, isSpatial) => {
            global.activeAclRoles = ['media.editor'];
            const mediaSaveMock = jest.fn();
            const mediaRepositoryFunctions = {
                save: mediaSaveMock,
            };

            const wrapper = await createWrapper(mockOptions, {}, mediaRepositoryFunctions);
            await flushPromises();

            const arToggle = wrapper.find('.ct-media-sidebar__quickactions-switch.ar-ready-toggle');
            expect(arToggle.exists()).toBe(isSpatial);

            const arToggleInput = wrapper.find('.mt-switch input');
            expect(arToggleInput.exists()).toBe(isSpatial);

            await arToggleInput.setChecked();
            expect(arToggleInput.element.checked).toBe(true);

            // await arToggle.trigger('change');
            expect(wrapper.emitted('update:item')).toBeTruthy();
            expect(wrapper.emitted('update:item')[0][0]).toEqual(
                expect.objectContaining({
                    config: {
                        spatial: {
                            arReady: true,
                            updatedAt: expect.any(Number),
                        },
                    },
                }),
            );
        },
    );

    it.each(provide3DMockOptions())(
        'should trigger update:item event when placement-singleselect is changed',
        async (mockOptions, isSpatial, isArReady, arPlacement) => {
            global.activeAclRoles = ['media.editor'];
            const mediaSaveMock = jest.fn();

            const mediaRepositoryGetMock = jest.fn().mockResolvedValue({
                config: {
                    spatial: {
                        arReady: isArReady,
                        arPlacement: arPlacement,
                    },
                },
            });

            const mediaRepositoryFunctions = {
                save: mediaSaveMock,
                get: mediaRepositoryGetMock,
            };

            const wrapper = await createWrapper(mockOptions, {}, mediaRepositoryFunctions);
            await flushPromises();

            const arToggle = wrapper.findComponent('.ct-media-sidebar__quickactions-switch.ar-ready-toggle');
            expect(arToggle.exists()).toBe(isSpatial);

            const arToggleInput = wrapper.find('.mt-switch input');
            expect(arToggleInput.exists()).toBe(true);

            expect(arToggleInput.element.checked).toBe(isArReady);

            const arPlacementSelect = wrapper.find('.mt-select input');
            expect(arPlacementSelect.exists()).toBe(isArReady);

            if (arPlacementSelect.exists()) {
                // click the input field to open results
                const selection = wrapper.find('.mt-select__selection');
                await selection.trigger('click');

                // find all results
                const selectResults = wrapper.findAll('.mt-select-result');
                // eslint-disable-next-line jest/no-conditional-expect
                expect(selectResults).toHaveLength(2);

                await selectResults.at(1).trigger('click');

                if (arPlacement === 'horizontal') {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(wrapper.emitted('update:item')).toBeTruthy();

                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(wrapper.emitted('update:item')[0][0]).toEqual(
                        // eslint-disable-next-line jest/no-conditional-expect
                        expect.objectContaining({
                            // eslint-disable-next-line jest/no-conditional-expect
                            config: expect.objectContaining({
                                // eslint-disable-next-line jest/no-conditional-expect
                                spatial: expect.objectContaining({
                                    arPlacement: 'vertical',
                                    // eslint-disable-next-line jest/no-conditional-expect
                                    updatedAt: expect.any(Number),
                                }),
                            }),
                        }),
                    );
                }
            }
        },
    );

    it.each(provide3DMockOptions())(
        'should check if object is AR ready when created and update ar toggle accordingly',
        async (mockOptions, isSpatial, isArReady, arPlacement) => {
            global.activeAclRoles = ['media.editor'];
            const mediaRepositoryGetMock = jest.fn().mockResolvedValue({
                config: {
                    spatial: {
                        arReady: isArReady,
                        arPlacement: arPlacement,
                    },
                },
            });
            const mediaRepositoryFunctions = {
                get: mediaRepositoryGetMock,
            };

            const wrapper = await createWrapper(mockOptions, {}, mediaRepositoryFunctions);
            await flushPromises();

            const arToggle = wrapper.findComponent('.ct-media-sidebar__quickactions-switch.ar-ready-toggle');
            expect(arToggle.exists()).toBe(true);

            const arToggleInput = wrapper.find('.mt-switch input');
            expect(arToggleInput.exists()).toBe(true);

            expect(arToggleInput.element.checked).toBe(isArReady);

            const arPlacementSelect = wrapper.find('.mt-select input');
            expect(arPlacementSelect.exists()).toBe(isArReady);
        },
    );

    it('shows cover actions only for playable video formats', async () => {
        global.activeAclRoles = ['media.editor'];

        const playableWrapper = await createWrapper({
            mimeType: 'video/mp4',
            mediaType: { name: 'VIDEO' },
        });
        await flushPromises();

        expect(playableWrapper.find('.quickaction--set-cover').exists()).toBe(true);

        const unsupportedWrapper = await createWrapper({
            mimeType: 'video/x-msvideo',
            mediaType: { name: 'VIDEO' },
        });
        await flushPromises();

        expect(unsupportedWrapper.find('.quickaction--set-cover').exists()).toBe(false);
    });

    it('should build augmented reality tooltip', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tooltip = wrapper.vm.buildAugmentedRealityTooltip('global.ct-media-media-item.tooltip.ar');
        expect(tooltip).toBe('global.ct-media-media-item.tooltip.ar');
    });

    it('should handle save error and show notification', async () => {
        const saveError = new Error('Save failed');
        const mediaSaveMock = jest.fn().mockRejectedValue(saveError);
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };

        const wrapper = await createWrapper({}, {}, mediaRepositoryFunctions);
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        await wrapper.vm.onSave();

        expect(mediaSaveMock).toHaveBeenCalledWith(wrapper.vm.item, expect.any(Object));
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: saveError.message }),
        );
    });

    it('should emit event bus message after save', async () => {
        const mediaSaveMock = jest.fn().mockResolvedValue();
        const mediaRepositoryFunctions = {
            save: mediaSaveMock,
        };
        const eventBusEmitSpy = jest.spyOn(Contena.Utils.EventBus, 'emit');

        const wrapper = await createWrapper({}, {}, mediaRepositoryFunctions);

        await wrapper.vm.onSave();

        expect(eventBusEmitSpy).toHaveBeenCalledWith('ct-media-library-item-updated', wrapper.vm.item.id);
    });

    it('should download private media with media service', async () => {
        const mediaBlob = new Blob(['media-content']);
        const prepareDownloadMediaMock = jest.fn().mockResolvedValue({ type: 'blob' });
        const downloadMediaMock = jest.fn().mockResolvedValue(mediaBlob);
        const objectUrl = 'blob:media-download';
        const createObjectURLMock = jest.fn().mockReturnValue(objectUrl);
        const revokeObjectURLMock = jest.fn();
        const originalCreateElement = document.createElement.bind(document);
        const link = document.createElement('a');
        const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tagName, options) => {
            if (tagName === 'a') {
                return link;
            }

            return originalCreateElement(tagName, options);
        });
        const dispatchEventSpy = jest.spyOn(link, 'dispatchEvent').mockImplementation(() => true);
        const removeSpy = jest.spyOn(link, 'remove').mockImplementation(() => {});

        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: createObjectURLMock,
        });
        Object.defineProperty(window.URL, 'revokeObjectURL', {
            configurable: true,
            writable: true,
            value: revokeObjectURLMock,
        });

        const wrapper = await createWrapper(
            {
                hasFile: true,
                private: true,
                fileName: 'private-media',
                fileExtension: 'jpg',
            },
            {
                prepareDownloadMedia: prepareDownloadMediaMock,
                downloadMedia: downloadMediaMock,
            },
        );

        const downloadAction = wrapper.find('.quickaction--download');

        expect(downloadAction.find('ct-external-link-stub').exists()).toBe(false);

        await downloadAction.trigger('click');
        await flushPromises();

        expect(prepareDownloadMediaMock).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(downloadMediaMock).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(createObjectURLMock).toHaveBeenCalledWith(mediaBlob);
        expect(createElementSpy).toHaveBeenCalledWith('a');
        expect(link.href).toBe(objectUrl);
        expect(link.download).toBe('private-media.jpg');
        expect(dispatchEventSpy).toHaveBeenCalledWith(expect.any(MouseEvent));
        expect(removeSpy).toHaveBeenCalled();
        expect(revokeObjectURLMock).toHaveBeenCalledWith(objectUrl);
    });

    it('should directly trigger external media downloads', async () => {
        const prepareDownloadMediaMock = jest.fn().mockResolvedValue({
            type: 'external',
            url: 'https://cdn.example.test/download',
        });
        const downloadMediaMock = jest.fn();
        const createObjectURLMock = jest.fn();
        const originalCreateElement = document.createElement.bind(document);
        const link = document.createElement('a');
        const createElementSpy = jest.spyOn(document, 'createElement').mockImplementation((tagName, options) => {
            if (tagName === 'a') {
                return link;
            }

            return originalCreateElement(tagName, options);
        });
        const dispatchEventSpy = jest.spyOn(link, 'dispatchEvent').mockImplementation(() => true);
        const removeSpy = jest.spyOn(link, 'remove').mockImplementation(() => {});

        Object.defineProperty(window.URL, 'createObjectURL', {
            configurable: true,
            writable: true,
            value: createObjectURLMock,
        });

        const wrapper = await createWrapper(
            {
                hasFile: true,
                private: true,
                fileName: 'private-media',
                fileExtension: 'jpg',
            },
            {
                prepareDownloadMedia: prepareDownloadMediaMock,
                downloadMedia: downloadMediaMock,
            },
        );

        const downloadAction = wrapper.find('.quickaction--download');
        await downloadAction.trigger('click');
        await flushPromises();

        expect(prepareDownloadMediaMock).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(downloadMediaMock).not.toHaveBeenCalled();
        expect(createObjectURLMock).not.toHaveBeenCalled();
        expect(createElementSpy).toHaveBeenCalledWith('a');
        expect(link.href).toBe('https://cdn.example.test/download');
        expect(link.download).toBe('');
        expect(link.target).toBe('_blank');
        expect(link.rel).toBe('noopener noreferrer');
        expect(dispatchEventSpy).toHaveBeenCalledWith(expect.any(MouseEvent));
        expect(removeSpy).toHaveBeenCalled();
    });

    it('should show notification when private media download fails', async () => {
        const prepareDownloadMediaMock = jest.fn().mockResolvedValue({ type: 'blob' });
        const downloadMediaMock = jest.fn().mockRejectedValue(new Error('Download failed'));
        const wrapper = await createWrapper(
            {
                hasFile: true,
                private: true,
            },
            {
                prepareDownloadMedia: prepareDownloadMediaMock,
                downloadMedia: downloadMediaMock,
            },
        );
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        await wrapper.vm.downloadMedia();
        await flushPromises();

        expect(prepareDownloadMediaMock).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(downloadMediaMock).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-media-item.notification.downloadError.message',
            }),
        );
    });

    it('should return the file name without an extension when none exists', async () => {
        const wrapper = await createWrapper({
            fileName: 'private-media',
            fileExtension: null,
        });

        expect(wrapper.vm.fileName).toBe('private-media');
    });

    it('should render external download link for public media', async () => {
        const wrapper = await createWrapper({
            hasFile: true,
            private: false,
            url: 'https://example.com/media/public.jpg',
        });
        await flushPromises();

        const externalDownloadLink = wrapper.find('.quickaction--download ct-external-link-stub');

        expect(externalDownloadLink.exists()).toBe(true);
        expect(externalDownloadLink.attributes('href')).toBe('https://example.com/media/public.jpg');
        expect(externalDownloadLink.attributes('download')).toBeDefined();
    });

    it.each([
        { mimeType: 'video/quicktime', shouldShowWarning: true },
        { mimeType: 'video/mp4', shouldShowWarning: false },
    ])(
        'should show warning banner if video format is not supported (type: $mimeType, shouldShowWarning: $shouldShowWarning)',
        async ({ mimeType, shouldShowWarning }) => {
            const wrapper = await createWrapper({ mimeType, hasFile: true });
            await flushPromises();

            const banner = wrapper.find('.ct-media-quickinfo__unsupported-format-banner');
            expect(banner.exists()).toBe(shouldShowWarning);
        },
    );

    it('uses the generic media preview for 3D model files', async () => {
        const wrapper = await createWrapper({
            mimeType: 'model/gltf-binary',
            hasFile: true,
            fileExtension: 'glb',
            fileName: 'test.glb',
        });
        await flushPromises();

        expect(wrapper.find('ct-media-preview-v2-stub').exists()).toBe(true);
    });
});
