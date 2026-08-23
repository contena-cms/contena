/* eslint-disable ct-test-rules/test-file-max-lines-warning */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import utils from 'src/core/service/util.service';

const fixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        variation: [{ group: 'Size', option: 'M' }],
        active: true,
    },
    {
        id: utils.createId(),
        name: 'second entry',
        active: false,
    },
    {
        id: utils.createId(),
        name: 'third entry',
        active: true,
    },
];

const propertyFixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        group: {
            name: 'example',
        },
    },
    {
        id: utils.createId(),
        name: 'second entry',
        group: {
            name: 'example',
        },
    },
    {
        id: utils.createId(),
        name: 'third',
        group: {
            name: 'entry',
        },
    },
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isContenaContext: true },
        fixture,
        fixture.length,
        null,
    );
}

function getPropertyCollection() {
    return new EntityCollection(
        '/property-group-option',
        'property_group_option',
        null,
        { isContenaContext: true },
        propertyFixture,
        propertyFixture.length,
        null,
    );
}

async function createEntitySingleSelect(
    customOptions = {
        global: {},
        props: {},
        slots: {},
    },
) {
    const options = {
        global: {
            stubs: {
                'ct-select-base': await wrapTestComponent('ct-select-base'),
                'ct-block-field': await wrapTestComponent('ct-block-field'),
                'ct-base-field': await wrapTestComponent('ct-base-field'),
                'ct-field-error': await wrapTestComponent('ct-field-error'),
                'ct-select-result-list': await wrapTestComponent('ct-select-result-list', {
                    sync: true,
                }),
                'ct-select-result': await wrapTestComponent('ct-select-result'),
                'ct-highlight-text': await wrapTestComponent('ct-highlight-text', {
                    sync: true,
                }),
                'ct-loader': await wrapTestComponent('ct-loader'),
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'mt-loader': true,
                'mt-floating-ui': {
                    template: '<div><slot></slot></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => Promise.resolve([]),
                        };
                    },
                },
            },
            ...customOptions.global,
        },
        props: {
            value: null,
            entity: 'test',
            ...customOptions.props,
        },
        slots: {
            ...customOptions.slots,
        },
    };

    return mount(
        await wrapTestComponent('ct-entity-single-select', {
            sync: true,
        }),
        {
            ...options,
        },
    );
}

describe('components/ct-entity-single-select', () => {
    it('should disable exact count mode per default', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect();
        await flushPromises();

        const criteria = swEntitySingleSelect.vm.criteria;

        expect(criteria).toBeInstanceOf(Object);
        expect(criteria.totalCountMode).toBe(0);
    });

    it('should have no reset option when it is not defined', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
            },
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).toBeNull();
    });

    it('should have disabled state results according to function', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: (item) => item.name === 'second entry',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-select-option--0').classes()).not.toContain('is--disabled');
        expect(wrapper.find('.ct-select-option--1').classes()).toContain('is--disabled');
        expect(wrapper.find('.ct-select-option--2').classes()).not.toContain('is--disabled');
    });

    it('should have no tooltip and enabled results with no disabling function', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const firstEntry = wrapper.find('.ct-select-option--0');
        expect(firstEntry.attributes('tooltip-mock-message')).toBeFalsy();
        expect(firstEntry.attributes('tooltip-mock-disabled')).toBe('true');
        expect(wrapper.find('.ct-select-option--0').classes()).not.toContain('is--disabled');
    });

    it('should update the active result when an option is hovered', async () => {
        const wrapper = await createEntitySingleSelect({
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve(getCollection()),
                        }),
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('.ct-select__selection').trigger('click');
        await flushPromises();

        const results = wrapper.findAll('.ct-select-result-list__item-list li');
        expect(results).toHaveLength(3);

        await results[1].trigger('mouseenter');
        await flushPromises();

        expect(results[1].classes()).toContain('is--active');
    });

    it('should show disabled selection tooltip when appropriate', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                selectionDisablingMethod: (item) => item.name === 'second entry',
                disabledSelectionTooltip: { message: 'test message' },
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const firstEntry = wrapper.find('.ct-select-option--0');
        expect(firstEntry.attributes('tooltip-mock-message')).toBe('test message');
        expect(firstEntry.attributes('tooltip-mock-disabled')).toBe('true');
        const secondEntry = wrapper.find('.ct-select-option--1');
        expect(secondEntry.attributes('tooltip-mock-message')).toBe('test message');
        expect(secondEntry.attributes('tooltip-mock-disabled')).toBe('false');
    });

    it('should show active state of options if enabled', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                shouldShowActiveState: false,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        let activeStateIcons = wrapper.findAll('.ct-entity-single-select__selection-active');

        expect(activeStateIcons).toHaveLength(0);

        await wrapper.setProps({
            shouldShowActiveState: true,
        });
        await flushPromises();

        activeStateIcons = wrapper.findAllComponents('.ct-entity-single-select__selection-active');

        const activeIconProps = {
            color: '#37d046',
            decorative: false,
            mode: 'regular',
            name: 'solid-circle',
            size: '6',
        };

        const inActiveIconProps = {
            color: '#d1d9e0',
            decorative: false,
            mode: 'regular',
            name: 'solid-circle',
            size: '6',
        };

        expect(activeStateIcons).toHaveLength(3);
        expect(activeStateIcons.at(0).props()).toStrictEqual(activeIconProps);
        expect(activeStateIcons.at(1).props()).toStrictEqual(inActiveIconProps);
        expect(activeStateIcons.at(2).props()).toStrictEqual(activeIconProps);

        await wrapper.setProps({
            shouldShowActiveState: false,
        });
        await flushPromises();

        activeStateIcons = wrapper.findAll('.ct-select-option .ct-entity-single-select__selection-active');

        expect(activeStateIcons).toHaveLength(0);
    });

    it('should have a reset option when it is defined an the value is null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'test',
                resetOption: 'reset',
            },
        });
        await flushPromises();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBeNull();
        expect(singleSelection.name).toBe('reset');
    });

    it('should have no reset option when it is defined but the value is not null', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: 'uuid',
                entity: 'test',
                resetOption: 'reset',
            },
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();

        const { singleSelection } = swEntitySingleSelect.vm;

        expect(singleSelection).not.toBeNull();
        expect(singleSelection.id).toBe('uuid');
        expect(singleSelection.name).toBe('uuid');
    });

    it('should have prepend reset option to resultCollection when resetOption is given', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();

        const { resultCollection } = swEntitySingleSelect.vm;

        expect(resultCollection).toHaveLength(getCollection().length + 1);
        expect(resultCollection[0].name).toBe('reset');
    });

    it('should not show the selected item on first entry', async () => {
        const secondItemId = `${fixture[2].id}`;

        const wrapper = await createEntitySingleSelect({
            props: {
                value: secondItemId,
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                                get: (id) => {
                                    if (id === secondItemId) {
                                        return Promise.resolve(fixture[2]);
                                    }

                                    return Promise.reject();
                                },
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-select-option--0').text()).toBe('reset');
        expect(wrapper.find('.ct-select-option--1').text()).toBe('first entry');
        expect(wrapper.find('.ct-select-option--2').text()).toBe('second entry');
        expect(wrapper.find('.ct-select-option--3').text()).toBe('third entry');
    });

    it('should not emit the paginate event when user does not scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const selectResultList = wrapper.findComponent({
            ref: 'resultsList',
        });
        const listContent = wrapper.find('.ct-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', {
            value: 1050,
        });
        Object.defineProperty(listContent.element, 'clientHeight', {
            value: 250,
        });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 150 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBeUndefined();
    });

    it('should emit the paginate event when user scroll to the end of list', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: '',
                entity: 'test',
                resetOption: 'reset',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await wrapper.find('input').trigger('click');
        await flushPromises();

        const selectResultList = wrapper.findComponent({
            ref: 'resultsList',
        });
        const listContent = wrapper.find('.ct-select-result-list__content');

        Object.defineProperty(listContent.element, 'scrollHeight', {
            value: 1050,
        });
        Object.defineProperty(listContent.element, 'clientHeight', {
            value: 250,
        });
        Object.defineProperty(listContent.element, 'scrollTop', { value: 800 });

        await listContent.trigger('scroll');

        expect(selectResultList.emitted('paginate')).toBeDefined();
        expect(selectResultList.emitted('paginate')).toHaveLength(1);
        expect(selectResultList.emitted('paginate')[0]).toEqual([]);
    });

    it('should emit the correct search term', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: null,
                entity: 'property_group_option',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getPropertyCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        swEntitySingleSelect.vm.loadData();
        await swEntitySingleSelect.vm.$nextTick();
        await swEntitySingleSelect.vm.$nextTick();

        await swEntitySingleSelect.find('.ct-select__selection').trigger('click');
        await swEntitySingleSelect.find('input').setValue('first');
        await swEntitySingleSelect.find('input').trigger('change');
        await swEntitySingleSelect.vm.$nextTick();

        expect(swEntitySingleSelect.emitted('search-term-change')[0]).toEqual([
            'first',
        ]);
    });

    it('should display label provided by callback', async () => {
        const swEntitySingleSelect = await createEntitySingleSelect({
            props: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(fixture[0]),
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        await swEntitySingleSelect.vm.$nextTick();
        expect(swEntitySingleSelect.find('.ct-entity-single-select__selection-text').text()).toBe('test');

        await swEntitySingleSelect.find('input').trigger('click');
        await swEntitySingleSelect.vm.$nextTick();

        expect(swEntitySingleSelect.find('input').element.value).toBe('test');
        expect(swEntitySingleSelect.find('.ct-select-result__result-item-text').text()).toBe('test');
    });

    it('should show the clearable icon in the single select', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                showClearableButton: true,
            },
        });
        await flushPromises();

        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        expect(clearableIcon.isVisible()).toBe(true);
    });

    it('should clear the selection when clicking on clear icon', async () => {
        const wrapper = await createEntitySingleSelect({
            props: {
                value: fixture[0].id,
                entity: 'test',
                labelCallback: () => 'test',
                showClearableButton: true,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve(fixture[0]),
                                search: () => Promise.resolve(getCollection()),
                            };
                        },
                    },
                },
            },
        });

        // wait until fetched data gets rendered
        await flushPromises();

        // expect test value selected
        let selectionText = wrapper.find('.ct-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('test');

        // expect no emitted value
        expect(wrapper.emitted('change')).toBeUndefined();

        // click on clear
        const clearableIcon = wrapper.find('.ct-select__select-indicator-clear');
        await clearableIcon.trigger('click');

        // expect emitting resetting value
        const emittedChangeValue = wrapper.emitted('update:value')[0];
        expect(emittedChangeValue).toEqual([null]);

        // emulate v-model change
        await wrapper.setProps({
            value: emittedChangeValue[0],
        });

        // expect empty selection
        selectionText = wrapper.find('.ct-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('');
    });

    it('should show description line in results list', async () => {
        const wrapper = await createEntitySingleSelect({
            slots: {
                'result-label-property': `<span>
                        {{ params.item.name }}
                    </span>`,
                'result-description-property': `<span>
                        {{ params.item.group.name }}
                    </span>`,
            },
            props: {
                value: null,
                entity: 'property_group_option',
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(getPropertyCollection()),
                            };
                        },
                    },
                },
            },
        });
        await flushPromises();

        wrapper.vm.loadData();
        await flushPromises();

        await wrapper.find('.ct-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = wrapper.findAll('.ct-select-result-list__item-list li').at(0);

        expect(firstListEntry.classes()).toContain('has--description');
        expect(firstListEntry.find('.ct-select-result__result-item-text').text()).toBe('first entry');
        expect(firstListEntry.find('.ct-select-result__result-item-description').text()).toBe('example');
    });

    it('should recognize non-existing entity and offer entity creation', async () => {
        const nonExistingEntityMock = new EntityCollection('', '', Contena.Context.api, null, [], 0);

        const existingEntityMock = new EntityCollection(
            '',
            '',
            Contena.Context.api,
            null,
            [
                {
                    id: '12345asd',
                },
            ],
            1,
        );

        const swOriginEntitySingleSelect = await wrapTestComponent('ct-entity-single-select', {
            sync: true,
        });
        const wrapper = mount(swOriginEntitySingleSelect, {
            props: {
                value: 'asdf555',
                entity: 'product_manufacturer',
                allowEntityCreation: true,
            },
            global: {
                stubs: {
                    'ct-select-base': await wrapTestComponent('ct-select-base'),
                    'ct-block-field': await wrapTestComponent('ct-block-field'),
                    'ct-base-field': await wrapTestComponent('ct-base-field'),
                    'ct-select-result-list': await wrapTestComponent('ct-select-result-list'),
                    'ct-highlight-text': await wrapTestComponent('ct-highlight-text', {
                        sync: true,
                    }),
                    'ct-field-error': true,
                    'ct-loader': true,
                    'ct-select-result': {
                        template: '<div><slot></slot></div>',
                    },
                    'ct-inheritance-switch': true,
                    'ct-ai-copilot-badge': true,
                    'ct-help-text': true,
                    'mt-floating-ui': {
                        template: '<div><slot></slot></div>',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => ({
                            search: (context) => {
                                // Should return no manufacturer when component searches for "Cars"
                                if (context.term === 'Cars') {
                                    return Promise.resolve(nonExistingEntityMock);
                                }

                                // Should return one manufacturer when component searches for "Bikes"
                                if (context.term === 'Bikes') {
                                    return Promise.resolve(existingEntityMock);
                                }

                                return Promise.resolve(new EntityCollection('', '', Contena.Context.api, null, [], 0));
                            },
                            get: () =>
                                Promise.resolve({
                                    id: 'manufacturerId',
                                    name: 'ThisIsMyEntity',
                                    product: [],
                                }),
                            create: () => Promise.resolve({}),
                        }),
                    },
                },
            },
        });
        await flushPromises();

        const input = wrapper.find('.ct-entity-single-select__selection-input');

        await wrapper.find('.ct-select__selection').trigger('click');

        // Enter a new search term
        await input.setValue('Cars');

        // Flush debouncedSearch from parent "ct-entity-single-select" component
        await wrapper.vm.debouncedSearch.flush();

        // Wait for rendering
        await flushPromises();
        // Ensure manufacturer does not exist
        expect(wrapper.vm.entityExists).toBe(false);

        // Ensure non-existing manufacturer is offered to be created by a new select result item
        expect(wrapper.vm.newEntityName).toBe('Cars');
        await flushPromises();
        const resultItem = wrapper.find('.ct-select-result-list__item-list').findComponent('.ct-highlight-text');

        expect(resultItem.text()).toBe('global.ct-single-select.labelEntityAdd');
        expect(resultItem.props().searchTerm).toBe('Cars');
    });
});
