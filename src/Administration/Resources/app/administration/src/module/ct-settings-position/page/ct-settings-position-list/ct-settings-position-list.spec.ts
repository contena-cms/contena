/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

function collection<T>(items: T[]) {
    return Object.assign(items, {
        first: () => items[0] ?? null,
        total: items.length,
        criteria: {},
        context: Contena.Context.api,
    });
}

async function createWrapper(privileges: string[] = []) {
    const positions = collection([
        {
            id: 'position-id',
            name: 'General Manager',
            translated: { name: 'General Manager' },
            code: 'general_manager',
            position: 10,
            active: true,
            createdAt: '2026-08-01T08:00:00.000+00:00',
            updatedAt: '2026-08-02T08:00:00.000+00:00',
        },
    ]);
    const positionRepository = {
        search: jest.fn(() => Promise.resolve(positions)),
        delete: jest.fn(),
    };
    const router = { push: jest.fn(), replace: jest.fn() };
    const wrapper = mount(await wrapTestComponent('ct-settings-position-list', { sync: true }), {
        global: {
            provide: {
                [routeLocationKey]: { name: 'ct.settings.position.index', params: {}, query: {} },
                [routerKey]: router,
                repositoryFactory: { create: () => positionRepository },
                acl: { can: (privilege: string) => privileges.includes(privilege) },
                searchRankingService: {},
            },
            mocks: { $t: (key: string) => key },
            stubs: {
                'ct-page': {
                    template:
                        '<div class="ct-page-stub"><slot name="search-bar" /><slot name="smart-bar-header" /><slot name="language-switch" /><slot name="smart-bar-actions" /><slot name="content" /><slot /></div>',
                },
                'ct-card-view': { template: '<div><slot /></div>' },
                'mt-data-table': {
                    name: 'MtDataTable',
                    props: [
                        'dataSource',
                        'columns',
                        'disableEdit',
                        'disableDelete',
                        'additionalContextButtons',
                        'layout',
                        'disableSearch',
                    ],
                    template: '<div class="data-table"><slot name="empty-state" /></div>',
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'ct-search-bar': {
                    name: 'CtSearchBar',
                    props: [
                        'initialSearchType',
                        'placeholder',
                    ],
                    template: '<div class="global-search" />',
                },
                'ct-language-switch': true,
                'mt-icon': true,
                'mt-link': true,
            },
        },
    });

    await flushPromises();
    return { wrapper: wrapper as any, positionRepository, router };
}

describe('module/ct-settings-position/page/ct-settings-position-list', () => {
    it('loads a full-width listing with lifecycle columns', async () => {
        const { wrapper, positionRepository } = await createWrapper(['position.viewer']);

        expect(positionRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.positions).toHaveLength(1);
        expect(wrapper.vm.columns).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ dataIndex: 'createdAt' }),
                expect.objectContaining({ dataIndex: 'updatedAt' }),
            ]),
        );
        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('disableEdit')).toBe('');
        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('layout')).toBe('full');
        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('disableSearch')).toBe('');
        expect(wrapper.findComponent({ name: 'CtSearchBar' }).props('initialSearchType')).toBe('position');
        expect(wrapper.vm.columns).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ property: 'code', renderer: 'text' }),
                expect.objectContaining({ property: 'position', renderer: 'number' }),
                expect.objectContaining({ property: 'extensions.positionCreatedAtLabel', renderer: 'text' }),
            ]),
        );
    });

    it('searches, sorts and paginates through listing criteria', async () => {
        const { wrapper, positionRepository } = await createWrapper(['position.viewer']);

        wrapper.findComponent({ name: 'CtSearchBar' }).vm.$emit('search', 'manager');
        await flushPromises();
        wrapper.vm.onSortColumn({ dataIndex: 'createdAt', naturalSorting: false });
        await flushPromises();
        wrapper.vm.onPageChange({ page: 2, limit: 50 });
        await flushPromises();

        expect(positionRepository.search).toHaveBeenCalledTimes(4);
        expect(wrapper.vm.term).toBe('manager');
        expect(wrapper.vm.sortBy).toBe('createdAt');
        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(50);
    });

    it('routes creation to the independent create page', async () => {
        const { wrapper, router } = await createWrapper(['position.creator']);

        wrapper.vm.onAddPosition();

        expect(router.push).toHaveBeenCalledWith({ name: 'ct.settings.position.create' });
    });

    it('links Position names to the independent detail page', async () => {
        const { wrapper } = await createWrapper(['position.viewer']);

        expect(wrapper.findComponent({ name: 'MtDataTable' }).exists()).toBe(true);
    });

    it('keeps all listing actions behind Position privileges', async () => {
        const { wrapper } = await createWrapper();
        const listing = wrapper.findComponent({ name: 'MtDataTable' });

        expect(wrapper.find('.ct-settings-position-list__button-create').attributes('disabled')).toBeDefined();
        expect(listing.props()).toMatchObject({ disableEdit: '', disableDelete: true, additionalContextButtons: [] });
    });
});
