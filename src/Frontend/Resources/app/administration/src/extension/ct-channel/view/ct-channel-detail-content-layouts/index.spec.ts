/* eslint-disable @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { flushPromises, shallowMount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';

describe('ct-channel-detail-content-layouts', () => {
    const assignments = {
        header: [
            {
                id: 'header-assignment',
                domainId: undefined,
                contentLayoutId: 'header-layout',
            },
        ],
        footer: [],
    };

    function createWrapper() {
        const repositories = {
            header_content_layout: {
                search: jest.fn<
                    Promise<typeof assignments.header>,
                    [InstanceType<typeof Contena.Data.Criteria>, typeof Contena.Context.api]
                >(() => Promise.resolve(assignments.header)),
                create: jest.fn(() => ({})),
                save: jest.fn(() => Promise.resolve()),
                delete: jest.fn(() => Promise.resolve()),
            },
            footer_content_layout: {
                search: jest.fn<
                    Promise<typeof assignments.footer>,
                    [InstanceType<typeof Contena.Data.Criteria>, typeof Contena.Context.api]
                >(() => Promise.resolve(assignments.footer)),
                create: jest.fn(() => ({})),
                save: jest.fn(() => Promise.resolve()),
                delete: jest.fn(() => Promise.resolve()),
            },
        };
        const routerPush = jest.fn();
        const wrapper = shallowMount(component, {
            props: {
                channel: {
                    id: 'channel-id',
                    name: 'Frontend',
                    domains: [{ id: 'domain-id', url: 'https://example.com' }],
                },
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entityName: keyof typeof repositories) => repositories[entityName],
                    },
                    acl: { can: jest.fn(() => true) },
                    [routeLocationKey]: { name: 'ct.channel.detail.contentLayouts' },
                    [routerKey]: { push: routerPush },
                },
                stubs: {
                    'ct-block': true,
                    'mt-card': true,
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-entity-select': true,
                },
            },
        });

        return { wrapper, repositories, routerPush };
    }

    it('loads header and footer assignments for the current Channel', async () => {
        const { repositories } = createWrapper();
        await flushPromises();

        expect(repositories.header_content_layout.search).toHaveBeenCalledWith(expect.anything(), Contena.Context.api);
        expect(repositories.footer_content_layout.search).toHaveBeenCalledWith(expect.anything(), Contena.Context.api);

        const criteria = repositories.header_content_layout.search.mock.calls[0]?.[0];
        expect(criteria?.parse().filter).toContainEqual({
            type: 'equals',
            field: 'channelId',
            value: 'channel-id',
        });
    });

    it('filters selectable layouts by content section', () => {
        const { wrapper } = createWrapper();

        expect(wrapper.vm.getContentLayoutCriteria('header').parse().filter).toContainEqual({
            type: 'equals',
            field: 'rootSource',
            value: 'header',
        });
        expect(wrapper.vm.getContentLayoutCriteria('footer').parse().filter).toContainEqual({
            type: 'equals',
            field: 'rootSource',
            value: 'footer',
        });
    });

    it('updates the Channel-level header assignment', async () => {
        const { wrapper, repositories } = createWrapper();
        await flushPromises();

        await wrapper.vm.onContentLayoutChange('header', null, 'new-header-layout');

        expect(repositories.header_content_layout.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'header-assignment',
                channelId: 'channel-id',
                domainId: null,
                contentLayoutId: 'new-header-layout',
            }),
            Contena.Context.api,
        );
    });

    it('creates a domain-specific footer assignment', async () => {
        const { wrapper, repositories } = createWrapper();
        await flushPromises();

        await wrapper.vm.onContentLayoutChange('footer', 'domain-id', 'footer-layout');

        expect(repositories.footer_content_layout.create).toHaveBeenCalledWith(Contena.Context.api);
        expect(repositories.footer_content_layout.save).toHaveBeenCalledWith(
            expect.objectContaining({
                channelId: 'channel-id',
                domainId: 'domain-id',
                contentLayoutId: 'footer-layout',
            }),
            Contena.Context.api,
        );
    });

    it('removes an assignment when the selection is cleared', async () => {
        const { wrapper, repositories } = createWrapper();
        await flushPromises();

        await wrapper.vm.onContentLayoutChange('header', null, null);

        expect(repositories.header_content_layout.delete).toHaveBeenCalledWith('header-assignment', Contena.Context.api);
    });

    it('opens Experience Studio with the header Channel context', () => {
        const { wrapper, routerPush } = createWrapper();

        wrapper.vm.onCreateLayout('header');

        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.experience.studio.create',
            query: {
                rootSource: 'header',
                channelId: 'channel-id',
            },
        });
    });
});
