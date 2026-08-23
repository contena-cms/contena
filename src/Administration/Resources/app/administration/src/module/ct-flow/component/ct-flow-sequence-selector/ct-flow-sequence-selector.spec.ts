import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-flow/component/ct-flow-sequence-selector', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () => ({ addPrivilegeMappingEntry: jest.fn() }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('uses branch-specific help text while preserving upstream controls', async () => {
        const component = (await import('./ct-flow-sequence-selector.vue')).default;
        const wrapper = mount(component, {
            props: { rootIndex: 0, branch: 'true' },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                },
            },
        });

        expect(wrapper.find('.ct-flow-sequence-selector__help-text').text()).toContain('ct-flow.sequence.selectorTrueHelp');
        wrapper.unmount();
    });
});
