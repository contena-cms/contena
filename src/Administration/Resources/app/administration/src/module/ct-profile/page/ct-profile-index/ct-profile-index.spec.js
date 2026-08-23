import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import TimezoneService from 'src/core/service/timezone.service';

async function createWrapper(
    privileges = [],
    saveFunction = () => Promise.resolve({}),
    loginService = { loginByUsername: () => Promise.resolve({}), logout: () => {} },
    routeName = 'ct.profile.index.general',
) {
    return mount(await wrapTestComponent('ct-profile-index', { sync: true }), {
        global: {
            stubs: {
                'ct-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>
                            `,
                },
                'ct-card-view': {
                    template: `<div class="ct-card-view"><slot></slot></div>`,
                },
                'router-view': {
                    template: `<div><slot></slot></div>`,
                },
                'ct-search-bar': true,
                'ct-notification-center': true,
                'ct-language-switch': true,
                'ct-button-process': true,
                'ct-language-info': true,
                'mt-tabs': true,
                'ct-skeleton': true,
                'ct-media-modal-v2': true,
            },
            provide: {
                [routeLocationKey]: {
                    fullPath: '/profile',
                    name: routeName,
                    params: {},
                },
                [routerKey]: {
                    push: jest.fn(),
                },
                acl: {
                    can: (key) => {
                        if (!key) {
                            return true;
                        }

                        return privileges.includes(key);
                    },
                },
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'media') {
                            return {
                                get: () => Promise.resolve({ id: '2142' }),
                            };
                        }

                        return {
                            get: () =>
                                Promise.resolve({
                                    id: '87923',
                                    localeId: '1337',
                                    email: 'foo@bar.baz',
                                }),
                            search: () => Promise.resolve(new EntityCollection('', '', Contena.Context.api, null, [], 0)),
                            getSyncChangeset: () => ({
                                changeset: [{ changes: { id: '1337' } }],
                            }),
                            save: () => Promise.resolve(),
                        };
                    },
                },
                loginService,
                userService: {
                    getUser: () => Promise.resolve({ data: { id: '87923' } }),
                    updateUser: saveFunction,
                },
                mediaDefaultFolderService: {},
                searchPreferencesService: {
                    getDefaultSearchPreferences: () => {},
                    getUserSearchPreferences: () => {},
                    createUserSearchPreferences: () => {
                        return {
                            key: 'search.preferences',
                            userId: 'userId',
                        };
                    },
                    processSearchPreferencesFields: (fields) => fields,
                },
                searchRankingService: {
                    clearCacheUserSearchConfiguration: () => {},
                    saveMinSearchTermLength: () => Promise.resolve(),
                    isValidTerm: (term) => {
                        return term && term.trim().length >= 1;
                    },
                },
                userConfigService: {
                    upsert: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve();
                    },
                },
                validationApiService: {
                    validateEmailAddress: () => {
                        return Promise.resolve(true);
                    },
                },
            },
        },
    });
}

describe('src/module/ct-profile/page/ct-profile-index', () => {
    beforeAll(() => {
        Contena.Service().register('timezoneService', () => {
            return new TimezoneService();
        });

        Contena.Service().register('localeHelper', () => {
            return {
                setLocaleWithId: jest.fn(),
            };
        });
    });

    it('should not be able to save own user', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        Object.assign(wrapper.vm, {
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-profile__save-action');

        expect(saveButton.attributes().isLoading).toBeFalsy();
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save own user', async () => {
        const wrapper = await createWrapper([
            'user.update_profile',
        ]);
        await flushPromises();

        Object.assign(wrapper.vm, {
            isLoading: false,
            isUserLoading: false,
        });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-profile__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to change new password', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onChangeNewPassword('Contena');

        expect(wrapper.vm.newPassword).toBe('Contena');
    });

    it('should be able to change new password confirm', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onChangeNewPasswordConfirm('Contena');

        expect(wrapper.vm.newPasswordConfirm).toBe('Contena');
    });

    it('should reset general data if route changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const getUserSpy = jest.spyOn(wrapper.vm.userService, 'getUser');
        getUserSpy.mockClear();

        wrapper.vm.resetGeneralData();
        await flushPromises();

        expect(wrapper.vm.newPassword).toBeNull();
        expect(wrapper.vm.newPasswordConfirm).toBeNull();

        expect(getUserSpy).toHaveBeenCalledTimes(1);
    });

    it('should handle user-save errors correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        Object.assign(wrapper.vm, {
            isLoading: true,
            $route: {
                name: 'ct.profile.index.general',
            },
        });
        await wrapper.vm.$nextTick();
        wrapper.vm.handleUserSaveError();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'ct-profile.index.notificationSaveErrorMessage',
            }),
        );
    });

    it('should save the user without asking for the current password', async () => {
        const updateFunction = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper([], updateFunction);
        await flushPromises();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(updateFunction).toHaveBeenCalled();
    });

    it('should handle avatarId and load the media', async () => {
        const wrapper = await createWrapper();
        const mediaId = '2142';

        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        wrapper.vm.setMediaItem({ targetId: mediaId });
        await flushPromises();

        expect(wrapper.vm.user.avatarId).toBe(mediaId);
        expect(wrapper.vm.avatarMediaItem.id).toBe(mediaId);
    });

    it('should save from the profile action directly', async () => {
        const updateFunction = jest.fn(() => Promise.resolve({}));
        const wrapper = await createWrapper(['user.update_profile'], updateFunction);
        await flushPromises();

        const saveButton = wrapper.find('.ct-profile__save-action');
        await saveButton.trigger('click');
        await flushPromises();

        expect(updateFunction).toHaveBeenCalled();
    });

    it('should save minSearchTermLength and userSearchPreferences', async () => {
        const wrapper = await createWrapper(
            [],
            () => Promise.resolve({}),
            { loginByUsername: () => Promise.resolve({}), logout: () => {} },
            'ct.profile.index.searchPreferences',
        );
        await flushPromises();
        Contena.Store.get('swProfile').searchPreferences = [];
        const minLengthSpy = jest.spyOn(wrapper.vm.searchRankingService, 'saveMinSearchTermLength');
        const preferencesSpy = jest.spyOn(wrapper.vm.userConfigService, 'upsert');

        await wrapper.vm.onSave();
        await flushPromises();

        expect(minLengthSpy).toHaveBeenCalledTimes(1);
        expect(preferencesSpy).toHaveBeenCalledTimes(1);
    });

    it('should re-login before updateCurrentUser when password changes (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };

        const wrapper = await createWrapper(['user:editor'], () => Promise.resolve({}), loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        const getUserSpy = jest.spyOn(wrapper.vm.userService, 'getUser');
        getUserSpy.mockClear();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(getUserSpy).toHaveBeenCalled();
        expect(loginByUsername.mock.invocationCallOrder[0]).toBeLessThan(getUserSpy.mock.invocationCallOrder[0]);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should NOT re-login when no password change (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };

        const wrapper = await createWrapper(['user:editor'], () => Promise.resolve({}), loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: null,
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should re-login before updateCurrentUser when password changes (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], updateUser, loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        const getUserSpy = jest.spyOn(wrapper.vm.userService, 'getUser');
        getUserSpy.mockClear();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(getUserSpy).toHaveBeenCalled();
        expect(loginByUsername.mock.invocationCallOrder[0]).toBeLessThan(getUserSpy.mock.invocationCallOrder[0]);
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should NOT re-login when no password change (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], updateUser, loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: null,
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should show an error and not succeed when save fails (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.resolve({}));
        const loginService = { loginByUsername, logout: jest.fn() };
        const updateUser = jest.fn(() => Promise.reject(new Error('Save failed')));

        const wrapper = await createWrapper([], updateUser, loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).not.toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
    });

    it('should log out and not show a save error when re-login fails after password change (user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.reject(new Error('Network error')));
        const logout = jest.fn();
        const loginService = { loginByUsername, logout };

        const wrapper = await createWrapper(['user:editor'], () => Promise.resolve({}), loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});
        wrapper.vm.handleUserSaveError = jest.fn();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(logout).toHaveBeenCalled();
        expect(wrapper.vm.handleUserSaveError).not.toHaveBeenCalled();
    });

    it('should log out and not show a save error when re-login fails after password change (non-user:editor path)', async () => {
        const loginByUsername = jest.fn(() => Promise.reject(new Error('Network error')));
        const logout = jest.fn();
        const loginService = { loginByUsername, logout };
        const updateUser = jest.fn(() => Promise.resolve({}));

        const wrapper = await createWrapper([], updateUser, loginService);
        await flushPromises();

        Object.assign(wrapper.vm, {
            newPassword: 'NewPassword123',
            newPasswordConfirm: 'NewPassword123',
            user: {
                id: '87923',
                username: 'admin',
                localeId: '1337',
                email: 'foo@bar.baz',
            },
        });
        await wrapper.vm.$nextTick();

        wrapper.vm.updateCurrentUser = jest.fn(async () => {});
        wrapper.vm.createNotificationError = jest.fn();

        wrapper.vm.saveUser({});
        await flushPromises();

        expect(loginByUsername).toHaveBeenCalledWith('admin', 'NewPassword123');
        expect(logout).toHaveBeenCalled();
        expect(wrapper.vm.createNotificationError).not.toHaveBeenCalled();
    });
});
