import { shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

type DomainsVm = {
    openCreateModal: (defaults?: Record<string, unknown>) => void;
    saveDomain: () => Promise<void>;
    duplicateUrl: boolean;
    domainToDelete: Record<string, unknown> | null;
    deleteDomain: () => void;
};

function domainsCollection(items: Record<string, unknown>[]) {
    return Object.assign(items, {
        source: '/channel/channel-id/domains',
        has: (id: string) => items.some((item) => item.id === id),
        add: (item: Record<string, unknown>) => items.push(item),
        remove: (id: string) => {
            const index = items.findIndex((item) => item.id === id);
            if (index >= 0) items.splice(index, 1);
        },
    });
}

describe('ct-channel-detail-domains', () => {
    function createWrapper(searchResult: Record<string, unknown>[] = []) {
        const domains = domainsCollection([]);
        const languages = Object.assign([{ id: 'language-id', name: 'English' }], {
            get: (id: string) => languages.find((language) => language.id === id) ?? null,
            first: () => languages[0],
        });
        const createdDomain = {
            id: 'new-domain-id',
            url: '',
            languageId: '',
            language: null,
            snippetSetId: '',
            isNew: () => true,
        };
        const repository = {
            create: jest.fn(() => createdDomain),
            search: jest.fn(() => Promise.resolve(Object.assign(searchResult, { total: searchResult.length }))),
        };
        const wrapper = shallowMount(component, {
            props: { channel: { id: 'channel-id', languageId: 'language-id', domains, languages } },
            global: {
                provide: { repositoryFactory: { create: () => repository } },
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'mt-card': { template: '<div><slot /></div>' },
                    'mt-data-table': {
                        props: { currentPage: Number, paginationLimit: Number, paginationTotalItems: Number },
                        template:
                            '<div data-test="mt-data-table" :data-current-page="currentPage" :data-pagination-limit="paginationLimit" :data-pagination-total-items="paginationTotalItems"><slot /></div>',
                    },
                    'mt-modal-root': true,
                },
            },
        });
        return { wrapper: wrapper as unknown as VueWrapper<DomainsVm>, domains, createdDomain };
    }

    it('creates a Domain with the Channel default language and writes it to the association', async () => {
        const { wrapper, domains, createdDomain } = createWrapper();
        wrapper.vm.openCreateModal();
        createdDomain.url = 'https://example.test';
        createdDomain.snippetSetId = 'snippet-set-id';

        await wrapper.vm.saveDomain();

        expect(createdDomain.languageId).toBe('language-id');
        expect(domains).toContainEqual(createdDomain);
    });

    it('rejects a duplicate Domain URL before association write', async () => {
        const { wrapper, domains, createdDomain } = createWrapper([
            { id: 'existing-domain', channelId: 'other-channel', url: 'https://example.test' },
        ]);
        wrapper.vm.openCreateModal();
        createdDomain.url = 'https://example.test';
        createdDomain.snippetSetId = 'snippet-set-id';

        await wrapper.vm.saveDomain();

        expect(wrapper.vm.duplicateUrl).toBe(true);
        expect(domains).toHaveLength(0);
    });

    it('uses the requested language when opened for an unserved language', () => {
        const { wrapper, createdDomain } = createWrapper();

        wrapper.vm.openCreateModal({ languageId: 'unserved-language-id' });

        expect(createdDomain.languageId).toBe('unserved-language-id');
        expect(createdDomain.language).toBeNull();
    });

    it('removes a Domain through the parent association for repository save', () => {
        const { wrapper, domains } = createWrapper();
        const domain = { id: 'domain-id', url: 'https://example.test' };
        domains.push(domain);
        wrapper.vm.domainToDelete = domain;

        wrapper.vm.deleteDomain();

        expect(domains).toHaveLength(0);
    });

    it('provides the pagination state required by the Meteor data table', () => {
        const { wrapper } = createWrapper();

        const dataTable = wrapper.find('[data-test="mt-data-table"]');
        expect(dataTable.attributes('data-current-page')).toBe('1');
        expect(dataTable.attributes('data-pagination-limit')).toBe('25');
        expect(dataTable.attributes('data-pagination-total-items')).toBe('0');
    });
});
