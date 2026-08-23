import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import { createI18n } from 'vue-i18n';
import { createMemoryHistory, createRouter } from 'vue-router';
import { usePageTitle } from './use-page-title';

describe('src/app/composables/use-page-title', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
        document.title = '';
    });

    it('keeps the Administration page title reactive without component options', async () => {
        const identifier = ref('Snippet');
        const component = defineComponent({
            setup() {
                usePageTitle(identifier);

                return { identifier };
            },
            template: '<div />',
        });
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                {
                    path: '/',
                    component,
                    meta: { $module: { title: 'module.title' } },
                },
            ],
        });
        const i18n = createI18n({
            legacy: false,
            locale: 'en-GB',
            messages: {
                'en-GB': {
                    global: { 'ct-admin-menu': { textContenaAdmin: 'Contena Administration' } },
                    module: { title: 'Settings' },
                },
            },
        });
        await router.push('/');

        wrapper = mount(component, {
            global: {
                plugins: [
                    router,
                    i18n,
                ],
            },
        });
        expect(document.title).toBe('Snippet | Settings | Contena Administration');

        identifier.value = 'Translations';
        await wrapper.vm.$nextTick();

        expect(document.title).toBe('Translations | Settings | Contena Administration');
    });
});
