import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

describe('src/module/ct-extension/page/ct-extension-config.spec', () => {
    let SwExtensionConfig;

    async function createWrapper(props = {}) {
        return mount(SwExtensionConfig, {
            global: {
                mocks: {
                    $route: {
                        meta: {
                            $module: null,
                        },
                    },
                },
                stubs: {
                    'ct-meteor-page': await wrapTestComponent('ct-meteor-page', { sync: true }),
                    'ct-system-config': await wrapTestComponent('ct-system-config', { sync: true }),
                    'ct-extension-icon': await wrapTestComponent('ct-extension-icon', { sync: true }),
                    'ct-external-link': {
                        template: '<a><slot></slot></a>',
                    },
                    'ct-search-bar': true,
                    'ct-notification-center': true,
                    'ct-help-center-v2': true,
                    'ct-meteor-navigation': true,
                    'ct-channel-switch': true,

                    'ct-form-field-renderer': true,
                    'ct-inherit-wrapper': true,
                    'ct-app-topbar-button': true,
                    'ct-app-topbar-sidebar': true,
                    'ct-ai-copilot-badge': true,
                },
                provide: {
                    contenaExtensionService: {
                        updateExtensionData: jest.fn(),
                    },
                    systemConfigApiService: {
                        getValues: () => {
                            return Promise.resolve({
                                'core.store.apiUri': 'https://api.contena.cn',
                                'core.store.licenseHost': 'sw6.test.contena.in',
                                'core.system.instanceSecret': 'very.s3cret',
                                'core.store.contenaId': 'max@muster.com',
                            });
                        },
                    },
                },
            },
            props: {
                namespace: 'MyExtension',
                ...props,
            },
            data() {
                return { extension: null };
            },
        });
    }

    beforeAll(async () => {
        SwExtensionConfig = await wrapTestComponent('ct-extension-config', {
            sync: true,
        });
    });

    beforeEach(async () => {
        setActivePinia(createPinia());
    });

    it('domain should suffix config', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.domain).toBe('MyExtension.config');
    });

    it('should reload extensions on createdComponent', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.contenaExtensionService.updateExtensionData).toHaveBeenCalledTimes(1);
    });

    it('should not reload extensions on createdComponent if extensions are loaded', async () => {
        Contena.Store.get('contenaExtensions').setMyExtensions([{ name: 'test-extension' }]);
        const wrapper = await createWrapper();

        expect(wrapper.vm.contenaExtensionService.updateExtensionData).toHaveBeenCalledTimes(0);
    });

    it('Save click success', async () => {
        const wrapper = await createWrapper();

        const saveAllMock = jest.fn(() => Promise.resolve());
        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);
        wrapper.vm.$refs.systemConfig.saveAll = saveAllMock;

        await wrapper.get('.ct-extension-config__save-action').trigger('click');

        expect(saveAllMock).toHaveBeenCalled();
        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'success' }));
    });

    it('Save click error', async () => {
        const wrapper = await createWrapper();

        const notificationSpy = jest
            .spyOn(Contena.Store.get('notification'), 'createNotification')
            .mockImplementation(() => null);

        wrapper.vm.$refs.systemConfig.saveAll = jest.fn(() => Promise.reject());

        await wrapper.find('.ct-extension-config__save-action').trigger('click');

        expect(notificationSpy).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
    });

    it('shows default header', async () => {
        const wrapper = await createWrapper();

        const iconComponent = wrapper.get('.ct-extension-config__extension-icon img');
        expect(iconComponent.attributes().src).toBe(
            'administration/administration/static/img/services/extension-icon-placeholder.svg',
        );
        expect(iconComponent.attributes().alt).toBe('ct-extension.component.ct-extension-config.imageDescription');

        const title = wrapper.get('.ct-meteor-page__smart-bar-title');
        expect(title.text()).toBe('MyExtension');

        const meta = wrapper.get('.ct-meteor-page__smart-bar-meta');
        expect(meta.text()).toBe('');
    });

    it('shows header for extension details', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.extension = {
            icon: 'icon.png',
            label: 'My extension label',
            producerName: 'contena AG',
        };

        await wrapper.vm.$nextTick();
        const iconComponent = wrapper.get('.ct-extension-icon img');
        expect(iconComponent.attributes().src).toBe('icon.png');
        expect(iconComponent.attributes().alt).toBe('ct-extension.component.ct-extension-config.imageDescription');

        const title = wrapper.get('.ct-meteor-page__smart-bar-title');
        expect(title.text()).toBe('My extension label');

        const meta = wrapper.get('.ct-meteor-page__smart-bar-meta');
        expect(meta.text()).toBe('ct-extension.component.ct-extension-config.labelBy contena AG');
    });

    it('shows header for extension details with producer website', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.extension = {
            producerName: 'contena AG',
            producerWebsite: 'https://www.contena.cn/',
        };

        await wrapper.vm.$nextTick();
        const meta = wrapper.get('.ct-meteor-page__smart-bar-meta');
        expect(meta.text()).toContain('ct-extension.component.ct-extension-config.labelBy');

        const metaLink = wrapper.get('.ct-extension-config__producer-link');
        expect(metaLink.attributes().href).toBe('https://www.contena.cn/');
        expect(metaLink.text()).toBe('contena AG');
    });

    it('saves from route when router navigates to ct-extension-config page', async () => {
        const fromRoute = {
            name: 'from.route.name',
        };
        const wrapper = await createWrapper({ fromLink: fromRoute });

        expect(wrapper.props('fromLink')).toEqual(fromRoute);
    });

    it('does not expose route props from the original extendable setup', async () => {
        const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});

        const wrapper = await createWrapper({ fromLink: { name: 'from.route.name' } });

        expect(wrapper.find('.ct-extension-config__content').exists()).toBe(true);
        expect(consoleErrorSpy).not.toHaveBeenCalledWith(
            expect.stringContaining('The original setup function for the originalComponent component returned a prop'),
        );

        consoleErrorSpy.mockRestore();
    });
});
