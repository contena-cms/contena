import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

function mockCustomFieldSetData() {
    const _customFieldSets = [];

    for (let i = 0; i < 10; i += 1) {
        const customFieldSet = {
            id: `id${i}`,
            name: `custom_additional_field_set_${i}`,
            active: true,
            apiAlias: null,
            config: {
                label: {
                    'en-GB': 'Industrial',
                },
            },
            createdAt: '2020-09-04T11:22:08.376+00:00',
            global: false,
            position: 2,
            updatedAt: '2020-09-07T07:01:50.245+00:00',
        };

        _customFieldSets.push(customFieldSet);
    }

    return _customFieldSets;
}

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('ct-settings-custom-field-set-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        params: {
                            id: '1234',
                        },
                        query: {
                            limit: '25',
                            naturalSorting: false,
                            page: 1,
                            sortBy: 'config.name',
                            sortDirection: 'ASC',
                        },
                    },
                },
                provide: {
                    [routeLocationKey]: {
                        name: 'custom-field-set-list',
                        query: {
                            limit: 25,
                            naturalSorting: false,
                            page: 1,
                            sortBy: 'config.name',
                            sortDirection: 'ASC',
                        },
                        meta: {
                            $module: {
                                icon: 'test',
                            },
                        },
                        params: {},
                    },
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve(mockCustomFieldSetData());
                            },
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },
                },
                stubs: {
                    'ct-page': {
                        template: `
                    <div class="ct-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`,
                    },
                    'ct-search-bar': true,
                    'ct-grid': await wrapTestComponent('ct-grid'),
                    'ct-context-button': {
                        template: '<div class="ct-context-button"><slot></slot></div>',
                    },
                    'ct-context-menu-item': {
                        template: '<div class="ct-context-menu-item"><slot></slot></div>',
                    },
                    'ct-context-menu': {
                        template: '<div><slot></slot></div>',
                    },
                    'ct-grid-column': {
                        template: '<div class="ct-grid-column"><slot></slot></div>',
                    },
                    'ct-grid-row': {
                        template: '<div class="ct-grid-row"><slot></slot></div>',
                    },
                    'ct-pagination': true,
                    'router-link': true,
                    'ct-card-view': true,
                    'ct-ignore-class': true,
                    'ct-extension-component-section': true,
                    'ct-help-text': true,
                    'ct-ai-copilot-badge': true,
                    'ct-loader': true,
                    'ct-checkbox-field': true,
                },
            },
        },
    );
}

describe('module/ct-settings-custom-field/page/ct-settings-custom-field-set-list', () => {
    it('should not be able to create a new custom-field set', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const createButton = wrapper.find('.ct-settings-custom-field-set-list__button-create');

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to create a new custom-field set', async () => {
        const wrapper = await createWrapper([
            'custom_field.creator',
        ]);
        await flushPromises();

        const createButton = wrapper.find('.ct-settings-custom-field-set-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteMenuItem = wrapper.find('.ct-settings-custom-field-set-list__delete-action');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        const wrapper = await createWrapper([
            'custom_field.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.ct-settings-custom-field-set-list__delete-action');
        expect(deleteMenuItem.attributes('disabled')).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.ct-custom-field-set-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.ct-custom-field-set-list__edit-action');
        expect(editMenuItem.attributes('disabled')).toBeFalsy();
    });

    it('should contain a listing criteria with page and limit properties', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.listingCriteria.page).toBe(1);
        expect(wrapper.vm.listingCriteria.limit).toBe(25);
    });
});
