import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

const channelSwitchStub = {
    name: 'SwChannelSwitchStub',
    props: ['disabled'],
    template: '<div class="ct-channel-switch-stub" />',
};

type SeoUrlVm = {
    seoPathInfoError: { code: string } | null;
};

function setCurrentSeoPathInfo(seoPathInfo: string | null): void {
    Contena.Store.get('swSeoUrl').currentSeoUrl = {
        createdAt: '2026-08-17T00:00:00.000+00:00',
        id: 'default-seo-url',
        languageId: Contena.Defaults.systemLanguageId,
        foreignKey: 'blog-1',
        routeName: 'frontend.blog.detail.page',
        pathInfo: '/blog/blog-1',
        seoPathInfo,
    } as EntitySchema.Entities['seo_url'];
}

function createWrapper(props: Record<string, unknown> = {}): VueWrapper<SeoUrlVm> {
    return shallowMount(component, {
        props: {
            urls: [
                {
                    id: 'default-seo-url',
                    foreignKey: 'blog-1',
                    languageId: Contena.Defaults.systemLanguageId,
                    pathInfo: '/blog/blog-1',
                    routeName: 'frontend.blog.detail.page',
                    channelId: null,
                    seoPathInfo: 'blog-1',
                },
            ],
            ...props,
        },
        global: {
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div><slot name="toolbar" /></div>' },
                'mt-text-field': true,
                'ct-channel-switch': channelSwitchStub,
                'ct-inherit-wrapper': {
                    template:
                        '<div><slot name="content" :is-inherit-field="false" :is-inherited="false" :current-value="null" /></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: jest.fn(() => ({
                        route: '/seo-url',
                        schema: { entity: 'seo_url' },
                        create: jest.fn(() => ({})),
                        search: jest.fn().mockResolvedValue([]),
                    })),
                },
            },
        },
    }) as unknown as VueWrapper<SeoUrlVm>;
}

describe('module/ct-settings-seo/component/ct-seo-url', () => {
    afterEach(() => {
        const seoStore = Contena.Store.get('swSeoUrl');
        seoStore.currentSeoUrl = null;
    });

    it('keeps the Channel switch enabled by default', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.findComponent(channelSwitchStub).props('disabled')).toBeUndefined();
    });

    it('forwards a disabled state to the Channel switch', async () => {
        const wrapper = createWrapper({ disabled: true });
        await flushPromises();

        expect(wrapper.findComponent(channelSwitchStub).props('disabled')).toBe(true);
    });

    it.each([
        'seo/url%/1',
        'foo/bar#baz',
        'foo\\bar',
    ])('rejects disallowed SEO path characters in %s', async (path) => {
        const wrapper = createWrapper();
        await flushPromises();
        setCurrentSeoPathInfo(path);

        expect(wrapper.vm.seoPathInfoError).toEqual(
            expect.objectContaining({ code: 'CONTENT__SEO_URL_INVALID_CHARACTERS' }),
        );
    });

    it.each([
        'Computers/Laptops',
        'foo/bar?x=1',
        'caf%C3%A9/SW10098',
        '',
        null,
    ])('accepts %s as an SEO path', async (path) => {
        const wrapper = createWrapper();
        await flushPromises();
        setCurrentSeoPathInfo(path);

        expect(wrapper.vm.seoPathInfoError).toBeNull();
    });
});
