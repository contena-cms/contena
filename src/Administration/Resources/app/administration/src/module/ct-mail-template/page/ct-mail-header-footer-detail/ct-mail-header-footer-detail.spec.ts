import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/page/ct-mail-header-footer-detail', () => {
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

    it.each([
        [
            'disables',
            false,
        ],
        [
            'enables',
            true,
        ],
    ])('%s all fields based on edit permission', async (_label, canEdit) => {
        const item = {
            id: 'header-footer-id',
            name: 'Contena notification',
            description: 'Shared notification header and footer',
            headerHtml: '<header>Contena</header>',
            headerPlain: 'Contena',
            footerHtml: '<footer>Contena</footer>',
            footerPlain: 'Contena',
            isNew: jest.fn(() => false),
        } as unknown as Entity<'mail_header_footer'>;
        const repository = {
            get: jest.fn(() => Promise.resolve(item)),
            save: jest.fn(() => Promise.resolve()),
        };
        const textField = defineComponent({
            name: 'MtTextField',
            props: [
                'modelValue',
                'disabled',
            ],
            template: '<input class="text-field" :disabled="disabled" />',
        });
        const textarea = defineComponent({
            name: 'MtTextarea',
            props: [
                'modelValue',
                'disabled',
            ],
            template: '<textarea class="textarea" :disabled="disabled" />',
        });
        const codeEditor = defineComponent({
            name: 'CtCodeEditor',
            props: [
                'value',
                'disabled',
            ],
            template: '<textarea class="code-editor" :disabled="disabled" />',
        });

        const wrapper = mount(await wrapTestComponent('ct-mail-header-footer-detail', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: { params: { id: item.id } },
                    [routerKey as symbol]: { push: jest.fn(), replace: jest.fn() },
                    repositoryFactory: { create: jest.fn(() => repository) },
                    acl: { can: jest.fn(() => canEdit) },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({
                        template: '<div><slot name="content" /><slot name="smart-bar-actions" /></div>',
                    }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': defineComponent({ template: '<section><slot /></section>' }),
                    'mt-text-field': textField,
                    'mt-textarea': textarea,
                    'ct-code-editor': codeEditor,
                    'ct-language-info': true,
                    'mt-button': true,
                    'ct-button-process': true,
                    'ct-language-switch': true,
                    'ct-skeleton': true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.findAll('.text-field')).toHaveLength(1);
        expect(wrapper.findAll('.textarea')).toHaveLength(1);
        expect(wrapper.findAll('.code-editor')).toHaveLength(4);
        expect(
            wrapper.findAll('.text-field, .textarea, .code-editor').every((field) => field.attributes('disabled') === ''),
        ).toBe(!canEdit);
    });
});
