import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(isSelectable, tooltip) {
    // mock entity functions
    const items = [
        { name: 'Contena' },
        { name: 'Github' },
        { name: 'PHP' },
        { name: 'VueJS' },
    ];
    items.total = 4;
    items.criteria = {
        page: 1,
        limit: 25,
    };

    return mount(
        await wrapTestComponent('ct-entity-advanced-selection-modal-grid', {
            sync: true,
        }),
        {
            props: {
                isRecordSelectableCallback() {
                    return { isSelectable, tooltip };
                },
                columns: [
                    { property: 'name', label: 'Name' },
                ],
                dataSource: new EntityCollection(
                    null,
                    null,
                    null,
                    new Criteria(1, 25),
                    [
                        { id: 'id1', name: 'item1' },
                        { id: 'id2', name: 'item2' },
                    ],
                    2,
                ),
                repository: {
                    search: () => {},
                },
                detailRoute: 'ct.manufacturer.detail',
            },

            global: {
                stubs: {
                    'ct-entity-listing': await wrapTestComponent('ct-entity-listing'),
                    'ct-data-grid-settings': await wrapTestComponent('ct-data-grid-settings'),
                    'ct-context-button': await wrapTestComponent('ct-context-button'),
                    'ct-context-menu-divider': true,
                    'ct-pagination': await wrapTestComponent('ct-pagination'),
                    'ct-context-menu-item': await wrapTestComponent('ct-context-menu-item'),
                    'ct-field-error': await wrapTestComponent('ct-field-error'),
                    'ct-base-field': await wrapTestComponent('ct-base-field'),
                    'ct-bulk-edit-modal': true,
                    'ct-data-grid-column-boolean': true,
                    'ct-data-grid-inline-edit': true,
                    'router-link': true,
                    'ct-data-grid-skeleton': true,
                    'ct-context-menu': true,
                    'mt-floating-ui': {
                        template: '<div><slot /></div>',
                    },
                    'ct-button-group': true,
                    'ct-loader': true,
                    'ct-select-field': true,
                    'ct-inheritance-switch': true,
                    'ct-ai-copilot-badge': true,
                    'ct-help-text': true,
                    'ct-provide': { template: '<slot/>', inheritAttrs: false },
                },
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
            },
        },
    );
}

describe('src/app/component/entity/ct-entity-advanced-selection-modal-grid', () => {
    it('should disable all checkboxes with enabled tooltip', async () => {
        const wrapper = await createWrapper(false, {
            message: 'test message',
            disabled: false,
        });
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.ct-data-grid__row--1').findComponent('.mt-field--checkbox__container');

        expect(firstRowCheckbox.props().disabled).toBe(true);

        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('false');
    });

    it('should enable all checkboxes', async () => {
        const wrapper = await createWrapper(true);
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.ct-data-grid__row--1').findComponent('.mt-field--checkbox__container');

        expect(firstRowCheckbox.props().disabled).toBe(false);
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });

    it('should disable all checkboxes with disabled tooltip', async () => {
        const wrapper = await createWrapper(false, {
            message: 'test message',
            disabled: true,
        });
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.ct-data-grid__row--1').findComponent('.mt-field--checkbox__container');

        expect(firstRowCheckbox.props().disabled).toBe(true);
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });
});
