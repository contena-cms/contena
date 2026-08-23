import { mount } from '@vue/test-utils';

import EntityCollection from 'src/core/data/entity-collection.data';

const fixture = [
    {
        id: 'ae12b3c2-8236-4eb2-84a1-b933863a7905',
        name: 'first entry',
        variation: [{ group: 'Size', option: 'M' }],
    },
];

const propertyFixture = [
    {
        id: '46a40e8d-671b-4c91-b0c7-cecee1bdea4a',
        name: 'first entry',
        group: {
            name: 'example',
        },
    },
    {
        id: 'c8637a67-cf98-4533-ac42-48513b7cb96f',
        name: 'second entry',
        group: {
            name: 'example',
        },
    },
    {
        id: '4eed437b-b242-418e-be58-b3fa3f2d15f9',
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

const createWrapper = async (customOptions = {}) => {
    const wrapper = mount(await wrapTestComponent('ct-entity-multi-select', { sync: true }), {
        global: {
            stubs: {
                'ct-select-base': await wrapTestComponent('ct-select-base', { sync: true }),
                'ct-block-field': await wrapTestComponent('ct-block-field', { sync: true }),
                'ct-base-field': await wrapTestComponent('ct-base-field', { sync: true }),
                'ct-select-selection-list': await wrapTestComponent('ct-select-selection-list', { sync: true }),
                'ct-field-error': await wrapTestComponent('ct-field-error', { sync: true }),
                'ct-loader': await wrapTestComponent('ct-loader', { sync: true }),
                'ct-select-result-list': await wrapTestComponent('ct-select-result-list', { sync: true }),
                'ct-select-result': await wrapTestComponent('ct-select-result', { sync: true }),
                'ct-highlight-text': await wrapTestComponent('ct-highlight-text', { sync: true }),
                'ct-label': await wrapTestComponent('ct-label', { sync: true }),
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'ct-color-badge': true,
                'mt-loader': true,
                'mt-floating-ui': {
                    template: '<div><slot /></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => Promise.resolve(getCollection()),
                        };
                    },
                },
                ...customOptions.global?.provide,
            },
        },
        props: {
            entity: 'test',
            entityCollection: getCollection(),
            showClearableButton: true,
            ...customOptions.props,
        },
        slots: customOptions?.slots,
    });

    await flushPromises();

    return wrapper;
};

describe('components/ct-entity-multi-select', () => {
    it('should emit the correct search term', async () => {
        const swEntityMultiSelect = await createWrapper({
            props: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection(),
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(getPropertyCollection()),
                        };
                    },
                },
            },
        });

        await swEntityMultiSelect.find('.ct-select__selection').trigger('click');
        await flushPromises();

        await swEntityMultiSelect.find('input').setValue('first');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        expect(swEntityMultiSelect.emitted('search-term-change')[0]).toEqual([
            'first',
        ]);
    });

    it('should show description line in results list', async () => {
        const wrapper = await createWrapper({
            slots: {
                'result-label-property': `<template>
                        {{ item.name }}
                    </template>`,
                'result-description-property': `<template>
                        {{ item.group.name }}
                    </template>`,
            },
            props: {
                entity: 'property_group_option',
                entityCollection: getPropertyCollection(),
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

        await wrapper.find('.ct-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        const firstListEntry = wrapper.findAll('.ct-select-result-list__item-list li').at(0);

        expect(firstListEntry.classes()).toContain('has--description');
        expect(firstListEntry.find('.ct-select-result__result-item-text').text()).toBe('first entry');
        expect(firstListEntry.find('.ct-select-result__result-item-description').text()).toBe('example');
    });

    it('should render select indicator', async () => {
        const swEntityMultiSelect = await createWrapper({
            props: {
                entity: 'test',
                entityCollection: new EntityCollection(
                    '/property-group-option',
                    'property_group_option',
                    null,
                    { isContenaContext: true },
                    [getPropertyCollection().at(0)],
                    1,
                    null,
                ),
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

        await swEntityMultiSelect.find('.ct-select__selection').trigger('click');
        await swEntityMultiSelect.find('input').trigger('change');
        await flushPromises();

        expect(swEntityMultiSelect.find('.ct-select-result-list__item-list li .mt-icon')).toBeDefined();
    });

    it('should be possible to clear the selection', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.ct-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');
        await flushPromises();

        await wrapper.find('.ct-select__select-indicator-clear').trigger('click');
        expect(wrapper.emitted('update:entityCollection')[0][0].total).toBeNull();
    });
});
