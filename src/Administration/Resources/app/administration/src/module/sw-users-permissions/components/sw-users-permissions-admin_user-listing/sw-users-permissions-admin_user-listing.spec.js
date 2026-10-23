/**
 * @sw-package fundamentals@framework
 */
import { mount, RouterLinkStub } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import 'src/app/mixin/translate-with-fallback.mixin';

async function createWrapper(privileges = [], isSso = { isSso: false }, deleteFunction = () => {}) {
    return mount(
        await wrapTestComponent('sw-users-permissions-admin_user-listing', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    admin_userService: {},
                    loginService: {},
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(
                                    new EntityCollection(
                                        'admin_user',
                                        'admin_user',
                                        Contena.Context.api,
                                        new Criteria(1),
                                        [
                                            {
                                                id: '019bff8c86e773e79ec5538c7b1edabc',
                                                username: 'maxmuster',
                                                firstName: 'Max',
                                                lastName: 'Mustermann',
                                                email: 'max@mustermann.com',
                                                active: false,
                                                aclRoles: [{ name: 'testRole' }],
                                            },
                                            {
                                                id: '019bff8c86e773e79ec5538c7b1ed571',
                                                username: 'admin',
                                                firstName: '',
                                                lastName: 'admin',
                                                email: 'info@contena.cn',
                                                active: true,
                                                aclRoles: [{ name: 'adminRole' }, { name: 'superUser' }],
                                            },
                                        ],
                                        1,
                                    ),
                                );
                            },
                            delete: deleteFunction,
                        }),
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                    ssoSettingsService: {
                        isSso: () => Promise.resolve(isSso),
                    },
                },
                mocks: {
                    $route: { query: '' },
                },
                stubs: {
                    'sw-admin_user-sso-status-label': await wrapTestComponent('sw-admin_user-sso-status-label'),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                    'router-link': RouterLinkStub,
                    'sw-context-menu-item': {
                        template:
                            '<div class="sw-context-menu-item-stub" :disabled="disabled ? \'true\' : undefined"><slot /></div>',
                        props: ['disabled', 'routerLink', 'variant'],
                    },
                    'sw-admin_user-sso-invitation-modal': true,
                    'sw-verify-admin_user-modal': true,
                    'sw-container': true,
                    'sw-simple-search-field': true,
                    'sw-avatar': true,
                    'sw-pagination': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-provide': true,
                    'sw-data-grid-skeleton': true,
                    'sw-color-badge': true,
                },
            },
        },
    );
}

describe('module/sw-users-permissions/components/sw-users-permissions-admin_user-listing', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should retain the deprecated password verification members', () => {
        expect(wrapper.vm.loginService).toBeDefined();
        expect(wrapper.vm.confirmPassword).toBe('');
        expect(wrapper.vm.isConfirmingPassword).toBe(false);
    });

    it('the data-grid should show the right columns', async () => {
        await flushPromises();

        const expectedColumns = [
            {
                property: 'username',
                label: 'sw-users-permissions.users.admin_user-grid.labelUsername',
            },
            {
                property: 'firstName',
                label: 'sw-users-permissions.users.admin_user-grid.labelFirstName',
            },
            {
                property: 'lastName',
                label: 'sw-users-permissions.users.admin_user-grid.labelLastName',
            },
            {
                property: 'aclRoles',
                label: 'sw-users-permissions.users.admin_user-grid.labelRoles',
            },
            {
                property: 'email',
                label: 'sw-users-permissions.users.admin_user-grid.labelEmail',
            },
            {
                property: 'status',
                label: 'sw-users-permissions.users.admin_user-grid.status',
            },
        ];

        expectedColumns.forEach((column) => {
            const label = wrapper.findByText('div', column.label);
            expect(label.exists()).toBe(true);

            const dataCell = wrapper.find('td', column.property);
            expect(dataCell.exists()).toBe(true);
        });
    });

    it('the data-grid should show the right columns with SSO', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator'], { isSso: true });
        await flushPromises();

        const expectedColumns = [
            {
                property: 'email',
                label: 'sw-users-permissions.users.admin_user-grid.labelEmail',
            },
            {
                property: 'aclRoles',
                label: 'sw-users-permissions.users.admin_user-grid.labelRoles',
            },
            {
                property: 'status',
                label: 'sw-users-permissions.users.admin_user-grid.status',
            },
        ];

        expectedColumns.forEach((column) => {
            const label = wrapper.findByText('div', column.label);
            expect(label.exists()).toBe(true);

            const dataCell = wrapper.find('td', column.property);
            expect(dataCell.exists()).toBe(true);
        });
    });

    it('the data-grid should get the right admin_user data', async () => {
        await flushPromises();

        const expectedUser = [
            {
                username: 'maxmuster',
                firstName: 'Max',
                lastName: 'Mustermann',
                email: 'max@mustermann.com',
                active: false,
                aclRoles: ['testRole'],
            },
            {
                username: 'admin',
                firstName: '',
                lastName: 'admin',
                email: 'info@contena.cn',
                active: true,
                aclRoles: ['adminRole', 'superUser'],
            },
        ];

        const allAclRoles = wrapper.findAll('td.sw-data-grid__cell--aclRoles');
        allAclRoles.forEach((aclRole, index) => {
            expectedUser[index].aclRoles.forEach((role) => {
                expect(aclRole.text()).toContain(role);
            });
        });

        expectedUser.forEach((admin_user) => {
            const admin_userName = wrapper.findByText('a.sw-settings-admin_user-list__columns', admin_user.username);
            expect(admin_userName.exists()).toBe(true);

            const firstName = wrapper.findByText('div.sw-data-grid__cell-content', admin_user.firstName);
            expect(firstName.exists()).toBe(true);

            const lastName = wrapper.findByText('div.sw-data-grid__cell-content', admin_user.lastName);
            expect(lastName.exists()).toBe(true);

            const email = wrapper.findByText('div.sw-data-grid__cell-content', admin_user.email);
            expect(email.exists()).toBe(true);

            const activeText = admin_user.active ? 'active' : 'inactive';
            const statusLabel = wrapper.findByText(
                '.sw-admin_user-sso-status-label',
                `sw-users-permissions.sso.admin_user-listing.status-label.${activeText}`,
            );
            expect(statusLabel.exists()).toBe(true);
        });
    });

    it('the data-grid should get the right admin_user data with SSO', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator'], { isSso: true });
        await flushPromises();

        const expectedUser = [
            {
                email: 'max@mustermann.com',
                active: false,
                aclRoles: ['testRole'],
            },
            {
                email: 'info@contena.cn',
                active: true,
                aclRoles: ['adminRole', 'superUser'],
            },
        ];

        const allAclRoles = wrapper.findAll('td.sw-data-grid__cell--aclRoles');
        allAclRoles.forEach((aclRole, index) => {
            expectedUser[index].aclRoles.forEach((role) => {
                expect(aclRole.text()).toContain(role);
            });
        });

        expectedUser.forEach((admin_user) => {
            const email = wrapper.findByText('a.sw-settings-admin_user-list__columns', admin_user.email);
            expect(email.exists()).toBe(true);

            const activeText = admin_user.active ? 'active' : 'inactive';
            const statusLabel = wrapper.findByText(
                '.sw-admin_user-sso-status-label',
                `sw-users-permissions.sso.admin_user-listing.status-label.${activeText}`,
            );
            expect(statusLabel.exists()).toBe(true);
        });
    });

    it('the card should contain the right title', async () => {
        const title = wrapper.findByText('div', 'sw-users-permissions.users.general.cardLabel');
        expect(title.exists()).toBe(true);
    });

    it('the add admin_user button should be disabled', async () => {
        const addUser = wrapper.find('.sw-users-permissions-admin_user-listing__add-admin_user-button');
        expect(addUser.attributes('disabled')).toBeDefined();
    });

    it('the add admin_user button should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator']);
        await wrapper.vm.$nextTick();

        const addUser = wrapper.find('.sw-users-permissions-admin_user-listing__add-admin_user-button');
        expect(addUser.attributes().disabled).toBeUndefined();
    });

    it('the context menu should be disabled', async () => {
        wrapper = await createWrapper([]);
        await flushPromises();

        const contextMenuEdit = wrapper.find('.sw-settings-admin_user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-admin_user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBe('true');
        expect(contextMenuDelete.attributes().disabled).toBe('true');
    });

    it('the context menu edit should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await flushPromises();

        const contextMenuEdit = wrapper.find('.sw-settings-admin_user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-admin_user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBeUndefined();
        expect(contextMenuDelete.attributes().disabled).toBe('true');
    });

    it('the context menu delete should be enabled', async () => {
        wrapper = await createWrapper(['users_and_permissions.deleter']);
        await flushPromises();

        const contextMenuEdit = wrapper.find('.sw-settings-admin_user-list__user-view-action');
        const contextMenuDelete = wrapper.find('.sw-settings-admin_user-list__user-delete-action');

        expect(contextMenuEdit.attributes().disabled).toBe('true');
        expect(contextMenuDelete.attributes().disabled).toBeUndefined();
    });

    it('should add avatar media as association', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);
        await flushPromises();

        expect(wrapper.vm.admin_userCriteria.associations[1].association).toBe('avatarMedia');
    });

    it('should show the default add admin_user button', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator']);
        await flushPromises();

        const addUserButton = wrapper.find('.sw-users-permissions-admin_user-listing__add-admin_user-button');
        expect(addUserButton.find('span').text()).toBe('sw-users-permissions.users.general.labelCreateNewUser');
    });

    it('should show the invite admin_user button', async () => {
        wrapper = await createWrapper(['users_and_permissions.creator'], { isSso: true });
        await flushPromises();

        const addUserButton = wrapper.find('.sw-users-permissions-admin_user-listing__add-admin_user-button');
        expect(addUserButton.find('span').text()).toBe('sw-users-permissions.sso.inviteButtonLabel');
    });

    it('should open the verification modal before deleting a admin_user', async () => {
        const deleteFunction = jest.fn().mockResolvedValue(undefined);
        wrapper = await createWrapper(['users_and_permissions.deleter'], { isSso: false }, deleteFunction);
        const admin_user = { id: 'different-admin_user', firstName: 'Max', lastName: 'Mustermann' };
        Contena.Store.get('session').setCurrentUser({ id: 'current-admin_user' });

        wrapper.vm.onDelete(admin_user);
        wrapper.vm.onConfirmDelete(admin_user);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('sw-verify-admin_user-modal-stub').exists()).toBe(true);
        expect(deleteFunction).not.toHaveBeenCalled();
    });

    it('should delete an SSO admin_user without password verification', async () => {
        const deleteFunction = jest.fn().mockResolvedValue(undefined);
        wrapper = await createWrapper(['users_and_permissions.deleter'], { isSso: true }, deleteFunction);
        const admin_user = { id: 'different-admin_user', firstName: 'Max', lastName: 'Mustermann' };
        Contena.Store.get('session').setCurrentUser({ id: 'current-admin_user' });

        wrapper.vm.onDelete(admin_user);
        wrapper.vm.onConfirmDelete(admin_user);
        await flushPromises();

        expect(deleteFunction).toHaveBeenCalledWith(admin_user.id, Contena.Context.api);
    });

    it('should use the correct route for the Edit context menu item', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);

        await flushPromises();

        const contextMenuEdit = wrapper.findComponent('.sw-settings-admin_user-list__user-view-action');
        expect(contextMenuEdit.exists()).toBe(true);

        // Check that the router-link prop uses the correct route name
        const routerLinkProp = contextMenuEdit.vm.$props.routerLink;
        expect(routerLinkProp).toBeDefined();
        expect(routerLinkProp.name).toBe('sw.users.permissions.admin_user.detail');
        expect(routerLinkProp.params.id).toBe('019bff8c86e773e79ec5538c7b1edabc');
    });

    it('should use the correct route for the Edit context menu item with SSO', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor'], { isSso: true });

        await flushPromises();

        const contextMenuEdit = wrapper.findComponent('.sw-settings-admin_user-list__user-view-action');
        expect(contextMenuEdit.exists()).toBe(true);

        // Check that the router-link prop uses the correct route name
        const routerLinkProp = contextMenuEdit.vm.$props.routerLink;
        expect(routerLinkProp).toBeDefined();
        expect(routerLinkProp.name).toBe('sw.users.permissions.admin_user.sso.detail');
        expect(routerLinkProp.params.id).toBe('019bff8c86e773e79ec5538c7b1edabc');
    });

    it('should use the correct route for editing on the admin_user name', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor']);

        await flushPromises();

        const routerLink = wrapper.findComponent('.sw-settings-admin_user-list__columns');
        expect(routerLink.exists()).toBe(true);

        // Check that the router-link prop uses the correct route name
        const routerLinkProp = routerLink.vm.$props.to;
        expect(routerLinkProp).toBeDefined();
        expect(routerLinkProp.name).toBe('sw.users.permissions.admin_user.detail');
        expect(routerLinkProp.params.id).toBe('019bff8c86e773e79ec5538c7b1edabc');
    });

    it('should use the correct route for editing on the admin_user name with SSO', async () => {
        wrapper = await createWrapper(['users_and_permissions.editor'], { isSso: true });

        await flushPromises();

        const routerLink = wrapper.findComponent('.sw-settings-admin_user-list__columns');
        expect(routerLink.exists()).toBe(true);

        // Check that the router-link prop uses the correct route name
        const routerLinkProp = routerLink.vm.$props.to;
        expect(routerLinkProp).toBeDefined();
        expect(routerLinkProp.name).toBe('sw.users.permissions.admin_user.sso.detail');
        expect(routerLinkProp.params.id).toBe('019bff8c86e773e79ec5538c7b1edabc');
    });
});
