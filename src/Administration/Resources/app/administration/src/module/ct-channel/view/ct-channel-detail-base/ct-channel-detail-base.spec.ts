/* eslint-disable ct-deprecation-rules/private-feature-declarations */
/* global EntityCollection */
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';
import type ChannelFavoritesService from '../../service/channel-favorites.service';

type DetailBaseVm = {
    mainCategories: EntityCollection<'category'>;
    footerCategories: EntityCollection<'category'>;
    serviceCategories: EntityCollection<'category'>;
    countryCriteria: InstanceType<typeof Contena.Data.Criteria>;
    languageCriteria: InstanceType<typeof Contena.Data.Criteria>;
    isWebChannel: boolean;
    isDomainAware: boolean;
    unservedLanguages: Array<{ id: string }>;
    unservedLanguageVariant: 'attention' | 'info';
    maintenanceIpAllowlist: string[];
    onMainSelectionAdd: (category: { id: string }) => void;
    onMainSelectionRemove: () => void;
    onFooterSelectionAdd: (category: { id: string }) => void;
    onFooterSelectionRemove: () => void;
    onServiceSelectionAdd: (category: { id: string }) => void;
    onServiceSelectionRemove: () => void;
    onGenerateKey: () => Promise<void>;
    validateMaintenanceIpCidr: (term: string) => boolean;
    isFavorite: () => boolean;
};

function entityCollection<EntityName extends keyof EntitySchema.Entities>(
    entity: EntityName,
    items: Array<Record<string, unknown>> = [],
): EntityCollection<EntityName> {
    return new Contena.Data.EntityCollection(
        `/${entity}`,
        entity,
        Contena.Context.api,
        null,
        items as never[],
        items.length,
    );
}

describe('ct-channel-detail-base', () => {
    const channelFavorites = {
        isFavorite: jest.fn((channelId: string) => channelId === 'channel-id'),
        update: jest.fn(() => Promise.resolve()),
    };
    const generateKey = jest.fn(() => Promise.resolve({ accessKey: 'new-key' }));
    const getKnownIps = jest.fn(() => Promise.resolve([{ name: 'Current request', value: '127.0.0.1' }]));
    const categorySearch = jest.fn((criteria: InstanceType<typeof Contena.Data.Criteria>) => {
        void criteria;
        return Promise.resolve(entityCollection('category'));
    });

    beforeAll(() => {
        Contena.Service().register('timezoneService', () => ({ getTimezoneOptions: () => [] }));
        Contena.Service().register('channelFavorites', () => channelFavorites as unknown as ChannelFavoritesService);
    });

    beforeEach(() => {
        jest.clearAllMocks();
    });

    function createWrapper(
        channelOverrides: Record<string, unknown> = {},
        can = true,
    ): VueWrapper<DetailBaseVm> & { props: (key?: string) => unknown } {
        const channel = {
            id: 'channel-id',
            typeId: Contena.Defaults.webChannelTypeId,
            languageId: 'language-id',
            countryId: 'country-id',
            languages: entityCollection('language', [{ id: 'language-id', name: 'English' }]),
            countries: entityCollection('country', [{ id: 'country-id', name: 'United Kingdom' }]),
            domains: entityCollection('channel_domain'),
            maintenanceIpAllowlist: [],
            ...channelOverrides,
        };

        return shallowMount(component, {
            props: { channel },
            global: {
                provide: {
                    acl: { can: () => can },
                    repositoryFactory: { create: () => ({ search: categorySearch }) },
                    channelService: { generateKey },
                    knownIpsService: { getKnownIps },
                },
                stubs: {
                    'ct-block': true,
                    'mt-card': true,
                    'ct-category-tree-field': true,
                    'ct-channel-defaults-select': true,
                    'ct-multi-tag-ip-select': true,
                    'ct-channel-detail-hreflang': true,
                    'ct-channel-detail-domains': true,
                },
            },
        }) as unknown as VueWrapper<DetailBaseVm> & { props: (key?: string) => unknown };
    }

    it('loads the selected main, footer, and service category collections', async () => {
        createWrapper({
            navigationCategoryId: 'main-category-id',
            footerCategoryId: 'footer-category-id',
            serviceCategoryId: 'service-category-id',
        });

        await flushPromises();

        expect(categorySearch).toHaveBeenCalledTimes(3);
        expect(
            categorySearch.mock.calls.map(([criteria]) => {
                return (criteria.parse().filter?.[0] as { value?: unknown } | undefined)?.value;
            }),
        ).toEqual([
            'main-category-id',
            'footer-category-id',
            'service-category-id',
        ]);
    });

    it('writes category tree selections back to the Channel', () => {
        const wrapper = createWrapper();
        const channel = wrapper.props('channel') as Record<string, unknown>;

        wrapper.vm.onMainSelectionAdd({ id: 'main-category-id' });
        wrapper.vm.onFooterSelectionAdd({ id: 'footer-category-id' });
        wrapper.vm.onServiceSelectionAdd({ id: 'service-category-id' });

        expect(channel.navigationCategoryId).toBe('main-category-id');
        expect(channel.footerCategoryId).toBe('footer-category-id');
        expect(channel.serviceCategoryId).toBe('service-category-id');

        wrapper.vm.onMainSelectionRemove();
        wrapper.vm.onFooterSelectionRemove();
        wrapper.vm.onServiceSelectionRemove();

        expect(channel.navigationCategoryId).toBeNull();
        expect(channel.footerCategoryId).toBeNull();
        expect(channel.serviceCategoryId).toBeNull();
    });

    it('passes upstream sorting and active filters to defaults selects', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.countryCriteria.parse().sort?.[0]).toEqual({
            field: 'name',
            order: 'ASC',
            naturalSorting: false,
        });
        expect(wrapper.vm.languageCriteria.parse().filter).toEqual([{ type: 'equals', field: 'active', value: true }]);
    });

    it('recognizes web and API channels as domain-aware', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.isWebChannel).toBe(true);
        expect(wrapper.vm.isDomainAware).toBe(true);

        await wrapper.setProps({
            channel: { ...(wrapper.props('channel') as object), typeId: Contena.Defaults.apiChannelTypeId },
        });

        expect(wrapper.vm.isWebChannel).toBe(false);
        expect(wrapper.vm.isDomainAware).toBe(true);
    });

    it('marks the default language as attention when no Domain serves it', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.unservedLanguages).toHaveLength(1);
        expect(wrapper.vm.unservedLanguageVariant).toBe('attention');
    });

    it('keeps the maintenance allowlist as tags and validates IP/CIDR values', () => {
        const wrapper = createWrapper({ maintenanceIpAllowlist: ['127.0.0.1'] });
        const channel = wrapper.props('channel') as Record<string, unknown>;

        expect(wrapper.vm.maintenanceIpAllowlist).toEqual(['127.0.0.1']);
        wrapper.vm.maintenanceIpAllowlist = [
            '127.0.0.1',
            '10.0.0.0/8',
        ];

        expect(channel.maintenanceIpAllowlist).toEqual([
            '127.0.0.1',
            '10.0.0.0/8',
        ]);
        expect(wrapper.vm.validateMaintenanceIpCidr('10.0.0.0/8')).toBe(true);
        expect(wrapper.vm.validateMaintenanceIpCidr('invalid')).toBe(false);
    });

    it('generates a new Channel API key and uses the Channel favourites service', async () => {
        const wrapper = createWrapper();
        const channel = wrapper.props('channel') as Record<string, unknown>;

        await wrapper.vm.onGenerateKey();

        expect(channel.accessKey).toBe('new-key');
        expect(wrapper.vm.isFavorite()).toBe(true);
        expect(channelFavorites.isFavorite).toHaveBeenCalledWith('channel-id');
    });
});
