import { mount } from '@vue/test-utils';

const set = {
    id: '9f359a2ab0824784a608fc2a443c5904',
    customFields: {},
};

let customFields = mockCustomFieldData();

function mockCustomFieldData() {
    const _customFields = [];

    for (let i = 0; i < 10; i += 1) {
        const customField = {
            id: `id${i}`,
            name: `custom_additional_field_${i}`,
            config: {
                label: { 'en-GB': `Special field ${i}` },
                customFieldType: 'checkbox',
                customFieldPosition: i + 1,
            },
        };

        _customFields.push(customField);
    }

    return _customFields;
}

function mockCustomFieldRepository() {
    class Repository {
        constructor() {
            this._customFields = customFields;
        }

        search() {
            const response = this._customFields;
            response.total = this._customFields.length;

            response.sort((a, b) => a.config.customFieldPosition - b.config.customFieldPosition);

            return Promise.resolve(this._customFields);
        }

        save(field) {
            if (field.id === 'id1337') {
                this._customFields.push(field);
            }

            return Promise.resolve();
        }

        syncDeleted() {
            this._customFields.splice(0, 1);

            return Promise.resolve();
        }
    }

    return new Repository();
}

async function createWrapper(privileges = [], repo = mockCustomFieldRepository()) {
    customFields = mockCustomFieldData();

    return mount(
        await wrapTestComponent('ct-custom-field-list', {
            sync: true,
        }),
        {
            props: {
                set: set,
            },
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create() {
                            return repo;
                        },
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
                    'mt-card': true,
                    'ct-simple-search-field': {
                        template: '<div></div>',
                    },
                    'ct-container': true,
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
                    'ct-checkbox-field': {
                        template: '<div></div>',
                    },
                    'ct-pagination': await wrapTestComponent('ct-pagination'),
                    'ct-loader': true,
                    'ct-modal': true,
                    'ct-text-field': true,
                    'mt-number-field': true,
                    'ct-custom-field-detail': true,
                    'ct-select-field': true,
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
        },
    );
}

describe('src/module/ct-settings-custom-field/component/ct-custom-field-list/ct-custom-field-list', () => {
    it('should store api error', async () => {
        const repoMock = {
            search: jest.fn(() => Promise.resolve(mockCustomFieldRepository().search())),
            save: jest.fn(() =>
                Promise.reject({
                    response: {
                        data: {
                            errors: [
                                {
                                    code: 'SOME_ERROR_CODE',
                                    detail: 'Some error happened',
                                },
                            ],
                        },
                    },
                }),
            ),
        };

        const wrapper = await createWrapper(['custom_field.editor'], repoMock);
        await flushPromises();

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        await wrapper.find('.ct-custom-field-list__edit-action').trigger('click');
        await flushPromises();

        expect(wrapper.find('ct-custom-field-detail-stub').exists()).toBe(true);
        await wrapper.getComponent('ct-custom-field-detail-stub').vm.$emit('custom-field-edit-save', customFields[0]);
        await flushPromises();

        expect(repoMock.save).toHaveBeenCalledTimes(1);
        expect(notificationSpy).toHaveBeenNthCalledWith(
            1,
            expect.objectContaining({ variant: 'error', message: 'Some error happened' }),
        );

        const errors = Contena.Store.get('error').getAllApiErrors();
        expect(errors).toHaveLength(1);

        const error = errors[0]?.id0?.name?.error;
        expect(error).toBeInstanceOf(Contena.Classes.ContenaError);
        expect(error.code).toBe('SOME_ERROR_CODE');
        expect(error.selfLink).toBe('custom_field.id0.name.error');
    });

    it('should always have a pagination', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const pagination = wrapper.find('.ct-pagination');
        expect(pagination.exists()).toBe(true);
    });

    it('should have one page initially', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const paginationButtons = wrapper.findAll('.ct-pagination__list-button');
        expect(paginationButtons).toHaveLength(1);
    });

    it('should create new custom field', async () => {
        const wrapper = await createWrapper();

        const newCustomField = {
            id: 'id1337',
            name: 'new_field',
            config: {
                label: { 'en-GB': 'New' },
                customFieldType: 'text',
                customFieldPosition: 0,
            },
        };
        await flushPromises();

        await wrapper.vm.onSaveCustomField(newCustomField);
        await flushPromises();

        // Should have two pagination buttons after add
        const paginationButtons = wrapper.findAll('.ct-pagination__list-button');
        expect(paginationButtons).toHaveLength(2);

        // Should be in grid on correct position
        const expectedRow = wrapper.findAll('.ct-grid .ct-grid__body .ct-grid-row')[0];
        expect(expectedRow.find('.ct-grid-column[data-index="label"]').text()).toBe('New');
    });

    it('should delete custom field', async () => {
        const wrapper = await createWrapper();

        const deleteCustomField = {
            id: 'id0',
            name: 'custom_additional_field_1',
            config: {
                label: { 'en-GB': 'Special field 1' },
                customFieldType: 'checkbox',
                customFieldPosition: 0,
            },
        };

        await flushPromises();

        Object.assign(wrapper.vm, {
            deleteCustomField: deleteCustomField,
        });
        await wrapper.vm.$nextTick();

        await flushPromises();

        await wrapper.vm.onDeleteCustomField();
        await flushPromises();

        const rows = wrapper.findAll('.ct-grid .ct-grid__body .ct-grid-row');
        expect(rows).toHaveLength(9);

        const expectedRow = rows.at(0);
        expect(expectedRow.find('.ct-grid-column[data-index="label"]').text()).toBe('Special field 1');
    });

    it('should sort custom fields by position', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const customFieldPositionCells = wrapper.findAll('.ct-grid-column[data-index="position"]');
        const [
            first,
            second,
            third,
            fourth,
        ] = customFieldPositionCells;

        expect(first.text()).toBe('1');
        expect(second.text()).toBe('2');
        expect(third.text()).toBe('3');
        expect(fourth.text()).toBe('4');
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editMenuItem = wrapper.find('.ct-custom-field-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.ct-custom-field-list__edit-action');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });
});
