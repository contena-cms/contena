/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import TimezoneService from 'src/core/service/timezone.service';
import EntityCollection from 'src/core/data/entity-collection.data';
import CtUsersUserDetailBase from '../../view/ct-users-user-detail-base.vue';
import CtUsersUserDetailInterface from '../../view/ct-users-user-detail-interface.vue';
import CtUsersUserDetailRoles from '../../view/ct-users-user-detail-roles.vue';
import CtUsersUserDetailIntegrations from '../../view/ct-users-user-detail-integrations.vue';

let wrapper;
let routerPush;

const mockedLoginService = {
    loginByUsername: jest.fn(() => Promise.resolve()),
};
const createTagCollection = (tags = []) => new EntityCollection('', 'tag', Contena.Context.api, null, tags, tags.length);
const createPositionCollection = (positions = []) =>
    new EntityCollection('', 'position', Contena.Context.api, null, positions, positions.length);

async function createWrapper(
    privileges = [],
    options = {
        global: {
            stubs: {},
        },
    },
) {
    routerPush = jest.fn();
    wrapper = mount(
        await wrapTestComponent('ct-users-user-detail', {
            sync: true,
        }),
        {
            props: {
                initialUserId: '1a2b3c4d',
            },
            global: {
                directives: {
                    tooltip: {
                        beforeMount(el, binding) {
                            el.setAttribute('data-tooltip-message', binding.value.message);
                            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                        },
                        mounted(el, binding) {
                            el.setAttribute('data-tooltip-message', binding.value.message);
                            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                        },
                        updated(el, binding) {
                            el.setAttribute('data-tooltip-message', binding.value.message);
                            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                        },
                    },
                },
                renderStubDefaultSlot: true,
                provide: {
                    [routeLocationKey]: {
                        meta: { $module: { icon: 'regular-content' } },
                        params: { id: '1a2b3c4d' },
                    },
                    [routerKey]: { push: routerPush },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    loginService: mockedLoginService,
                    userService: {
                        getUser: () => Promise.resolve({ data: {} }),
                    },
                    mediaDefaultFolderService: {
                        getDefaultFolderId: () => Promise.resolve('1234'),
                    },
                    userValidationService: {
                        checkUserEmail: () => Promise.resolve({ emailIsUnique: true }),
                    },
                    integrationService: {},
                    repositoryFactory: {
                        create: (entityName) => {
                            if (entityName === 'user') {
                                return {
                                    search: () => Promise.resolve(),
                                    get: () => {
                                        return Promise.resolve({
                                            active: true,
                                            localeId: '7dc07b43229843d387bb5f59233c2d66',
                                            username: 'admin',
                                            name: 'admin',
                                            phoneNumber: '',
                                            email: 'info@contena.cn',
                                            tags: createTagCollection(),
                                            positions: createPositionCollection(),
                                            accessKeys: {
                                                entity: 'media',
                                            },
                                        });
                                    },
                                    save: () => Promise.resolve(),
                                };
                            }

                            if (entityName === 'language') {
                                return {
                                    search: () =>
                                        Promise.resolve(new EntityCollection('', '', Contena.Context.api, null, [], 0)),
                                    get: () => Promise.resolve(),
                                };
                            }

                            if (entityName === 'media') {
                                return {
                                    get: () =>
                                        Promise.resolve({
                                            id: '2142',
                                        }),
                                };
                            }

                            return {};
                        },
                    },
                    validationService: {},
                },
                mocks: {
                    $route: {
                        params: {
                            id: '1a2b3c4d',
                        },
                        meta: {
                            $module: {
                                icon: 'regular-content',
                            },
                        },
                    },
                    $device: {
                        getSystemKey: () => 'STRG',
                    },
                },
                stubs: {
                    'ct-page': {
                        template: `
<div>
    <slot name="smart-bar-actions"></slot>
    <slot name="content"></slot>
</div>`,
                    },
                    'mt-tabs': {
                        props: [
                            'items',
                            'defaultItem',
                        ],
                        template: `
                            <div class="mt-tabs-stub">
                                <button
                                    v-for="item in items"
                                    :key="item.name"
                                    type="button"
                                    @click="item.onClick()"
                                >
                                    {{ item.label }}
                                </button>
                            </div>
                        `,
                    },
                    'ct-card-view': true,
                    'router-view': {
                        components: {
                            CtUsersUserDetailBase,
                            CtUsersUserDetailInterface,
                            CtUsersUserDetailRoles,
                            CtUsersUserDetailIntegrations,
                        },
                        template: `
                            <CtUsersUserDetailBase />
                            <CtUsersUserDetailInterface />
                            <CtUsersUserDetailRoles />
                            <CtUsersUserDetailIntegrations />
                        `,
                    },
                    'mt-card': {
                        template: `
    <div class="ct-card-stub">
        <slot></slot>
        <slot name="grid"></slot>
    </div>
    `,
                    },
                    'ct-button-process': await wrapTestComponent('ct-button-process'),
                    'ct-contextual-field': await wrapTestComponent('ct-contextual-field'),
                    'ct-block-field': await wrapTestComponent('ct-block-field'),
                    'ct-base-field': await wrapTestComponent('ct-base-field'),
                    'ct-field-error': await wrapTestComponent('ct-field-error'),
                    'ct-upload-listener': true,
                    'ct-media-upload-v2': true,
                    'ct-select-field': true,

                    'ct-entity-multi-select': true,
                    'ct-entity-tag-select': {
                        props: [
                            'entityCollection',
                            'disabled',
                        ],
                        emits: ['update:entityCollection'],
                        template: '<div />',
                    },
                    'mt-entity-select': true,
                    'mt-select': {
                        props: [
                            'modelValue',
                            'options',
                            'disabled',
                        ],
                        template: '<div />',
                    },
                    'mt-switch': {
                        props: [
                            'modelValue',
                            'disabled',
                        ],
                        template: '<div />',
                    },
                    'ct-data-grid': {
                        props: ['dataSource'],
                        template: `
                        <div>
                            <template v-for="item in dataSource">
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>
                    `,
                    },
                    'mt-data-table': {
                        props: [
                            'dataSource',
                            'disableDelete',
                            'additionalContextButtons',
                            'layout',
                        ],
                        template: `
                        <div
                            class="mt-data-table-stub"
                            :data-disable-delete="disableDelete"
                            :data-layout="layout || 'default'"
                        >
                            <template v-for="item in dataSource">
                                <slot name="column-accessKey" v-bind="{ data: item }"></slot>
                                <slot name="context-select" v-bind="{ data: item }"></slot>
                            </template>
                            <slot name="toolbar"></slot>
                            <slot name="empty-state"></slot>
                        </div>
                    `,
                    },
                    'ct-context-menu-item': true,
                    'ct-skeleton': true,
                    'ct-loader': true,
                    'ct-media-modal-v2': true,

                    'ct-help-text': true,
                    'ct-inheritance-switch': true,
                    'ct-field-copyable': true,
                    'ct-ai-copilot-badge': true,
                    ...options.global.stubs,
                },
            },
        },
    );

    // wait until all loading promises are done
    await wrapper.vm.$nextTick();

    return wrapper;
}

describe('modules/ct-users/page/ct-users-user-detail', () => {
    beforeAll(() => {
        Contena.Service().register('timezoneService', () => {
            return new TimezoneService();
        });
    });

    beforeEach(async () => {
        Contena.Store.get('session').languageId = '123456789';
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        await wrapper.unmount();
        Contena.Store.get('session').languageId = '';
    });

    it('should contain all fields', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();
        const fieldName = wrapper.findComponent('.ct-users-user-detail__grid-name');
        const fieldPhoneNumber = wrapper.findComponent('.ct-users-user-detail__grid-phoneNumber');
        const fieldEmail = wrapper.findComponent('.ct-users-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.ct-users-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.ct-users-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findComponent('.ct-users-user-detail__grid-password');
        const fieldLanguage = wrapper.findComponent('.ct-users-user-detail__grid-language');
        const fieldActive = wrapper.findComponent('.ct-users-user-detail__grid-active');
        const fieldTags = wrapper.findComponent('.ct-users-user-detail__tags');

        expect(fieldName.exists()).toBeTruthy();
        expect(fieldPhoneNumber.exists()).toBeTruthy();
        expect(fieldEmail.exists()).toBeTruthy();
        expect(fieldUsername.exists()).toBeTruthy();
        expect(fieldProfilePicture.exists()).toBeTruthy();
        expect(fieldPassword.exists()).toBeTruthy();
        expect(fieldLanguage.exists()).toBeTruthy();
        expect(fieldActive.exists()).toBeTruthy();
        expect(fieldTags.exists()).toBeTruthy();

        expect(fieldName.props('modelValue')).toBe('admin');
        expect(fieldPhoneNumber.props('modelValue')).toBe('');
        expect(fieldEmail.props('modelValue')).toBe('info@contena.cn');
        expect(fieldUsername.props('modelValue')).toBe('admin');
        expect(fieldProfilePicture.attributes('value')).toBeUndefined();
        expect(fieldPassword.attributes('value')).toBeUndefined();
        expect(fieldLanguage.props('modelValue')).toBe('7dc07b43229843d387bb5f59233c2d66');
        expect(fieldActive.props('modelValue')).toBe(true);
        expect(fieldTags.props('entityCollection')).toBe(wrapper.vm.user.tags);
    });

    it('switches the visible detail tab', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();

        const tabs = wrapper.findAll('.mt-tabs-stub button');
        await tabs[1].trigger('click');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.users.detail.interface',
            params: { id: '1a2b3c4d' },
        });
    });

    it('loads the tag association', () => {
        expect(wrapper.vm.userCriteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'tags' }),
            ]),
        );
    });

    it('keeps tag selection in the user association collection', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();

        const selectedTags = createTagCollection([{ id: 'tag-id', name: 'Internal' }]);
        const fieldTags = wrapper.findComponent('.ct-users-user-detail__tags');
        fieldTags.vm.$emit('update:entityCollection', selectedTags);
        await wrapper.vm.$nextTick();

        expect(Array.from(wrapper.vm.user.tags.getIds())).toEqual(['tag-id']);
    });

    it('should contain all fields with a given user', async () => {
        Object.assign(wrapper.vm, {
            user: {
                active: false,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                phoneNumber: '13800138000',
                email: 'max@mustermann.com',
            },
            isLoading: false,
        });
        await wrapper.vm.$nextTick();
        await flushPromises();

        const fieldName = wrapper.findComponent('.ct-users-user-detail__grid-name');
        const fieldPhoneNumber = wrapper.findComponent('.ct-users-user-detail__grid-phoneNumber');
        const fieldEmail = wrapper.findComponent('.ct-users-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.ct-users-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.ct-users-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findComponent('.ct-users-user-detail__grid-password');
        const fieldLanguage = wrapper.findComponent('.ct-users-user-detail__grid-language');
        const fieldActive = wrapper.findComponent('.ct-users-user-detail__grid-active');

        expect(fieldName.exists()).toBeTruthy();
        expect(fieldPhoneNumber.exists()).toBeTruthy();
        expect(fieldEmail.exists()).toBeTruthy();
        expect(fieldUsername.exists()).toBeTruthy();
        expect(fieldProfilePicture.exists()).toBeTruthy();
        expect(fieldPassword.exists()).toBeTruthy();
        expect(fieldLanguage.exists()).toBeTruthy();
        expect(fieldActive.exists()).toBeTruthy();

        expect(fieldName.props('modelValue')).toBe('Max Mustermann');
        expect(fieldPhoneNumber.props('modelValue')).toBe('13800138000');
        expect(fieldEmail.props('modelValue')).toBe('max@mustermann.com');
        expect(fieldUsername.props('modelValue')).toBe('maxmuster');
        expect(fieldProfilePicture.attributes('value')).toBeUndefined();
        expect(fieldPassword.attributes('value')).toBeUndefined();
        expect(fieldLanguage.props('modelValue')).toBe('12345');
        expect(fieldActive.props('modelValue')).toBe(false);
    });

    it('should use the display name', async () => {
        Object.assign(wrapper.vm, {
            user: {
                name: 'Max Mustermann',
                username: 'maxmuster',
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.fullName).toBe('Max Mustermann');

        Object.assign(wrapper.vm, {
            user: {
                name: '',
                username: 'maxmuster',
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.fullName).toBe('maxmuster');
    });

    it('should enable the tooltip warning when user is admin', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        Object.assign(wrapper.vm, {
            user: {
                admin: true,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                email: 'max@mustermann.com',
            },
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        const aclRolesSelect = wrapper.find('.ct-users-user-detail__grid-aclRoles');

        expect(aclRolesSelect.attributes()['data-tooltip-message']).toBe('ct-users.user-detail.disabledRoleSelectWarning');

        expect(aclRolesSelect.attributes()['data-tooltip-disabled']).toBe('false');
    });

    it('should disable the tooltip warning when user is not admin', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');
        Object.assign(wrapper.vm, {
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                email: 'max@mustermann.com',
            },
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        const aclRolesSelect = wrapper.find('.ct-users-user-detail__grid-aclRoles');

        expect(aclRolesSelect.attributes()['data-tooltip-disabled']).toBe('true');
    });

    it('keeps position changes in the user association collection', () => {
        const position = { id: 'position-id', name: 'General Manager' };

        wrapper.vm.onPositionAdd(position);
        expect(Array.from(wrapper.vm.positionIds)).toEqual(['position-id']);

        wrapper.vm.onPositionsUpdate([]);
        expect(Array.from(wrapper.vm.positionIds)).toEqual([]);
    });

    it('should disable all fields when user has not editor rights', async () => {
        Object.assign(wrapper.vm, {
            isLoading: false,
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                email: 'max@mustermann.com',
                active: true,
            },
            integrations: [
                {},
            ],
        });
        await wrapper.vm.$nextTick();
        await flushPromises();

        const fieldName = wrapper.findComponent('.ct-users-user-detail__grid-name');
        const fieldPhoneNumber = wrapper.findComponent('.ct-users-user-detail__grid-phoneNumber');
        const fieldEmail = wrapper.findComponent('.ct-users-user-detail__grid-eMail');
        const fieldUsername = wrapper.findComponent('.ct-users-user-detail__grid-username');
        const fieldProfilePicture = wrapper.findComponent('.ct-users-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.findByLabel('ct-users.user-detail.labelPassword');
        const fieldLanguage = wrapper.findComponent('.ct-users-user-detail__grid-language');
        const fieldActive = wrapper.findComponent('.ct-users-user-detail__grid-active');
        const integrationsTable = wrapper.find('.mt-data-table-stub');

        expect(fieldName.props('disabled')).toBe(true);
        expect(fieldPhoneNumber.props('disabled')).toBe(true);
        expect(fieldEmail.props('disabled')).toBe(true);
        expect(fieldUsername.props('disabled')).toBe(true);
        expect(fieldProfilePicture.attributes().disabled).toBe('true');
        expect(fieldPassword.attributes('disabled')).toBeDefined();
        expect(fieldLanguage.props().disabled).toBe(true);
        expect(fieldActive.props().disabled).toBe(true);
        expect(integrationsTable.attributes('data-disable-delete')).toBe('true');
    });

    it('should enable all fields when user has not editor rights', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');

        Object.assign(wrapper.vm, {
            isLoading: false,
            user: {
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                email: 'max@mustermann.com',
                active: true,
            },
            integrations: [
                {},
            ],
        });
        await wrapper.vm.$nextTick();

        const fieldName = wrapper.find('.ct-users-user-detail__grid-name');
        const fieldPhoneNumber = wrapper.find('.ct-users-user-detail__grid-phoneNumber');
        const fieldEmail = wrapper.find('.ct-users-user-detail__grid-eMail');
        const fieldUsername = wrapper.find('.ct-users-user-detail__grid-username');
        const fieldProfilePicture = wrapper.find('.ct-users-user-detail__grid-profile-picture');
        const fieldPassword = wrapper.find('.ct-users-user-detail__grid-password');
        const fieldLanguage = wrapper.find('.ct-users-user-detail__grid-language');
        const fieldActive = wrapper.find('.ct-users-user-detail__grid-active');
        const integrationsTable = wrapper.find('.mt-data-table-stub');

        expect(fieldName.attributes().disabled).toBeUndefined();
        expect(fieldPhoneNumber.attributes().disabled).toBeUndefined();
        expect(fieldEmail.attributes().disabled).toBeUndefined();
        expect(fieldUsername.attributes().disabled).toBeUndefined();
        expect(fieldProfilePicture.attributes().disabled).toBeUndefined();
        expect(fieldPassword.attributes().disabled).toBeUndefined();
        expect(fieldLanguage.attributes().disabled).toBeUndefined();
        expect(fieldActive.attributes().disabled).toBeUndefined();
        expect(integrationsTable.attributes('data-disable-delete')).toBeUndefined();
    });

    it('should not allow deactivating the current user', async () => {
        wrapper = await createWrapper('users_and_permissions.editor');

        Object.assign(wrapper.vm, {
            isLoading: false,
            userId: 'current-user-id',
            currentUser: {
                id: 'current-user-id',
            },
            user: {
                id: 'current-user-id',
                admin: false,
                localeId: '12345',
                username: 'maxmuster',
                name: 'Max Mustermann',
                email: 'max@mustermann.com',
                active: true,
            },
        });
        await wrapper.vm.$nextTick();

        const fieldActive = wrapper.findComponent('.ct-users-user-detail__grid-active');

        expect(fieldActive.props().disabled).toBe(true);
    });

    it('should change the password', async () => {
        wrapper = await createWrapper('users_and_permissions.editor', {
            global: {
                stubs: {},
            },
        });
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.ct-users-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should delete the password when input is empty', async () => {
        wrapper = await createWrapper('users_and_permissions.editor', {
            global: {
                stubs: {},
            },
        });
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.ct-users-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();
    });

    it('should send a request with the new password', async () => {
        wrapper = await createWrapper('users_and_permissions.editor', {
            global: {
                stubs: {},
            },
        });
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.ct-users-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');
    });

    it('should not send a request when user clears the password field', async () => {
        wrapper = await createWrapper('users_and_permissions.editor', {
            global: {
                stubs: {},
            },
        });
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();

        const fieldPasswordInput = wrapper.find('.ct-users-user-detail__grid-password input');
        expect(fieldPasswordInput.element.value).toBe('');

        await fieldPasswordInput.setValue('fooBar');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('fooBar');

        await fieldPasswordInput.setValue('');
        await fieldPasswordInput.trigger('change');
        await flushPromises();

        expect(wrapper.vm.user.password).toBeUndefined();
    });

    it('should update data onDropMedia item', async () => {
        const mediaId = '2142';
        const mediaItem = { id: mediaId };

        wrapper = await createWrapper('users_and_permissions.editor');
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        wrapper.vm.onDropMedia(mediaItem);
        await flushPromises();

        expect(wrapper.vm.user.avatarId).toBe(mediaId);
        expect(wrapper.vm.user.avatarMedia.id).toBe(mediaId);
        expect(wrapper.vm.mediaItem.id).toBe(mediaId);
    });

    it('should set media data', async () => {
        const mediaId = '2142';
        const mediaItem = { id: mediaId };

        wrapper = await createWrapper('users_and_permissions.editor');
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(wrapper.vm.mediaDefaultFolderId).toBe('1234');

        wrapper.vm.onMediaSelectionChange([mediaItem]);
        await flushPromises();

        expect(wrapper.vm.mediaItem.id).toBe(mediaId);
        expect(wrapper.vm.user.avatarId).toBe(mediaId);
        expect(wrapper.vm.user.avatarMedia.id).toBe(mediaId);
    });

    it('should update the auth token if user password is changed', async () => {
        Contena.Application.$container.resetProviders();
        Contena.Application.addServiceProvider('localeHelper', () => ({ setLocaleWithId: () => Promise.resolve() }));
        wrapper.vm.user.password = 'newPassword';
        await wrapper.vm.saveUser();
        await flushPromises();

        expect(mockedLoginService.loginByUsername).toHaveBeenCalledWith(wrapper.vm.user.username, 'newPassword');
    });

    it('should not update the auth token if user password is not changed', async () => {
        Contena.Application.$container.resetProviders();
        Contena.Application.addServiceProvider('localeHelper', () => ({ setLocaleWithId: () => Promise.resolve() }));
        await wrapper.vm.saveUser();
        await flushPromises();

        expect(mockedLoginService.loginByUsername).not.toHaveBeenCalled();
    });

    it('should not update the auth token if user a different user then the currently logged in user is changed', async () => {
        Contena.Application.$container.resetProviders();
        Contena.Application.addServiceProvider('localeHelper', () => ({ setLocaleWithId: () => Promise.resolve() }));
        wrapper.vm.user.password = 'newPassword';
        wrapper.vm.user.id = 'randomId';
        await wrapper.vm.saveUser();
        await flushPromises();

        expect(mockedLoginService.loginByUsername).not.toHaveBeenCalled();
    });

    it('should stop loading when the email is already used', async () => {
        const saveSpy = jest.spyOn(wrapper.vm.userRepository, 'save');
        jest.spyOn(wrapper.vm.userValidationService, 'checkUserEmail').mockResolvedValue({ emailIsUnique: false });

        Object.assign(wrapper.vm, {
            currentUser: { id: 'current-user-id' },
            user: {
                id: 'edited-user-id',
                email: 'info@contena.cn',
            },
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        await wrapper.vm.saveUser();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(saveSpy).not.toHaveBeenCalled();
    });

    it('should stop loading when email validation fails', async () => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
        const saveSpy = jest.spyOn(wrapper.vm.userRepository, 'save');
        jest.spyOn(wrapper.vm.userValidationService, 'checkUserEmail').mockRejectedValue(
            new Error('Email validation failed'),
        );

        Object.assign(wrapper.vm, {
            currentUser: { id: 'current-user-id' },
            user: {
                id: 'edited-user-id',
                email: 'info@contena.cn',
            },
            isLoading: false,
        });
        await wrapper.vm.$nextTick();

        await expect(wrapper.vm.saveUser()).rejects.toThrow('Email validation failed');

        expect(wrapper.vm.isLoading).toBe(false);
        expect(saveSpy).not.toHaveBeenCalled();
    });
});
