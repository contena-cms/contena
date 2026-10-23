/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

// from Defaults.php
const FRONTEND_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const HEADLESS_TYPE_ID = 'f183ee5650cf4bdb8a774337575067a6';
const BLOG_COMPARISON_TYPE_ID = 'ed535e5722134ac1aa6524f73e26881b';
const AGENTIC_COMMERCE_TYPE_ID = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

const FRONTEND_CHANNEL_ID = '863137935ecf48999d69096de547b090';
const HEADLESS_CHANNEL_ID = 'headless-sales-channel-id';
const HEADLESS_NO_DOMAIN_CHANNEL_ID = 'headless-no-domain-sales-channel-id';
const BLOG_COMPARISON_CHANNEL_ID = 'blog-comparison-sales-channel-id';
const AGENTIC_COMMERCE_CHANNEL_ID = 'agentic-commerce-sales-channel-id';

const FK = '4066b6039fcf41f089bdf859cc6ce662';
const LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b'; // Contena.Context.api.languageId in tests

const HEADLESS_DOMAIN = 'https://headless.example.com';

const CHANNELS = [
    { id: FRONTEND_CHANNEL_ID, typeId: FRONTEND_TYPE_ID },
    {
        id: HEADLESS_CHANNEL_ID,
        typeId: HEADLESS_TYPE_ID,
        domains: [{ isExternalFrontend: true, languageId: LANGUAGE_ID, url: HEADLESS_DOMAIN }],
    },
    { id: HEADLESS_NO_DOMAIN_CHANNEL_ID, typeId: HEADLESS_TYPE_ID, domains: [{ isExternalFrontend: false }] },
    { id: BLOG_COMPARISON_CHANNEL_ID, typeId: BLOG_COMPARISON_TYPE_ID },
    { id: AGENTIC_COMMERCE_CHANNEL_ID, typeId: AGENTIC_COMMERCE_TYPE_ID },
];

function createEntityCollection(entities = []) {
    return new Contena.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function setSalesChannels(channels = CHANNELS) {
    Contena.Store.get('swSeoUrl').channelCollection = createEntityCollection(channels);
}

function seoUrl(overrides = {}) {
    return {
        id: 'c0221c1f712a4f369a79e924a10fa398',
        foreignKey: FK,
        languageId: '12345678',
        pathInfo: `/detail/${FK}`,
        routeName: 'frontend.detail.page',
        channelId: null,
        seoPathInfo: 'Awesome-blog/',
        ...overrides,
    };
}

function expectedCurrentSeoUrl({ routeName, pathInfo, channelId, foreignKey = FK }) {
    return { foreignKey, isCanonical: true, languageId: LANGUAGE_ID, pathInfo, routeName, channelId, isModified: true };
}

async function createWrapper() {
    return mount(await wrapTestComponent('sw-seo-url', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'mt-card': { template: '<div><slot name="toolbar"></slot></div>' },
                'sw-sales-channel-switch': true,
                'sw-text-field': true,
                'sw-inherit-wrapper': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        search: () =>
                            Promise.resolve(entity === 'channel' ? createEntityCollection(CHANNELS) : []),
                        create: () => ({}),
                        schema: { entity: {} },
                    }),
                },
            },
        },
    });
}

describe('src/module/sw-settings-seo/component/sw-seo-url', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        Contena.Store.get('swSeoUrl').currentSeoUrl = '';
    });

    it('sales channel switch should not be disabled', async () => {
        await wrapper.setData({ showEmptySeoUrlError: false });

        expect(wrapper.find('sw-sales-channel-switch-stub').attributes().disabled).toBeUndefined();
    });

    it('sales channel switch should be disabled', async () => {
        wrapper.vm.showEmptySeoUrlError = false;
        await wrapper.setProps({ disabled: true });

        expect(wrapper.find('sw-sales-channel-switch-stub').attributes().disabled).toBe('true');
    });

    it('should update currentSeoUrl when defaultSeoUrl empty', async () => {
        await wrapper.setProps({
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    channelId: FRONTEND_CHANNEL_ID,
                    seoPathInfo: 'Computers/',
                }),
            ],
            channelId: FRONTEND_CHANNEL_ID,
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: FRONTEND_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.navigation.page',
                pathInfo: `/navigation/${FK}`,
                channelId: FRONTEND_CHANNEL_ID,
            }),
        );
    });

    it('should update currentSeoUrl when defaultSeoUrl empty and the channel has no seo urls yet', async () => {
        await wrapper.setProps({
            // the seo url belongs to another sales channel, so no default seo url exists for the selected one
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    channelId: FK,
                    seoPathInfo: 'Computers/',
                }),
            ],
            channelId: 'a-sales-channel-without-seo-urls',
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: FRONTEND_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual({});
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.navigation.page',
                pathInfo: `/navigation/${FK}`,
                channelId: FRONTEND_CHANNEL_ID,
            }),
        );
    });

    it.each([['seo/url%/1'], ['foo/bar#baz'], ['foo\\bar']])(
        'reports a validation error for disallowed character in "%s"',
        async (invalidPath) => {
            Contena.Store.get('swSeoUrl').currentSeoUrl = {
                seoPathInfo: invalidPath,
            };

            expect(wrapper.vm.seoPathInfoError).toEqual(
                expect.objectContaining({ code: 'CONTENT__SEO_URL_INVALID_CHARACTERS' }),
            );
        },
    );

    it.each([
        ['Computers/Laptops'],
        ['Pepper-white-ground-pearl/SW10098'],
        ['foo/bar?x=1'],
        ['caf%C3%A9/SW10098'],
        // headless channels store absolute URLs; they must not be flagged either
        ['https://example.com/blog'],
        [''],
        [null],
    ])('accepts "%s" as SEO path', async (validPath) => {
        Contena.Store.get('swSeoUrl').currentSeoUrl = {
            seoPathInfo: validPath,
        };

        expect(wrapper.vm.seoPathInfoError).toBeNull();
    });

    it('should update currentSeoUrl when defaultSeoUrl not empty', async () => {
        const defaultSeoUrl = {
            id: '123456789',
            foreignKey: '12345678910111213',
            languageId: '1234567891011',
            pathInfo: '/navigation/123456789',
            routeName: 'frontend.blog-detail.page',
            channelId: null,
            seoPathInfo: 'Blog-detail/',
        };

        await wrapper.setProps({
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    channelId: FRONTEND_CHANNEL_ID,
                    seoPathInfo: 'Computers/',
                }),
                defaultSeoUrl,
            ],
            channelId: FRONTEND_CHANNEL_ID,
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: FRONTEND_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.defaultSeoUrl).toEqual(defaultSeoUrl);
        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                foreignKey: '12345678910111213',
                routeName: 'frontend.blog-detail.page',
                pathInfo: '/navigation/123456789',
                channelId: FRONTEND_CHANNEL_ID,
            }),
        );
    });

    it.each([
        ['frontend', FRONTEND_CHANNEL_ID, false],
        ['headless', HEADLESS_CHANNEL_ID, false],
        ['blog comparison', BLOG_COMPARISON_CHANNEL_ID, true],
        ['agentic commerce', AGENTIC_COMMERCE_CHANNEL_ID, true],
        ['none selected', null, false],
    ])('should flag SEO URL support for a %s sales channel', async (_, channelId, unsupported) => {
        setSalesChannels();

        wrapper.vm.currentSalesChannelId = channelId;

        expect(wrapper.vm.isUnsupportedSalesChannel).toBe(unsupported);
    });

    it('should only expose the not-supported help text for unsupported sales channel types', async () => {
        setSalesChannels();

        // headless is allowed and shows no help text, blog comparison is not
        wrapper.vm.currentSalesChannelId = HEADLESS_CHANNEL_ID;
        expect(wrapper.vm.seoUrlHelptext).toBeNull();

        wrapper.vm.currentSalesChannelId = BLOG_COMPARISON_CHANNEL_ID;
        expect(wrapper.vm.seoUrlHelptext).toBe('sw-seo-url.textSeoUrlsNotSupported');
    });

    it('should not flag a relative seo path for a headless sales channel', async () => {
        setSalesChannels();
        Contena.Store.get('swSeoUrl').currentSeoUrl = { seoPathInfo: 'some/relative/path' };

        wrapper.vm.currentSalesChannelId = HEADLESS_CHANNEL_ID;

        // headless templates may be relative (resolved against the sales channel domain), so no error
        expect(wrapper.vm.seoPathInfoError).toBeNull();
    });

    it('does not map the frontend fallback url for a frontend sales channel', async () => {
        setSalesChannels();

        await wrapper.setProps({
            urls: [
                seoUrl({
                    pathInfo: `/navigation/${FK}`,
                    routeName: 'frontend.navigation.page',
                    channelId: FK,
                    seoPathInfo: 'Computers/',
                }),
            ],
            channelId: FRONTEND_CHANNEL_ID,
        });
        await wrapper.setData({ showEmptySeoUrlError: false, currentSalesChannelId: FRONTEND_CHANNEL_ID });
        await wrapper.vm.$nextTick();

        await wrapper.vm.refreshCurrentSeoUrl();

        expect(wrapper.vm.currentSeoUrl).toEqual(
            expectedCurrentSeoUrl({
                routeName: 'frontend.navigation.page',
                pathInfo: `/navigation/${FK}`,
                channelId: FRONTEND_CHANNEL_ID,
            }),
        );
    });

    it('shows the external-frontend requirement help text and disables input for a headless channel without a matching domain', async () => {
        setSalesChannels();

        wrapper.vm.currentSalesChannelId = HEADLESS_NO_DOMAIN_CHANNEL_ID;

        expect(wrapper.vm.headlessExternalFrontendUrl).toBeNull();
        expect(wrapper.vm.seoUrlHelptext).toBe('sw-seo-url-template-card.general.textExternalFrontendRequired');
        expect(wrapper.vm.allowInput).toBe(false);
    });

    it('exposes the external frontend domain with a trailing slash as prefix for a headless sales channel', async () => {
        setSalesChannels();

        wrapper.vm.currentSalesChannelId = HEADLESS_CHANNEL_ID;

        expect(wrapper.vm.headlessExternalFrontendUrl).toBe(`${HEADLESS_DOMAIN}/`);
        expect(wrapper.vm.seoUrlHelptext).toBeNull();
        expect(wrapper.vm.allowInput).toBe(true);
    });

    it('does not require an external frontend domain for a non-headless sales channel', async () => {
        setSalesChannels();

        wrapper.vm.currentSalesChannelId = FRONTEND_CHANNEL_ID;

        expect(wrapper.vm.headlessExternalFrontendUrl).toBeNull();
        expect(wrapper.vm.seoUrlHelptext).toBeNull();
        expect(wrapper.vm.allowInput).toBe(true);
    });
});
