import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-flow/component/ct-flow-mail-send-modal', () => {
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

    it('normalizes custom recipients into the flow mail configuration', async () => {
        const wrapper = mount(await wrapTestComponent('ct-flow-mail-send-modal', { sync: true }), {
            props: {
                config: {
                    mailTemplateId: '019fc5b5ad1f7a659e3eea39f1000002',
                    recipient: { type: 'custom', data: { 'old@example.com': null } },
                },
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-entity-select': true,
                    'mt-select': true,
                    'mt-textarea': true,
                    'mt-button': true,
                },
            },
        });
        const modal = wrapper.vm as unknown as {
            customRecipients: string;
            onSave: () => void;
        };
        modal.customRecipients = 'first@example.com, second@example.com';

        modal.onSave();

        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            mailTemplateId: '019fc5b5ad1f7a659e3eea39f1000002',
            recipient: {
                type: 'custom',
                data: {
                    'first@example.com': null,
                    'second@example.com': null,
                },
            },
        });
    });
});
