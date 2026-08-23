import { flushPromises, mount } from '@vue/test-utils';
import component from './ct-extension-store-landing-page';

describe('ct-extension-store-landing-page', () => {
    it('exports a renderable SFC component configuration', () => {
        expect(component._renderedBySfcTemplate).toBe(true);
        expect(component.render).toEqual(expect.any(Function));
    });

    async function createWrapper(service = { installAndActivateExtension: jest.fn() }) {
        const LandingPage = await wrapTestComponent('ct-extension-store-landing-page', { sync: true });

        return mount(LandingPage, {
            global: {
                mocks: {
                    $t: (key: string) => key,
                    $createTitle: () => 'Extension store',
                },
                provide: {
                    contenaExtensionService: service,
                },
                stubs: {
                    'ct-block': {
                        props: [
                            'name',
                            'data',
                        ],
                        template: '<div class="ct-block"><slot /></div>',
                    },
                    'ct-page': {
                        template: '<div><slot name="smart-bar-header" /><slot name="content" /></div>',
                    },
                    'mt-button': {
                        props: ['isLoading'],
                        template: '<button :disabled="isLoading" @click="$emit(\'click\')"><slot /></button>',
                    },
                    'mt-icon': true,
                    'mt-loader': true,
                },
            },
        });
    }

    it('keeps the landing card inside the page content and exposes extension blocks', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-extension-store-landing-page__wrapper').exists()).toBe(true);
        expect(wrapper.find('.ct-extension-store-landing-page__illustration').exists()).toBe(true);
        expect(wrapper.findAll('.ct-block').length).toBeGreaterThan(4);
        expect(wrapper.find('button').text()).toBe('ct-extension.store.install');
    });

    it('shows the provider error and allows a retry after activation fails', async () => {
        const service = {
            installAndActivateExtension: jest.fn().mockRejectedValue({
                response: {
                    data: {
                        errors: [{ title: 'Activation failed', detail: 'The store is unavailable.' }],
                    },
                },
            }),
        };
        const wrapper = await createWrapper(service);

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.ct-extension-store-landing-page__status').exists()).toBe(true);
        expect(wrapper.text()).toContain('Activation failed');
        expect(wrapper.text()).toContain('The store is unavailable.');

        const callsBeforeRetry = service.installAndActivateExtension.mock.calls.length;

        await wrapper.find('button').trigger('click');
        expect(service.installAndActivateExtension.mock.calls.length).toBeGreaterThan(callsBeforeRetry);
    });

    it('shows a loading state while the store is being installed', async () => {
        const service = {
            installAndActivateExtension: jest.fn(() => new Promise<void>(() => undefined)),
        };
        const wrapper = await createWrapper(service);

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.ct-extension-store-landing-page__loading').exists()).toBe(true);
        expect(wrapper.text()).toContain('ct-extension.store.activating');
    });
});
