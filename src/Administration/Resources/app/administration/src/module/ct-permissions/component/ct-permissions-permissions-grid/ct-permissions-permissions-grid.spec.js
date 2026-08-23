/* eslint-disable ct-test-rules/test-file-max-lines-warning, ct-test-rules/test-file-max-lines-error */

import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';

async function createWrapper({ privilegesMappings = [], rolePrivileges = [] } = {}) {
    const privilegesService = new PrivilegesService();
    privilegesMappings.forEach((mapping) => {
        privilegesService.addPrivilegeMappingEntry(mapping);
    });

    const wrapper = mount(
        await wrapTestComponent('ct-permissions-permissions-grid', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'ct-field-error': true,
                    'ct-base-field': true,
                },
                provide: {
                    privileges: privilegesService,
                },
            },
            props: reactive({
                role: { privileges: rolePrivileges },
            }),
        },
    );

    await flushPromises();

    return wrapper;
}

describe('src/module/ct-permissions/component/ct-permissions-permissions-grid', () => {
    it('should show the header with all titles', async () => {
        const wrapper = await createWrapper();

        const gridHeader = wrapper.find('.ct-permissions-permissions-grid__entry-header');
        const gridTitle = gridHeader.find('.ct-permissions-permissions-grid__title');
        const gridRoleViewer = gridHeader.findAll('.ct-permissions-permissions-grid__checkbox-wrapper').at(0);
        const gridRoleEditor = gridHeader.findAll('.ct-permissions-permissions-grid__checkbox-wrapper').at(1);
        const gridRoleCreator = gridHeader.findAll('.ct-permissions-permissions-grid__checkbox-wrapper').at(2);
        const gridRoleDeleter = gridHeader.findAll('.ct-permissions-permissions-grid__checkbox-wrapper').at(3);
        const gridRoleAll = gridHeader.find('.ct-permissions-permissions-grid__all');

        expect(gridHeader.exists()).toBeTruthy();

        expect(gridTitle.exists()).toBeTruthy();
        expect(gridTitle.text()).toBe('');

        expect(gridRoleViewer.exists()).toBeTruthy();
        expect(gridRoleViewer.text()).toBe('ct-privileges.roles.viewer');

        expect(gridRoleEditor.exists()).toBeTruthy();
        expect(gridRoleEditor.text()).toBe('ct-privileges.roles.editor');

        expect(gridRoleCreator.exists()).toBeTruthy();
        expect(gridRoleCreator.text()).toBe('ct-privileges.roles.creator');

        expect(gridRoleDeleter.exists()).toBeTruthy();
        expect(gridRoleDeleter.text()).toBe('ct-privileges.roles.deleter');

        expect(gridRoleAll.exists()).toBeTruthy();
        expect(gridRoleAll.text()).toBe('ct-privileges.roles.all');
    });

    it('should show a row with privileges', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const entry = wrapper.find('div[class*=ct-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeTruthy();

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');

        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all .mt-field--checkbox__container');

        expect(mediaRow.exists()).toBeTruthy();
        expect(mediaViewer.exists()).toBeTruthy();
        expect(mediaEditor.exists()).toBeTruthy();
        expect(mediaCreator.exists()).toBeTruthy();
        expect(mediaDeleter.exists()).toBeTruthy();
        expect(mediaAll.exists()).toBeTruthy();
    });

    it('should show only privileges with the right mapping category', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');

        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all .mt-field--checkbox__container');

        expect(mediaRow.exists()).toBeTruthy();
        expect(mediaViewer.exists()).toBeTruthy();
        expect(mediaEditor.exists()).toBeFalsy();
        expect(mediaCreator.exists()).toBeFalsy();
        expect(mediaDeleter.exists()).toBeTruthy();
        expect(mediaAll.exists()).toBeTruthy();
    });

    it('should show only roles which are existing', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'tag',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');

        expect(mediaRow.exists()).toBeFalsy();
    });

    it('should ignore roles outside the permissions mapping category', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'additional_permissions',
                    key: 'system',
                    parent: null,
                    roles: {
                        clear_cache: {
                            dependencies: [],
                            privileges: ['system:clear:cache'],
                        },
                        core_update: {
                            dependencies: [],
                            privileges: ['system:core:update'],
                        },
                        plugin_maintain: {
                            dependencies: [],
                            privileges: ['system:plugin:maintain'],
                        },
                    },
                },
            ],
        });

        const entry = wrapper.find('div[class*=ct-permissions-permissions-grid__entry_');
        expect(entry.exists()).toBeFalsy();
    });

    it('should select the viewer role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow.find('.ct-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await mediaViewer.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(1);
        expect(wrapper.vm.role.privileges[0]).toBe('media.viewer');
        expect(mediaViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should have selected the viewer role directly', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: ['media.viewer'],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow.find('.ct-permissions-permissions-grid__role_viewer');
        const mediaEditor = mediaRow.find('.ct-permissions-permissions-grid__role_editor');

        expect(mediaViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(mediaEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(false);
    });

    it('should select the creator role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaCreator = mediaRow.find('.ct-permissions-permissions-grid__role_creator');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges.length).toBeGreaterThan(0);
        expect(wrapper.vm.role.privileges).toContain('media.creator');
        expect(mediaCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should select a role and all its dependencies in the same row', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaEditor = mediaRow.find('.ct-permissions-permissions-grid__role_editor');
        const mediaCreator = mediaRow.find('.ct-permissions-permissions-grid__role_creator');
        const mediaViewer = mediaRow.find('.ct-permissions-permissions-grid__role_viewer');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(3);

        expect(wrapper.vm.role.privileges).toContain('media.creator');
        expect(wrapper.vm.role.privileges).toContain('media.editor');
        expect(wrapper.vm.role.privileges).toContain('media.viewer');

        expect(mediaViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(mediaEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(mediaCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should have enabled checkboxes when selecting a role with its dependencies', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaViewer.props().checked).toBe(false);

        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();

        wrapper.vm.$forceUpdate();

        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaViewer.props().checked).toBe(true);
    });

    it('should select a role and all its dependencies in other rows', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'tag',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_tag');
        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagCreator = tagRow.find('.ct-permissions-permissions-grid__role_creator');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(wrapper.vm.role.privileges).toHaveLength(0);

        await tagCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toHaveLength(3);

        expect(wrapper.vm.role.privileges).toContain('tag.creator');
        expect(wrapper.vm.role.privileges).toContain('media.editor');
        expect(wrapper.vm.role.privileges).toContain('media.viewer');

        expect(tagCreator.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(mediaViewer.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
        expect(mediaEditor.findComponent('.mt-field--checkbox__container').props().checked).toBe(true);
    });

    it('should select a role and add it to the role privileges prop', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaCreator = mediaRow.find('.ct-permissions-permissions-grid__role_creator');

        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('media.creator');
    });

    it('should select a role and all dependencies to the role privileges prop', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaCreator = mediaRow.find('.ct-permissions-permissions-grid__role_creator');

        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('media.creator');
        expect(wrapper.vm.role.privileges).toContain('media.editor');
        expect(wrapper.vm.role.privileges).toContain('media.viewer');
    });

    it('should select all and all roles in the row should be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        expect(wrapper.vm.role.privileges).not.toContain('media.viewer');
        expect(wrapper.vm.role.privileges).not.toContain('media.editor');
        expect(wrapper.vm.role.privileges).not.toContain('media.creator');
        expect(wrapper.vm.role.privileges).not.toContain('media.deleter');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all');

        await mediaAll.find('.mt-field--checkbox__container input').setChecked();

        expect(wrapper.vm.role.privileges).toContain('media.viewer');
        expect(wrapper.vm.role.privileges).toContain('media.editor');
        expect(wrapper.vm.role.privileges).toContain('media.creator');
        expect(wrapper.vm.role.privileges).toContain('media.deleter');
    });

    it('should select all and all checkboxes in the row should be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all');

        expect(mediaViewer.props().checked).toBe(false);
        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaDeleter.props().checked).toBe(false);

        await mediaAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaDeleter.props().checked).toBe(true);
    });
    it('should select all and roles in other rows should not be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'tag',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_tag');
        const tagViewer = tagRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const tagEditor = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagCreator = tagRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const tagDeleter = tagRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all');

        expect(mediaViewer.props().checked).toBe(false);
        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaDeleter.props().checked).toBe(false);

        expect(tagViewer.props().checked).toBe(false);
        expect(tagEditor.props().checked).toBe(false);
        expect(tagCreator.props().checked).toBe(false);
        expect(tagDeleter.props().checked).toBe(false);

        await mediaAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaDeleter.props().checked).toBe(true);

        expect(tagViewer.props().checked).toBe(false);
        expect(tagEditor.props().checked).toBe(false);
        expect(tagCreator.props().checked).toBe(false);
        expect(tagDeleter.props().checked).toBe(false);
    });

    it('should select some and click on all. All have to be selected', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all');

        await mediaViewer.find('.mt-field--checkbox__container input').setChecked();
        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaDeleter.props().checked).toBe(false);

        await mediaAll.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaDeleter.props().checked).toBe(true);
    });
    it('should select all roles each and the checkbox all have to be checked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const mediaAll = mediaRow
            .find('.ct-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        expect(mediaAll.props().checked).toBe(false);

        await mediaViewer.find('.mt-field--checkbox__container input').setChecked();
        await mediaEditor.find('.mt-field--checkbox__container input').setChecked();
        await mediaCreator.find('.mt-field--checkbox__container input').setChecked();
        await mediaDeleter.find('.mt-field--checkbox__container input').setChecked();
        wrapper.vm.$forceUpdate();

        expect(mediaAll.props().checked).toBe(true);
    });

    it('should select all roles each and the checkbox all have to be checked (privilege has only two roles)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const mediaAll = mediaRow
            .find('.ct-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // prove that creator and deleter checkbox do not exist
        expect(mediaCreator.exists()).toBe(false);
        expect(mediaDeleter.exists()).toBe(false);

        // verify that all checkboxes are not checked
        expect(mediaViewer.props('checked')).toBe(false);
        expect(mediaEditor.props('checked')).toBe(false);
        expect(mediaAll.props('checked')).toBe(false);

        // check viewer and editor role
        await mediaViewer.find('input[type="checkbox"]').setChecked();
        await mediaEditor.find('input[type="checkbox"]').setChecked();

        // check state of viewer, editor and all checkbox
        expect(mediaViewer.props('checked')).toBe(true);
        expect(mediaEditor.props('checked')).toBe(true);
        expect(mediaAll.props('checked')).toBe(true);
    });

    it('should unselect all roles with the checkbox all', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        const mediaAll = mediaRow.find('.ct-permissions-permissions-grid__all .mt-field--checkbox__container');

        await mediaViewer.find('input').setChecked();
        await mediaEditor.find('input').setChecked();
        await mediaCreator.find('input').setChecked();
        await mediaDeleter.find('input').setChecked();

        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaDeleter.props().checked).toBe(true);

        await mediaAll.find('input').setChecked(false);
        wrapper.vm.$forceUpdate();

        expect(mediaViewer.props().checked).toBe(false);
        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaDeleter.props().checked).toBe(false);
    });

    it('should disable checkboxes which are dependencies for viewer (0 dependencies)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await mediaViewer.find('input').setChecked();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaViewer.props().disabled).toBe(false);

        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaEditor.props().disabled).toBe(false);

        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaCreator.props().disabled).toBe(false);

        expect(mediaDeleter.props().checked).toBe(false);
        expect(mediaDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for editor (1 dependency)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await mediaEditor.find('input').setChecked();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaViewer.props().disabled).toBe(true);

        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaEditor.props().disabled).toBe(false);

        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaCreator.props().disabled).toBe(false);

        expect(mediaDeleter.props().checked).toBe(false);
        expect(mediaDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for creator (2 dependencies)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await mediaCreator.find('input').setChecked();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaViewer.props().disabled).toBe(true);

        expect(mediaEditor.props().checked).toBe(true);
        expect(mediaEditor.props().disabled).toBe(true);

        expect(mediaCreator.props().checked).toBe(true);
        expect(mediaCreator.props().disabled).toBe(false);

        expect(mediaDeleter.props().checked).toBe(false);
        expect(mediaDeleter.props().disabled).toBe(false);
    });

    it('should disable checkboxes which are dependencies for deleter (1 dependency)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');

        await mediaDeleter.find('input').setChecked();

        expect(mediaViewer.props().checked).toBe(true);
        expect(mediaViewer.props().disabled).toBe(true);

        expect(mediaEditor.props().checked).toBe(false);
        expect(mediaEditor.props().disabled).toBe(false);

        expect(mediaCreator.props().checked).toBe(false);
        expect(mediaCreator.props().disabled).toBe(false);

        expect(mediaDeleter.props().checked).toBe(true);
        expect(mediaDeleter.props().disabled).toBe(false);
    });

    it('should show the parent permissions', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['media.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['media.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const parentGrids = wrapper.findAll('.ct-permissions-permissions-grid__parent');
        expect(parentGrids).toHaveLength(3);

        const parentResources = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const parentNull = wrapper.find('.ct-permissions-permissions-grid__parent_null');
        const parentSettings = wrapper.find('.ct-permissions-permissions-grid__parent_settings');

        expect(parentResources.isVisible()).toBeTruthy();
        expect(parentNull.isVisible()).toBeTruthy();
        expect(parentSettings.isVisible()).toBeTruthy();
    });

    it('should organize the children to the matching parents', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['media.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['media.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const gridEntries = wrapper.findAll('.ct-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(8);

        // header in beginning
        expect(gridEntries.at(0).classes()).toContain('ct-permissions-permissions-grid__entry-header');

        // other (null) with children
        expect(gridEntries.at(1).classes()).toContain('ct-permissions-permissions-grid__parent_null');
        expect(gridEntries.at(2).classes()).toContain('ct-permissions-permissions-grid__entry_integration');

        // resources with children
        expect(gridEntries.at(3).classes()).toContain('ct-permissions-permissions-grid__parent_resources');
        expect(gridEntries.at(4).classes()).toContain('ct-permissions-permissions-grid__entry_media');
        expect(gridEntries.at(5).classes()).toContain('ct-permissions-permissions-grid__entry_users');

        // settings with children
        expect(gridEntries.at(6).classes()).toContain('ct-permissions-permissions-grid__parent_settings');
        expect(gridEntries.at(7).classes()).toContain('ct-permissions-permissions-grid__entry_countries');
    });

    it('should sort parents alphabetically with the label', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'workflows',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: 'content',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {},
                },
            ],
        });

        const gridEntries = wrapper.findAll('.ct-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(9);

        // check if order is sorted alphabetically
        expect(gridEntries.at(1).classes()).toContain('ct-permissions-permissions-grid__parent_content');
        expect(gridEntries.at(3).classes()).toContain('ct-permissions-permissions-grid__parent_resources');
        expect(gridEntries.at(5).classes()).toContain('ct-permissions-permissions-grid__parent_settings');
        expect(gridEntries.at(7).classes()).toContain('ct-permissions-permissions-grid__parent_workflows');
    });

    it('should sort children in parents alphabetically', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'state_machine',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'basic_information',
                    parent: 'settings',
                    roles: {},
                },
                {
                    category: 'permissions',
                    key: 'plugins',
                    parent: 'settings',
                    roles: {},
                },
            ],
        });

        const gridEntries = wrapper.findAll('.ct-permissions-permissions-grid__entry');
        expect(gridEntries).toHaveLength(6);

        // check if order is sorted alphabetically
        expect(gridEntries.at(2).classes()).toContain('ct-permissions-permissions-grid__entry_basic_information');
        expect(gridEntries.at(3).classes()).toContain('ct-permissions-permissions-grid__entry_countries');
        expect(gridEntries.at(4).classes()).toContain('ct-permissions-permissions-grid__entry_plugins');
        expect(gridEntries.at(5).classes()).toContain('ct-permissions-permissions-grid__entry_state_machine');
    });

    it('parent checkbox should be partial checked when some of the child permission is clicked (TODO)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceViewerCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceViewerCheckbox.props().partial).toBe(false);
        expect(resourceEditorCheckbox.props().partial).toBe(false);

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaEditorCheckbox = mediaRow.find(
            '.ct-permissions-permissions-grid__role_editor .mt-field--checkbox__container',
        );

        await mediaEditorCheckbox.find('input').setChecked();

        expect(resourceViewerCheckbox.props().partial).toBe(true);
        expect(resourceEditorCheckbox.props().partial).toBe(true);
    });

    it('parent checkbox should be partial checked when some of the child permission is clicked (all)', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceAllCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_all')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceAllCheckbox.props().partial).toBe(false);

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaAllCheckbox = mediaRow.find('.ct-permissions-permissions-grid__role_all .mt-field--checkbox__container');

        await mediaAllCheckbox.find('input').setChecked();

        expect(resourceAllCheckbox.props().partial).toBe(true);
    });

    it('parent checkbox should be checked when all of the child permission is clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceEditorCheckbox.props().checked).toBe(false);
        expect(mediaEditorCheckbox.props().checked).toBe(false);
        expect(tagEditorCheckbox.props().checked).toBe(false);

        await mediaEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(false);

        await tagEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should be disabled when all of the child permission are disabled', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceViewerCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceViewerCheckbox.props().disabled).toBe(false);

        await mediaEditorCheckbox.find('input').setChecked();

        expect(resourceViewerCheckbox.props().disabled).toBe(false);

        await tagEditorCheckbox.find('input').setChecked();

        expect(resourceViewerCheckbox.props().disabled).toBe(true);
    });

    it('parent checkbox should check all of the child permission when clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceEditorCheckbox.props().checked).toBe(false);
        expect(mediaEditorCheckbox.props().checked).toBe(false);
        expect(tagEditorCheckbox.props().checked).toBe(false);

        await resourceEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should check the child permission except missing roles when clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        // Missing editor role for users
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        expect(resourceEditorCheckbox.props().checked).toBe(false);
        expect(mediaEditorCheckbox.props().checked).toBe(false);
        expect(tagEditorCheckbox.exists()).toBeFalsy();

        await resourceEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.exists()).toBeFalsy();

        expect(wrapper.vm.role.privileges).not.toContain('users.editor');
    });

    it('parent checkbox should check all of the child permission when clicked and some child permissions are already clicked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        await mediaEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(false);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.props().checked).toBe(false);

        await resourceEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.props().checked).toBe(true);
    });

    it('parent checkbox should uncheck all of the child permission when unchecked', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceEditorCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');

        await mediaEditorCheckbox.find('input').setChecked();
        await tagEditorCheckbox.find('input').setChecked();

        expect(resourceEditorCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagEditorCheckbox.props().checked).toBe(true);

        await resourceEditorCheckbox.find('input').setChecked(false);

        expect(resourceEditorCheckbox.props().checked).toBe(false);
        expect(mediaEditorCheckbox.props().checked).toBe(false);
        expect(tagEditorCheckbox.props().checked).toBe(false);
    });

    it('parent checkbox should uncheck all of the child permission when unchecked expect disabled checkboxes', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const resourcesRow = wrapper.find('.ct-permissions-permissions-grid__parent_resources');
        const resourceViewerCheckbox = resourcesRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const tagRow = wrapper.find('.ct-permissions-permissions-grid__entry_users');
        const mediaViewerCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditorCheckbox = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagEditorCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const tagViewerCheckbox = tagRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // check media.editor
        await mediaEditorCheckbox.find('input').setChecked();

        expect(resourceViewerCheckbox.props().checked).toBe(false);
        expect(mediaViewerCheckbox.props().checked).toBe(true);
        expect(mediaEditorCheckbox.props().checked).toBe(true);
        expect(tagViewerCheckbox.props().checked).toBe(false);
        expect(tagEditorCheckbox.props().checked).toBe(false);

        // check all resources viewer children
        await tagViewerCheckbox.find('input').setChecked();

        expect(resourceViewerCheckbox.props().checked).toBe(true);
        expect(mediaViewerCheckbox.props().checked).toBe(true);
        expect(tagViewerCheckbox.props().checked).toBe(true);

        // uncheck all resources viewer children
        await resourceViewerCheckbox.find('input').setChecked(false);

        expect(mediaViewerCheckbox.props().checked).toBe(true);
        expect(tagViewerCheckbox.props().checked).toBe(false);
    });

    it('should disable all checkboxes', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'media.viewer',
                                'media.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [
                                'media.viewer',
                            ],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'users',
                    parent: 'resources',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'users.viewer',
                                'users.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['users.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'countries',
                    parent: 'settings',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'countries.viewer',
                                'countries.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['countries.viewer'],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'integration',
                    parent: null,
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [
                                'integration.viewer',
                                'integration.editor',
                            ],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: ['integration.viewer'],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const checkboxes = wrapper.findAllComponents({
            name: 'MtCheckbox',
        });

        checkboxes.forEach((checkbox) => {
            expect(checkbox.props().disabled).toBe(false);
        });

        await wrapper.setProps({ disabled: true });

        checkboxes.forEach((checkbox) => {
            expect(checkbox.props().disabled).toBe(true);
        });
    });

    it('should not exist in the DOM if it has no child roles', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resource',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        // get media viewer checkbox
        const resourceRow = wrapper.find('.ct-permissions-permissions-grid__parent_resource');
        const resourceViewer = resourceRow.find(
            '.ct-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // get media viewer checkbox
        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // assert that resource parent does not exist
        expect(resourceViewer.exists()).toBe(false);

        // assert that child does not exist
        expect(mediaViewer.exists()).toBe(false);
    });

    it('should exist in the DOM if it has at least one child role', async () => {
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resource',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'custom_field',
                    parent: 'resource',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        // get media viewer checkbox
        const resourceRow = wrapper.find('.ct-permissions-permissions-grid__parent_resource');
        const resourceViewer = resourceRow.find(
            '.ct-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // get media viewer checkbox
        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');

        // get custom_field viewer checkbox
        const custom_fieldRow = wrapper.find('.ct-permissions-permissions-grid__entry_custom_field');
        const custom_fieldViewer = custom_fieldRow.find(
            '.ct-permissions-permissions-grid__role_viewer .mt-field--checkbox__container',
        );

        // assert that resource parent does exist
        expect(resourceViewer.exists()).toBe(true);

        // assert that media child does not exist
        expect(mediaViewer.exists()).toBe(false);

        // assert that custom_field child does exist
        expect(custom_fieldViewer.exists()).toBe(true);
    });

    /*
     * Write test that checks the select all roles button and prove that the header role that has one missing role
     * has no partial value and is not checked. but all the others are.
     *
     * also check that the select all header checkbox has a partial value
     */
    it('should only add permissions that exist using the check all box', async () => {
        /** @type Wrapper */
        const wrapper = await createWrapper({
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: 'resource',
                    roles: {
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
                {
                    category: 'permissions',
                    key: 'custom_field',
                    parent: 'resource',
                    roles: {
                        viewer: {
                            dependencies: [],
                            privileges: [],
                        },
                        editor: {
                            dependencies: [],
                            privileges: [],
                        },
                        creator: {
                            dependencies: [],
                            privileges: [],
                        },
                        deleter: {
                            dependencies: [],
                            privileges: [],
                        },
                    },
                },
            ],
        });

        const mediaRow = wrapper.find('.ct-permissions-permissions-grid__entry_media');
        const mediaViewer = mediaRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const mediaEditor = mediaRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const mediaCreator = mediaRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const mediaDeleter = mediaRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const mediaAll = mediaRow
            .find('.ct-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // check that media viewer box does not exist
        expect(mediaViewer.exists()).toBe(false);

        // assert that every checkbox has a state of false
        expect(mediaEditor.props('checked')).toBe(false);
        expect(mediaCreator.props('checked')).toBe(false);
        expect(mediaDeleter.props('checked')).toBe(false);
        expect(mediaAll.props('checked')).toBe(false);

        await mediaAll.find('input[type="checkbox"]').setChecked();

        expect(mediaEditor.props('checked')).toBe(true);
        expect(mediaCreator.props('checked')).toBe(true);
        expect(mediaDeleter.props('checked')).toBe(true);
        expect(mediaAll.props('checked')).toBe(true);

        const headerRow = wrapper.find('.ct-permissions-permissions-grid__parent');
        const headerViewer = headerRow
            .find('.ct-permissions-permissions-grid__role_viewer')
            .findComponent('.mt-field--checkbox__container');
        const headerEditor = headerRow
            .find('.ct-permissions-permissions-grid__role_editor')
            .findComponent('.mt-field--checkbox__container');
        const headerCreator = headerRow
            .find('.ct-permissions-permissions-grid__role_creator')
            .findComponent('.mt-field--checkbox__container');
        const headerDeleter = headerRow
            .find('.ct-permissions-permissions-grid__role_deleter')
            .findComponent('.mt-field--checkbox__container');
        const headerAll = headerRow
            .find('.ct-permissions-permissions-grid__all')
            .findComponent('.mt-field--checkbox__container');

        // assert that viewer header checkbox as not value and no partial value
        expect(headerViewer.props('partial')).toBe(false);
        expect(headerViewer.props('checked')).toBe(false);

        // check that every other header checkbox has a partial value of true and a value of false
        expect(headerEditor.props('partial')).toBe(true);
        expect(headerEditor.props('checked')).toBe(false);

        expect(headerCreator.props('partial')).toBe(true);
        expect(headerCreator.props('checked')).toBe(false);

        expect(headerDeleter.props('partial')).toBe(true);
        expect(headerDeleter.props('checked')).toBe(false);

        expect(headerAll.props('partial')).toBe(true);
        expect(headerAll.props('checked')).toBe(false);
    });
});
