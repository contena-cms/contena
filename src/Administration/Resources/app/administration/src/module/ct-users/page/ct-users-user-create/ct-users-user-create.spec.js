import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import TimezoneService from 'src/core/service/timezone.service';
import EntityCollection from 'src/core/data/entity-collection.data';

const numberRangeService = {
    reserve: jest.fn((type, preview = false) => Promise.resolve({ number: preview ? '10000' : '10001' })),
};
const userRepositorySave = jest.fn(() => Promise.resolve());
const createTagCollection = (tags = []) => new EntityCollection('', 'tag', Contena.Context.api, null, tags, tags.length);
const createPositionCollection = (positions = []) =>
    new EntityCollection('', 'position', Contena.Context.api, null, positions, positions.length);

async function createWrapper(privileges = []) {
    const reservedPrefixWarning = {
        method: 'warn',
        msg: 'setup() return property "__swSetupAuthor_',
    };
    global.allowedErrors.push(reservedPrefixWarning);

    const wrapper = mount(await wrapTestComponent('ct-users-user-create', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            provide: {
                [routeLocationKey]: {
                    meta: { $module: { icon: 'regular-content' } },
                    params: {},
                },
                [routerKey]: { push: jest.fn() },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                loginService: {},
                numberRangeService,
                userService: {
                    getUser: () => Promise.resolve({ data: { id: 'current-user-id' } }),
                },
                mediaDefaultFolderService: {
                    getDefaultFolderId: (folder) => Promise.resolve(folder),
                },
                userValidationService: {},
                integrationService: {},
                repositoryFactory: {
                    create: (entityName) => {
                        if (entityName === 'user') {
                            return {
                                search: () => Promise.resolve(),
                                save: userRepositorySave,
                                get: () => {
                                    return Promise.resolve({
                                        localeId: '7dc07b43229843d387bb5f59233c2d66',
                                        username: 'admin',
                                        name: 'admin',
                                        email: 'info@contena.cn',
                                    });
                                },
                                create: () => {
                                    return {
                                        id: 'new-user-id',
                                        localeId: '',
                                        username: '',
                                        name: '',
                                        phoneNumber: '',
                                        email: '',
                                        password: '',
                                        aclRoles: new EntityCollection('', 'acl_role', Contena.Context.api, null, [], 0),
                                        positions: createPositionCollection(),
                                        tags: createTagCollection(),
                                    };
                                },
                            };
                        }

                        if (entityName === 'language') {
                            return {
                                search: () =>
                                    Promise.resolve(new EntityCollection('', '', Contena.Context.api, null, [], 0)),
                                get: () => Promise.resolve(),
                            };
                        }

                        return {};
                    },
                },
            },
            mocks: {
                $route: {
                    params: {
                        id: undefined,
                    },
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
            stubs: {
                'ct-page': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'ct-card-view': true,
                'ct-text-field': true,
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
                    emits: ['update:modelValue'],
                    template: '<div />',
                },
                'ct-skeleton': true,
                'ct-data-grid': true,
                'ct-context-menu-item': true,
                'ct-button-process': true,
                'ct-media-modal-v2': true,
            },
        },
    });

    global.allowedErrors.pop();

    return wrapper;
}
describe('modules/ct-users/page/ct-users-user-create', () => {
    let wrapper;

    beforeAll(() => {
        Contena.Service().register('timezoneService', () => {
            return new TimezoneService();
        });
    });

    beforeEach(async () => {
        numberRangeService.reserve.mockClear();
        userRepositorySave.mockClear();
        Contena.Store.get('session').languageId = '123456789';
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
        Contena.Store.get('session').languageId = '';
    });

    it('should create a new user', async () => {
        expect(wrapper.vm.user).toStrictEqual({
            admin: false,
            id: 'new-user-id',
            userCode: '10000',
            localeId: '',
            username: '',
            name: '',
            phoneNumber: '',
            email: '',
            password: '',
            active: true,
            aclRoles: expect.any(EntityCollection),
            positions: expect.any(EntityCollection),
            tags: expect.any(EntityCollection),
        });
    });

    it('keeps tag selection in the new user association collection', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();

        const selectedTags = createTagCollection([{ id: 'tag-id', name: 'Internal' }]);
        const fieldTags = wrapper.findComponent('.ct-users-user-detail__tags');
        fieldTags.vm.$emit('update:entityCollection', selectedTags);
        await wrapper.vm.$nextTick();

        expect(Array.from(wrapper.vm.user.tags.getIds())).toEqual(['tag-id']);
    });

    it('loads timezone options and the current user for a new user', () => {
        expect(wrapper.vm.timezoneOptions.length).toBeGreaterThan(0);
        expect(wrapper.vm.currentUser).toEqual({ id: 'current-user-id' });
    });

    it('keeps ACL role changes in the user association collection', () => {
        const role = { id: 'role-id', name: 'Administrator' };

        wrapper.vm.onAclRoleAdd(role);
        expect(wrapper.vm.aclRoleIds).toEqual(['role-id']);

        wrapper.vm.onAclRolesUpdate([]);
        expect(wrapper.vm.aclRoleIds).toEqual([]);
    });

    it('keeps position changes in the user association collection', () => {
        const position = { id: 'position-id', name: 'General Manager' };

        wrapper.vm.onPositionAdd(position);
        expect(Array.from(wrapper.vm.positionIds)).toEqual(['position-id']);

        wrapper.vm.onPositionsUpdate([]);
        expect(Array.from(wrapper.vm.positionIds)).toEqual([]);
    });

    it('should allow to set the password', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.user.password).toBe('');

        const fieldPassword = wrapper.findByLabel('ct-users.user-detail.labelPassword');
        await fieldPassword.setValue('Passw0rd!');
        await flushPromises();

        expect(wrapper.vm.user.password).toBe('Passw0rd!');
    });

    it('should not be an admin by default', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.admin).toBe(false);
    });

    it('should be active by default', async () => {
        Object.assign(wrapper.vm, { isLoading: false });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.user.active).toBe(true);
        expect(wrapper.find('.ct-users-user-detail__grid-active').exists()).toBe(true);
    });

    it('previews and reserves the user code through the upstream number range service', async () => {
        expect(numberRangeService.reserve).toHaveBeenCalledWith('user', true);
        expect(wrapper.vm.user.userCode).toBe('10000');

        await wrapper.vm.saveUser(Contena.Context.api);

        expect(numberRangeService.reserve).toHaveBeenCalledWith('user');
        expect(wrapper.vm.user.userCode).toBe('10001');
        expect(userRepositorySave).toHaveBeenCalledWith(wrapper.vm.user, Contena.Context.api);
    });
});
