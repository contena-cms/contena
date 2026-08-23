import { reactive } from 'vue';
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';
import EntitySchema from 'test/_mocks_/entity-schema.json';

async function createWrapper({
    privilegesMappings = [],
    rolePrivileges = [],
    detailedPrivileges = [],
    canGrant = () => true,
} = {}) {
    const privilegesService = new PrivilegesService();
    privilegesMappings.forEach((mapping) => {
        privilegesService.addPrivilegeMappingEntry(mapping);
    });

    return mount(
        await wrapTestComponent('ct-permissions-detailed-permissions-grid', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    acl: {
                        can: canGrant,
                    },
                    privileges: privilegesService,
                },
            },
            props: reactive({
                role: { privileges: rolePrivileges },
                detailedPrivileges: detailedPrivileges,
            }),
        },
    );
}

let entitySchema;

describe('src/module/ct-permissions/component/ct-permissions-detailed-permissions-grid', () => {
    beforeAll(async () => {
        const entityDefinitionFactory = Contena.Application.getContainer('factory').entityDefinition;
        entitySchema = EntitySchema;

        Object.entries(entitySchema).forEach(
            ([
                name,
                value,
            ]) => {
                entityDefinitionFactory.add(name, value);
            },
        );
    });

    it('should contain the header titles', async () => {
        const wrapper = await createWrapper();

        const headerEntries = wrapper.findAll(
            '.ct-permissions-detailed-permissions-grid__entry-header .ct-permissions-detailed-permissions-grid__checkbox-wrapper',
        );

        expect(headerEntries.at(0).text()).toBe('ct-privileges.permissionType.read');
        expect(headerEntries.at(1).text()).toBe('ct-privileges.permissionType.update');
        expect(headerEntries.at(2).text()).toBe('ct-privileges.permissionType.create');
        expect(headerEntries.at(3).text()).toBe('ct-privileges.permissionType.delete');
    });

    it('should render a row for each entity with all checkboxes enabled', async () => {
        const wrapper = await createWrapper();

        Object.keys(entitySchema).forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityTitle = entityRow.find('.ct-permissions-detailed-permissions-grid__title');
            expect(entityTitle.text()).toBe(entityName);

            // skip default values
            if (
                [
                    'country',
                    'language',
                    'log_entry',
                    'locale',
                    'message_queue_stats',
                ].includes(entityName)
            ) {
                return;
            }

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should render default user privileges as selected and disabled', async () => {
        const wrapper = await createWrapper();

        [
            'country',
            'language',
            'locale',
        ].forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);
            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');

            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityReadInput.element.checked).toBe(true);
        });
    });

    it('should render a row for each entity with all checkboxes disabled when prop disabled is true', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        Object.keys(entitySchema).forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // to be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeDefined();
            expect(entityDeleteInput.attributes().disabled).toBeDefined();
            expect(entityCreateInput.attributes().disabled).toBeDefined();
        });
    });

    it('should render mapped read privileges as selected and disabled', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: ['media.viewer'],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'media:read',
                                'media_folder:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'media:update',
                                'media_folder:update',
                            ],
                            dependencies: ['media.viewer'],
                        },
                    },
                },
            ],
        });

        [
            'media',
            'media_folder',
        ].forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // read should be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBe(true);
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });

        ['tag'].forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should render mapped read and update privileges as selected and disabled', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'media.viewer',
                'media.editor',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'media:read',
                                'media_folder:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'media:update',
                                'media_folder:update',
                            ],
                            dependencies: ['media.viewer'],
                        },
                    },
                },
            ],
        });

        [
            'media',
            'media_folder',
        ].forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // read and update should be disabled
            expect(entityReadInput.attributes().disabled).toBeDefined();
            expect(entityUpdateInput.attributes().disabled).toBeDefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBe(true);
            expect(entityUpdateInput.element.checked).toBe(true);
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });

        ['tag'].forEach((entityName) => {
            const entityRow = wrapper.find(`.ct-permissions-detailed-permissions-grid__entry_${entityName}`);

            const entityReadInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_read input');
            const entityUpdateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
            const entityDeleteInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_create input');
            const entityCreateInput = entityRow.find('.ct-permissions-detailed-permissions-grid__role_delete input');

            // should exist
            expect(entityReadInput.exists()).toBeTruthy();
            expect(entityUpdateInput.exists()).toBeTruthy();
            expect(entityDeleteInput.exists()).toBeTruthy();
            expect(entityCreateInput.exists()).toBeTruthy();

            // not disabled
            expect(entityReadInput.attributes().disabled).toBeUndefined();
            expect(entityUpdateInput.attributes().disabled).toBeUndefined();
            expect(entityDeleteInput.attributes().disabled).toBeUndefined();
            expect(entityCreateInput.attributes().disabled).toBeUndefined();

            // not checked
            expect(entityReadInput.element.checked).toBeFalsy();
            expect(entityUpdateInput.element.checked).toBeFalsy();
            expect(entityDeleteInput.element.checked).toBeFalsy();
            expect(entityCreateInput.element.checked).toBeFalsy();
        });
    });

    it('should be able to check the checkboxes', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'media.viewer',
                'media.editor',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'media:read',
                                'media_folder:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'media:update',
                                'media_folder:update',
                            ],
                            dependencies: ['media.viewer'],
                        },
                    },
                },
            ],
        });

        const privileges = wrapper.props().role.privileges;
        const detailedPrivileges = wrapper.props().detailedPrivileges;

        expect(privileges).toEqual([
            'media.viewer',
            'media.editor',
        ]);
        expect(detailedPrivileges).toEqual([]);

        const tagRow = wrapper.find('.ct-permissions-detailed-permissions-grid__entry_tag');
        const tagUpdateInput = tagRow.find('.ct-permissions-detailed-permissions-grid__role_update input');
        const tagCreateInput = tagRow.find('.ct-permissions-detailed-permissions-grid__role_create input');

        await tagUpdateInput.setChecked();
        await tagCreateInput.setChecked();

        expect(privileges).toEqual([
            'media.viewer',
            'media.editor',
        ]);
        expect(detailedPrivileges).toEqual([
            'tag:update',
            'tag:create',
        ]);
    });

    it('should be able to uncheck the checkboxes', async () => {
        const wrapper = await createWrapper({
            rolePrivileges: [
                'media.viewer',
                'media.editor',
            ],
            detailedPrivileges: [
                'tag:update',
                'tag:create',
            ],
            privilegesMappings: [
                {
                    category: 'permissions',
                    key: 'media',
                    parent: null,
                    roles: {
                        viewer: {
                            privileges: [
                                'media:read',
                                'media_folder:read',
                            ],
                            dependencies: [],
                        },
                        editor: {
                            privileges: [
                                'media:update',
                                'media_folder:update',
                            ],
                            dependencies: ['media.viewer'],
                        },
                    },
                },
            ],
        });

        const privileges = wrapper.props().role.privileges;
        const detailedPrivileges = wrapper.props().detailedPrivileges;

        expect(privileges).toEqual([
            'media.viewer',
            'media.editor',
        ]);
        expect(detailedPrivileges).toEqual([
            'tag:update',
            'tag:create',
        ]);

        const tagRow = wrapper.find('.ct-permissions-detailed-permissions-grid__entry_tag');
        const tagUpdateInput = tagRow.find('.ct-permissions-detailed-permissions-grid__role_update input');

        await tagUpdateInput.setChecked(false);

        expect(privileges).toEqual([
            'media.viewer',
            'media.editor',
        ]);
        expect(detailedPrivileges).toEqual(['tag:create']);
    });

    it('should not add detailed privileges which the current user cannot grant', async () => {
        const wrapper = await createWrapper({
            canGrant: () => false,
        });

        wrapper.vm.changePermissionForEntity('tag', 'update');

        expect(wrapper.props().detailedPrivileges).toEqual([]);
        expect(wrapper.vm.isEntityDisabled('tag', 'update')).toBe(true);
    });

    it('should allow removing an existing privilege which the current user cannot grant', async () => {
        const wrapper = await createWrapper({
            detailedPrivileges: ['tag:update'],
            canGrant: () => false,
        });

        wrapper.vm.changePermissionForEntity('tag', 'update');

        expect(wrapper.props().detailedPrivileges).toEqual([]);
    });
});
