import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/base/ct-select-selection-list';
import 'src/app/component/base/ct-label';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('ct-select-selection-list', { sync: true }), {
        global: {
            stubs: {
                'ct-label': {
                    template: '<div class="ct-label"><slot></slot></div>',
                },
            },
        },
        propsData: {
            ...propsData,
        },
    });
}

describe('src/app/component/form/select/base/ct-select-selection-list', () => {
    it('should render dismissable labels', async () => {
        const wrapper = await createWrapper({
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.ct-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBe('true');
    });

    it('should pass autocomplete attribute to input', async () => {
        const wrapper = await createWrapper({
            autocomplete: 'off',
        });

        const input = wrapper.find('.ct-select-selection-list__input');
        expect(input.attributes('autocomplete')).toBe('off');
    });

    it('should not render autocomplete attribute by default', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('.ct-select-selection-list__input');
        expect(input.attributes('autocomplete')).toBeUndefined();
    });

    it('should render labels which are not dismissable', async () => {
        const wrapper = await createWrapper({
            disabled: true,
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.ct-label');
        expect(element.exists()).toBeTruthy();
        if (element.attributes().hasOwnProperty('dismissable')) {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(element.attributes().dismissable).toBe('false');
        } else {
            // eslint-disable-next-line jest/no-conditional-expect
            expect(element.attributes().dismissable).toBeFalsy();
        }
    });
});
