import { mount } from '@vue/test-utils';

const { Module } = Contena;

// mocking modules
const modulesToCreate = new Map();
modulesToCreate.set('ct-user-media', {
    icon: 'regular-user',
    entity: 'user',
});
modulesToCreate.set('ct-plugin-media', {
    icon: 'regular-cog',
    entity: 'plugin',
});

Array.from(modulesToCreate.keys()).forEach((moduleName) => {
    const currentModuleValues = modulesToCreate.get(moduleName);

    Module.register(moduleName, {
        icon: currentModuleValues.icon,
        entity: currentModuleValues.entity,
        routes: {
            index: {
                components: {},
                path: 'index',
            },
        },
    });
});

const ID_PLUGIN_FOLDER = '4006d6aa64ce409692ac2b952fa56ade';
const ID_USER_FOLDER = '0e6b005ca7a1440b8e87ac3d45ed5c9f';

async function createWrapper(defaultFolderId, privileges = []) {
    const repositoryFactoryMock = {
        save: jest.fn(() => Promise.resolve()),
        create: () =>
            Promise.resolve({
                isNew: () => true,
            }),
        search: () =>
            Promise.resolve({
                isNew: () => false,
            }),
        get: (folderId) => {
            switch (folderId) {
                case ID_USER_FOLDER:
                    return {
                        entity: 'user',
                        isNew: () => false,
                    };
                case ID_PLUGIN_FOLDER:
                    return {
                        entity: 'plugin',
                        isNew: () => false,
                    };
                default:
                    return null;
            }
        },
    };

    return mount(await wrapTestComponent('ct-media-folder-item', { sync: true }), {
        props: {
            item: {
                useParentConfiguration: false,
                configurationId: 'a73ef286f6c748deacdbdfd5aab3cca7',
                defaultFolderId: defaultFolderId,
                parentId: null,
                childCount: 0,
                name: 'Cms Page Media',
                customFields: null,
                createdAt: '2020-06-03T09:44:51+00:00',
                updatedAt: null,
                id: 'af46d5250e34403485e045ba7049dec7',
                children: [],
                isNew: () => false,
                media: [
                    {
                        isNew: () => false,
                    },
                ],
            },
            showSelectionIndicator: false,
            showContextMenuButton: true,
            selected: false,
            isList: true,
        },
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
                    create: () => repositoryFactoryMock,
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'ct-media-base-item': {
                    props: {
                        allowMultiSelect: {
                            type: Boolean,
                            required: false,
                            default: true,
                        },
                    },
                    // Hack with AllowMultiSelect is needed because the property
                    // can't be accessed in the test utils correctly
                    template: `
                    <div class="ct-media-base-item">
                        AllowMultiSelect: "{{ allowMultiSelect }}"
                        <slot name="context-menu" v-bind="{ startInlineEdit: () => {}}"></slot>
                        <slot></slot>
                    </div>`,
                },
                'ct-context-button': {
                    template: '<div class="ct-context-button"><slot></slot></div>',
                },
                'ct-context-menu-item': {
                    template: '<div class="ct-context-menu-item"><slot></slot></div>',
                },
                'ct-context-menu': {
                    template: '<div><slot></slot></div>',
                },
                'ct-text-field': true,
                'ct-media-modal-folder-settings': true,
                'ct-media-modal-folder-dissolve': true,
                'ct-media-modal-move': true,
                'ct-media-modal-delete': true,
                'ct-time-ago': true,
            },
        },
    });
}

describe('components/media/ct-media-folder-item', () => {
    it('should provide the fallback folder color for a user folder', async () => {
        const wrapper = await createWrapper(ID_USER_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail');
    });

    it('should provide the system folder color for a plugin folder', async () => {
        const wrapper = await createWrapper(ID_PLUGIN_FOLDER);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail--grey');
    });

    it('should provide fallback folder color', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.iconName).toBe('multicolor-folder-thumbnail');
    });

    it('should not be able to delete', async () => {
        const aclWrapper = await createWrapper();
        await aclWrapper.vm.$nextTick();

        const deleteMenuItem = aclWrapper.find('.ct-media-context-item__delete-folder-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const aclWrapper = await createWrapper(null, [
            'media.deleter',
        ]);
        await aclWrapper.vm.$nextTick();

        const deleteMenuItem = aclWrapper.find('.ct-media-context-item__delete-folder-action');
        expect(deleteMenuItem.attributes().disabled).toBeDefined();
    });

    it('should not be able to edit', async () => {
        const aclWrapper = await createWrapper();
        await aclWrapper.vm.$nextTick();

        const editMenuItem = aclWrapper.find('.ct-media-context-item__move-folder-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const aclWrapper = await createWrapper(null, [
            'media.editor',
        ]);
        await aclWrapper.vm.$nextTick();

        const editMenuItem = aclWrapper.find('.ct-media-context-item__move-folder-action');
        expect(editMenuItem.attributes().disabled).toBeDefined();
    });

    it('should show the icon when it is not parent', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isParent: false,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('AllowMultiSelect: "true"');
    });

    it('should not show the icon on back folder', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isParent: true,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('AllowMultiSelect: "false"');
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('onBlur doesnt update the entity if the value did not change', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;
        const event = { target: { value: item.name } };
        const saveSpy = jest.spyOn(wrapper.vm.mediaFolderRepository, 'save');

        wrapper.vm.onBlur(event, item, () => {});
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('change handler is called if the folder name has changed on blur', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;
        const event = { target: { value: `${item.name} Test` } };
        const saveSpy = jest.spyOn(wrapper.vm.mediaFolderRepository, 'save');

        wrapper.vm.onBlur(event, item, () => {});
        await flushPromises();

        expect(saveSpy).toHaveBeenCalledWith(item, expect.any(Object));
        expect(wrapper.emitted('media-folder-changed')).toHaveLength(1);
    });

    it('onChangeName rejects invalid names', async () => {
        const wrapper = await createWrapper();
        const item = wrapper.vm.mediaFolder;
        const endInlineEdit = jest.fn();
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        const emptyName = { target: { value: '' } };
        wrapper.vm.onBlur(emptyName, item, endInlineEdit);

        const invalidName = { target: { value: 'Test <' } };
        wrapper.vm.onBlur(invalidName, item, endInlineEdit);

        expect(endInlineEdit).toHaveBeenCalledTimes(2);
        expect(notificationSpy).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-folder-item.notification.errorBlankItemName.message',
            }),
        );
        expect(notificationSpy).toHaveBeenNthCalledWith(
            2,
            expect.objectContaining({
                variant: 'error',
                message: 'global.ct-media-folder-item.notification.errorInvalidItemName.message',
            }),
        );
    });

    it('should not call the api get default folder if default folder id does not exist', async () => {
        const wrapper = await createWrapper(null);
        await wrapper.vm.$nextTick();

        const mediaDefaultFolderRepositorySpy = jest.spyOn(wrapper.vm.mediaDefaultFolderRepository, 'get');
        await wrapper.vm.getIconConfigFromFolder();

        expect(mediaDefaultFolderRepositorySpy).toHaveBeenCalledTimes(0);
    });

    it('should call the api get default folder if default folder id exists', async () => {
        const wrapper = await createWrapper(ID_USER_FOLDER);
        Object.assign(wrapper.vm, {
            lastDefaultFolderId: '',
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.mediaDefaultFolderRepository.get = jest.fn(() => Promise.resolve({}));
        wrapper.vm.moduleFactory.getModuleByEntityName = jest.fn(() => Promise.resolve({}));

        await wrapper.vm.getIconConfigFromFolder();
        await flushPromises();

        expect(wrapper.vm.mediaDefaultFolderRepository.get).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.moduleFactory.getModuleByEntityName).toHaveBeenCalledTimes(1);
    });
});
