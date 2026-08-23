import { mount } from '@vue/test-utils';
import { routeLocationKey } from 'vue-router';
import 'src/module/ct-integration/page/ct-integration-list';

const integrationFixture = {
    id: 'integration-id',
    label: 'Automation client',
    aclRoles: [],
    mcpAllowlist: null,
};

async function createWrapper(privileges = [], integrations = null, options = {}) {
    const defaultIntegrations = integrations ?? [{ id: '44de136acf314e7184401d36406c1e90' }];
    const saveMock = options.saveMock ?? jest.fn().mockResolvedValue();
    const searchMock = options.searchMock ?? jest.fn().mockResolvedValue(defaultIntegrations);
    const updateAdminMock = options.updateAdminMock ?? jest.fn().mockResolvedValue();

    const wrapper = mount(await wrapTestComponent('ct-integration-list', { sync: true }), {
        global: {
            provide: {
                [routeLocationKey]: {
                    meta: {
                        $module: {
                            title: 'ct-integration.general.mainMenuItemGeneral',
                        },
                    },
                },
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve({
                                id: '44de136acf314e7184401d36406c1e90',
                            });
                        },

                        search: searchMock,

                        save: saveMock,

                        delete: () => {
                            return Promise.resolve();
                        },
                    }),
                },

                integrationService: {
                    generateKey: () => {
                        return Promise.resolve({
                            accessKey: 'SWIANMDUSUR1Q2X0VURGAVDAQG',
                            secretAccessKey: 'YzFnaFprUjdaZUI4WkJsSmVOcHNOTnI5bUNqc2o4YUx0WmFIb3Y',
                        });
                    },
                    saveMcpAllowlist: () => {
                        return Promise.resolve();
                    },
                    updateAdmin: updateAdminMock,
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
                'ct-page': {
                    template: `
                        <div class="ct-page">
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-back"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="side-content"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                            <slot></slot>
                        </div>
                        `,
                },
                'ct-card-view': {
                    template: `
                        <div class="ct-card-view">
                            <slot></slot>
                        </div>
                        `,
                },
                'mt-card': {
                    template: `
                        <div class="mt-card">
                            <slot></slot>
                        </div>
                        `,
                },
                'ct-language-switch': true,
                'ct-search-bar': true,
                'ct-container': {
                    template: '<div><slot></slot></div>',
                },
                'ct-entity-multi-select': true,
                'ct-entity-listing': {
                    props: [
                        'items',
                        'dataSource',
                        'detailRoute',
                        'fullPage',
                    ],
                    template: `
                        <div>
                            <template v-for="item in (dataSource || items)" :key="item.id">
                                <slot name="actions" v-bind="{ item }">
                                </slot>
                                <slot name="action-modals" v-bind="{ item }">
                                </slot>
                            </template>
                        </div>
                    `,
                },
                'ct-context-menu-item': await wrapTestComponent('ct-context-menu-item'),

                'ct-label': true,
                'router-link': true,
                'ct-loader': true,
            },
            mocks: {
                $route: {
                    meta: {
                        $module: {
                            icon: 'solid-content',
                        },
                    },
                },
            },
        },
    });

    await flushPromises();
    return wrapper;
}

describe('module/ct-integration/page/ct-integration-list', () => {
    it('lets the entity listing determine its content height', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent('.ct-integration-list__grid').props('fullPage')).toBe(false);
    });

    it('should not be able to create / edit without permissions', async () => {
        const wrapper = await createWrapper();

        const createButton = wrapper.find('.ct-integration-list__add-integration-action');
        expect(createButton.attributes().disabled).toBeDefined();

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action .ct-context-menu-item');
        expect(editMenuItem.classes()).toContain('is--disabled');

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action .ct-context-menu-item');
        expect(deleteMenuItem.classes()).toContain('is--disabled');
    });

    it('should be able to create a integration', async () => {
        const wrapper = await createWrapper([
            'integration.creator',
            'integration.editor',
        ]);

        const createButton = wrapper.find('.ct-integration-list__add-integration-action');
        expect(createButton.attributes().disabled).toBeUndefined();
        await createButton.trigger('click');
        await flushPromises();

        const modal = wrapper.find('.ct-modal.ct-integration-list__detail');
        expect(modal.exists()).toBeTruthy();

        const labelField = wrapper.find('#ct-field--currentIntegration-label');
        await labelField.setValue('Test');

        const accessKeyField = wrapper.find('#ct-field--currentIntegration-accessKey');
        expect(accessKeyField.element.value).toBe('SWIANMDUSUR1Q2X0VURGAVDAQG');

        const secretKeyField = wrapper.find('#ct-field--currentIntegration-secretAccessKey');
        expect(secretKeyField.element.value).toBe('YzFnaFprUjdaZUI4WkJsSmVOcHNOTnI5bUNqc2o4YUx0WmFIb3Y');

        const saveButton = wrapper.find('.ct-integration-detail-modal__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();
        await saveButton.trigger('click');
        await flushPromises();

        const modalAfterSave = wrapper.find('.ct-modal.ct-integration-list__detail');
        expect(modalAfterSave.exists()).toBeFalsy();
    });

    it('should be able to edit a integration', async () => {
        const wrapper = await createWrapper([
            'integration.editor',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action .ct-context-menu-item');
        await editMenuItem.trigger('click');
        await flushPromises();

        const modal = wrapper.find('.ct-modal.ct-integration-list__detail');
        expect(modal.exists()).toBeTruthy();

        const labelField = wrapper.find('#ct-field--currentIntegration-label');
        await labelField.setValue('Test2');

        const accessKeyField = wrapper.find('#ct-field--currentIntegration-accessKey');
        expect(accessKeyField.exists()).toBeTruthy();

        // secret field should be hidden on edit
        const secretKeyField = wrapper.find('#ct-field--currentIntegration-secretAccessKey');
        expect(secretKeyField.exists()).toBeFalsy();

        const saveButton = wrapper.find('.ct-integration-detail-modal__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();
        await saveButton.trigger('click');
        await flushPromises();

        const modalAfterSave = wrapper.find('.ct-modal.ct-integration-list__detail');
        expect(modalAfterSave.exists()).toBeFalsy();
    });

    it('should update the admin flag through the integration service', async () => {
        const integration = {
            id: '44de136acf314e7184401d36406c1e90',
            label: 'Test integration',
            admin: true,
            aclRoles: [],
            getOrigin: () => {
                return { admin: false };
            },
        };
        const saveMock = jest.fn().mockResolvedValue();
        const updateAdminMock = jest.fn().mockResolvedValue();
        const searchMock = jest.fn().mockResolvedValue([integration]);

        const wrapper = await createWrapper(
            [
                'admin',
                'integration.editor',
            ],
            [integration],
            {
                saveMock,
                searchMock,
                updateAdminMock,
            },
        );

        await wrapper.vm.updateIntegration(integration);
        await flushPromises();

        expect(saveMock).toHaveBeenCalledWith(integration);
        expect(updateAdminMock).toHaveBeenCalledWith(integration.id, true);
        expect(searchMock).toHaveBeenCalledTimes(2);
    });

    it('should not update the admin flag when it was not changed', async () => {
        const integration = {
            id: '44de136acf314e7184401d36406c1e90',
            label: 'Test integration',
            admin: false,
            aclRoles: [],
            getOrigin: () => {
                return { admin: false };
            },
        };
        const updateAdminMock = jest.fn().mockResolvedValue();

        const wrapper = await createWrapper(
            [
                'admin',
                'integration.editor',
            ],
            [integration],
            {
                updateAdminMock,
            },
        );

        await wrapper.vm.updateIntegration(integration);
        await flushPromises();

        expect(updateAdminMock).not.toHaveBeenCalled();
    });

    it('should be able to delete a integration', async () => {
        const wrapper = await createWrapper([
            'integration.deleter',
        ]);

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action .ct-context-menu-item');
        await deleteMenuItem.trigger('click');
        await flushPromises();

        const deleteModal = wrapper.find('.ct-modal');
        expect(deleteModal.exists()).toBeTruthy();

        const deleteButton = deleteModal
            .findAll('button')
            .find((button) => button.text().trim() === 'global.default.delete');
        expect(deleteButton.text()).toBe('global.default.delete');
        await deleteButton.trigger('click');
        await flushPromises();

        const modalAfterDelete = wrapper.find('.ct-modal');
        expect(modalAfterDelete.exists()).toBeFalsy();
    });

    it('should not be able add an integration with admin-role as a non-admin', async () => {
        const wrapper = await createWrapper([
            'integration.viewer',
            'integration.editor',
            'integration.deleter',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action .ct-context-menu-item');
        await editMenuItem.trigger('click');
        await flushPromises();

        const adminRoleSwitch = wrapper.findComponent('.ct-settings-user-detail__grid-is-admin');
        expect(adminRoleSwitch.props().disabled).toBe(true);
    });

    it('should allow editing MCP tools for integrations', async () => {
        const wrapper = await createWrapper(['integration_mcp.editor'], [integrationFixture]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action .ct-context-menu-item');
        expect(mcpMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should not disable edit and delete for integrations with matching privileges', async () => {
        const wrapper = await createWrapper([
            'integration.editor',
            'integration.deleter',
        ]);

        const editMenuItem = wrapper.find('.sw_integration_list__edit-action .ct-context-menu-item');
        expect(editMenuItem.classes()).not.toContain('is--disabled');

        const deleteMenuItem = wrapper.find('.sw_integration_list__delete-action .ct-context-menu-item');
        expect(deleteMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should call integrationService.saveMcpAllowlist on save', async () => {
        const integration = { ...integrationFixture };
        const saveMock = jest.fn().mockResolvedValue();
        const wrapper = await createWrapper(['integration_mcp.editor'], [integration]);
        wrapper.vm.$.appContext.provides.integrationService.saveMcpAllowlist = saveMock;

        wrapper.vm.mcpIntegration = integration;
        wrapper.vm.pendingMcpAllowlist = ['contena-entity-read'];

        await wrapper.vm.onSaveMcpAllowlist();
        await flushPromises();

        expect(saveMock).toHaveBeenCalledWith(integration.id, ['contena-entity-read']);
    });

    it('should gate Edit MCP Tools on integration_mcp.editor not integration.editor', async () => {
        const wrapper = await createWrapper(['integration.editor'], [integrationFixture]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action .ct-context-menu-item');
        expect(mcpMenuItem.classes()).toContain('is--disabled');
    });

    it('should enable Edit MCP Tools with integration_mcp.editor', async () => {
        const wrapper = await createWrapper(['integration_mcp.editor'], [integrationFixture]);

        const mcpMenuItem = wrapper.find('.sw_integration_list__edit-mcp-action .ct-context-menu-item');
        expect(mcpMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should have integration criteria with filters', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.integrationCriteria;

        expect(criteria.filters).toStrictEqual([
            expect.objectContaining({
                field: 'deletedAt',
                type: 'equals',
                value: null,
            }),
        ]);
    });

    it('searches integrations through Criteria', async () => {
        const searchMock = jest.fn().mockResolvedValue([]);
        const wrapper = await createWrapper([], [], { searchMock });
        searchMock.mockClear();

        await wrapper.vm.onSearch('automation');

        expect(wrapper.vm.integrationCriteria.term).toBe('automation');
        expect(searchMock).toHaveBeenCalledWith(wrapper.vm.integrationCriteria);
    });
});
