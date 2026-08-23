import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import type PrivilegesService from 'src/app/service/privileges.service';

describe('module/ct-mail-template/page/ct-mail-template-index', () => {
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

    it('shows route tabs and creates templates and headers and footers', async () => {
        const router = {
            push: jest.fn(() => Promise.resolve()),
            replace: jest.fn(() => Promise.resolve()),
        };
        const wrapper = mount(await wrapTestComponent('ct-mail-template-index', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: { name: 'ct.mail.template.index.templates', query: {} },
                    [routerKey as symbol]: router,
                    acl: { can: jest.fn(() => true) },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': defineComponent({
                        template:
                            '<div><slot name="smart-bar-actions" /><slot name="language-switch" /><slot name="content" /></div>',
                    }),
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-language-switch': true,
                    'ct-button-group': defineComponent({ template: '<div class="button-group"><slot /></div>' }),
                    'ct-context-button': defineComponent({
                        template: '<div class="context-button"><slot name="button" /><slot /></div>',
                    }),
                    'ct-context-menu-item': defineComponent({
                        emits: ['click'],
                        template: '<button class="context-menu-item" @click="$emit(\'click\')"><slot /></button>',
                    }),
                    'mt-button': defineComponent({
                        emits: ['click'],
                        template: '<button class="mail-template-button" @click="$emit(\'click\')"><slot /></button>',
                    }),
                    'mt-icon': true,
                    'mt-tabs': defineComponent({
                        props: ['items'],
                        template:
                            '<div class="mail-template-tabs"><button v-for="item in items" :key="item.name" @click="item.onClick">{{ item.label }}</button></div>',
                    }),
                    'router-view': defineComponent({ template: '<div class="router-view" />' }),
                },
            },
        });

        expect(wrapper.find('.mail-template-tabs').exists()).toBe(true);
        expect(wrapper.findAll('.button-group')).toHaveLength(1);
        expect(wrapper.findAll('.mail-template-button')).toHaveLength(2);
        expect(wrapper.findAll('.context-menu-item')).toHaveLength(1);

        const createTemplateButton = wrapper.find('.mail-template-button');
        await createTemplateButton.trigger('click');
        await wrapper.find('.context-menu-item').trigger('click');

        await wrapper.find('.mail-template-tabs button').trigger('click');

        expect(router.push.mock.calls).toEqual([
            [{ name: 'ct.mail.template.create' }],
            [{ name: 'ct.mail.template.create_head_foot' }],
            [{ name: 'ct.mail.template.index.templates' }],
        ]);

        const component = wrapper.vm as unknown as {
            searchType: string;
            onSearch: (term: string) => void;
        };
        expect(component.searchType).toBe('mail_template');
        component.onSearch('recovery');
        expect(router.replace).toHaveBeenCalledWith({
            query: { term: 'recovery', page: undefined },
        });
    });
});
