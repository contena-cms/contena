import { shallowMount } from '@vue/test-utils';
import toolbarComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-toolbar', () => {
    const meteorSelectStub = {
        template: '<div class="meteor-select-stub" :data-small="small" />',
        props: {
            small: {
                type: Boolean,
                default: false,
            },
        },
    };
    const createWrapper = (props: Record<string, unknown> = {}) =>
        shallowMount(toolbarComponent, {
            props,
            global: {
                stubs: {
                    'mt-entity-select': meteorSelectStub,
                    'mt-select': meteorSelectStub,
                },
            },
        });

    it('uses compact Meteor selects in the toolbar', () => {
        const wrapper = createWrapper({ previewEntityType: 'blog' });

        const entitySelects = wrapper.findAll('.meteor-select-stub');

        expect(entitySelects).toHaveLength(2);
        entitySelects.forEach((select) => expect(select.attributes('data-small')).toBe('true'));
    });

    it('uses a compact Meteor select for the empty entity state', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.meteor-select-stub').attributes('data-small')).toBe('true');
    });

    it('hides the entity selector for section layouts', () => {
        const wrapper = createWrapper({ showPreviewEntitySelect: false });

        expect(wrapper.findAll('.meteor-select-stub')).toHaveLength(1);
    });
});
