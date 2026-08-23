import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import type { RangeDays } from './dashboard-statistics';

function collection(items: unknown[] = [], aggregations: Record<string, unknown> = {}) {
    return Object.assign(items, { aggregations });
}

function createRepositoryFactory() {
    const search = jest.fn((criteria: { aggregations?: Record<string, unknown> }) =>
        Promise.resolve(
            collection([], {
                paidAmount: { sum: 123450 },
                previousAmount: { sum: 100000 },
                paidOrders: { count: 12 },
                previousOrders: { count: 10 },
                orderTrend: { buckets: [] },
                refundAmount: { sum: 1200 },
                refundOrders: { count: 1 },
                totalMembers: { count: 42 },
                newMembers: { count: 7 },
                previousMembers: { count: 5 },
                memberTrend: { buckets: [] },
                ...criteria.aggregations,
            }),
        ),
    );

    return { search, factory: { create: jest.fn(() => ({ search })) } };
}

const CardStub = defineComponent({
    template: '<section class="mt-card-stub"><header><slot name="title" /></header><slot /></section>',
});

async function createWrapper({
    can = () => true,
    repositoryFactory = createRepositoryFactory(),
}: { can?: (privilege: string) => boolean; repositoryFactory?: ReturnType<typeof createRepositoryFactory> } = {}) {
    const component = await wrapTestComponent('ct-dashboard-index', { sync: true });
    const wrapper = mount(component, {
        global: {
            stubs: {
                'ct-page': { template: '<div><slot name="content"></slot></div>' },
                'mt-card': CardStub,
                'mt-tooltip': { template: '<div><slot /></div>' },
                'mt-button': { template: '<button><slot /></button>' },
                'mt-icon': true,
                'mt-badge': true,
                'mt-empty-state': true,
                MtSegmentedControl: true,
                apexchart: true,
                'ct-extension-component-section': true,
                'ct-search-bar': true,
                'ct-app-topbar-button': true,
                'ct-app-topbar-sidebar': true,
                'ct-notification-center': true,
                'ct-help-center-v2': true,
                'ct-app-actions': true,
                'ct-error-summary': true,
                'ct-context-menu-item': true,
                'ct-context-button': true,
            },
            mocks: {
                $t: (path: string, placeholders: Record<string, string | number> = {}) =>
                    Object.entries(placeholders).reduce(
                        (
                            text,
                            [
                                key,
                                value,
                            ],
                        ) => text.replace(`{${key}}`, String(value)),
                        path,
                    ),
                $route: { meta: { $module: {} } },
            },
            provide: { repositoryFactory: repositoryFactory.factory, acl: { can } },
        },
    });
    await flushPromises();
    return { wrapper, repositoryFactory };
}

describe('module/ct-dashboard/page/ct-dashboard-index', () => {
    it('renders business metrics and both business trend charts', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('.ct-dashboard-index__metric')).toHaveLength(6);
        expect(wrapper.findAll('apexchart-stub')).toHaveLength(1);
        expect(wrapper.find('.ct-dashboard-index__members-card').exists()).toBe(false);
        expect(wrapper.find('.ct-dashboard-index__metric').text()).toContain('1,234.50');
    });

    it('reloads payment and member statistics when the date range changes', async () => {
        const { wrapper, repositoryFactory } = await createWrapper();
        const dashboard = wrapper.vm as unknown as {
            setRange: (days: RangeDays) => Promise<void>;
            selectedRange: RangeDays;
        };
        await dashboard.setRange(7);
        expect(dashboard.selectedRange).toBe(7);
        expect(repositoryFactory.search).toHaveBeenCalledTimes(8);
    });

    it('shows no dashboard content without payment and member privileges', async () => {
        const { wrapper, repositoryFactory } = await createWrapper({ can: () => false });
        expect(wrapper.find('.ct-dashboard-index__metrics').exists()).toBe(false);
        expect(wrapper.find('.ct-dashboard-index__primary-grid').exists()).toBe(false);
        expect(repositoryFactory.search).not.toHaveBeenCalled();
    });
});
