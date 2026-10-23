/**
 * @sw-package framework
 */
import 'src/app/mixin/admin_user-settings.mixin';
import { mount } from '@vue/test-utils';

let createRepositoryFactoryMock;

async function createWrapper() {
    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [Contena.Mixin.getByName('admin_user-settings')],
            data() {
                return {
                    name: 'sw-mock-field',
                };
            },
        },
        {
            attachTo: document.body,
            global: {
                mocks: {
                    repositoryFactory: {
                        create: () => createRepositoryFactoryMock,
                    },
                },
            },
        },
    );
}

describe('src/app/mixin/admin_user-settings.mixin.ts', () => {
    let wrapper;

    beforeEach(async () => {
        createRepositoryFactoryMock = undefined;
        global.activeAclRoles = ['admin_user_config:read', 'admin_user_config:create', 'admin_user_config:update'];
        Contena.Store.get('session').setCurrentUser({ id: 'current-admin_user-id' });
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.unmount();
        }

        await flushPromises();
    });

    it('should reject when acl rights are missing', async () => {
        global.activeAclRoles = [];

        await expect(wrapper.vm.getUserSettingsEntity('my-identifier', 'my-admin_user-id')).rejects.toEqual();
    });

    it('should receives the whole settings entity via identifier key', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() =>
                Promise.resolve([
                    {
                        id: 'my-admin_user-id',
                        key: 'my-identifier',
                    },
                ]),
            ),
        };

        const result = await wrapper.vm.getUserSettingsEntity('my-identifier', 'my-admin_user-id');

        expect(result).toEqual({
            id: 'my-admin_user-id',
            key: 'my-identifier',
        });

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'admin_userId',
                        type: 'equals',
                        value: 'my-admin_user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should return null via identifier key when no result was given', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() => Promise.resolve([])),
        };

        const result = await wrapper.vm.getUserSettingsEntity('my-identifier', 'my-admin_user-id');

        expect(result).toBeNull();

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'admin_userId',
                        type: 'equals',
                        value: 'my-admin_user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should return the admin_user settings value', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() =>
                Promise.resolve([
                    {
                        value: {
                            my: 'value',
                        },
                    },
                ]),
            ),
        };

        const result = await wrapper.vm.getUserSettings('my-identifier', 'my-admin_user-id');

        expect(result).toEqual({
            my: 'value',
        });

        expect(createRepositoryFactoryMock.search).toHaveBeenCalledWith(
            expect.objectContaining({
                filters: [
                    {
                        field: 'key',
                        type: 'equals',
                        value: 'my-identifier',
                    },
                    {
                        field: 'admin_userId',
                        type: 'equals',
                        value: 'my-admin_user-id',
                    },
                ],
            }),
            expect.anything(),
        );
    });

    it('should save current admin_user settings through the admin_user config service', async () => {
        const upsertMock = jest.spyOn(Contena.Service('admin_userConfigService'), 'upsert').mockResolvedValue();

        const result = await wrapper.vm.saveUserSettings('my-identifier', {
            entity: 'value',
        });

        expect(result).toBeUndefined();
        expect(upsertMock).toHaveBeenCalledWith({
            'custom.my-identifier': {
                entity: 'value',
            },
        });
    });

    it('should save settings for another admin_user through the admin_user config repository', async () => {
        createRepositoryFactoryMock = {
            search: jest.fn(() =>
                Promise.resolve([
                    {
                        value: {
                            my: 'value',
                        },
                    },
                ]),
            ),
            save: jest.fn(() =>
                Promise.resolve({
                    save: 'success',
                }),
            ),
        };

        const result = await wrapper.vm.saveUserSettings(
            'my-identifier',
            {
                entity: 'value',
            },
            'another-admin_user-id',
        );

        expect(result).toEqual({
            save: 'success',
        });

        expect(createRepositoryFactoryMock.save).toHaveBeenCalledWith(
            expect.objectContaining({
                key: 'custom.my-identifier',
                admin_userId: 'another-admin_user-id',
                value: {
                    entity: 'value',
                },
            }),
            expect.anything(),
        );
    });
});
