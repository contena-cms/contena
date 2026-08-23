import { defineComponent } from 'vue';
import { shallowMount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/view/ct-mail-template-view-templates', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('renders the list and forwards getList to its load method', async () => {
        const getList = jest.fn(() => Promise.resolve());
        const wrapper = shallowMount(await wrapTestComponent('ct-mail-template-view-templates', { sync: true }), {
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-mail-template-list': defineComponent({
                        name: 'ct-mail-template-list',
                        setup(_, { expose }) {
                            expose({ getList });
                            return {};
                        },
                        template: '<div class="mail-template-list" />',
                    }),
                },
            },
        });

        expect(wrapper.find('.mail-template-list').exists()).toBe(true);
        (wrapper.vm as unknown as { getList: () => void }).getList();
        expect(getList).toHaveBeenCalledTimes(1);
    });
});
