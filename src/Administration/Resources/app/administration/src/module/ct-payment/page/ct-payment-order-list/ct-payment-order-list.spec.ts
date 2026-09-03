import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface PaymentOrderListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
    statusVariant: (order: Entity<'payment_order'>) => string;
}

describe('module/ct-payment/page/ct-payment-order-list', () => {
    let wrapper: VueWrapper<PaymentOrderListVm>;
    let search: jest.Mock;

    beforeEach(async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/payment/order', name: 'ct.payment.order', component: { template: '<div />' } }],
        });
        await router.push('/payment/order');
        await router.isReady();
        search = jest.fn().mockResolvedValue(Object.assign([], { total: 0 }));
        wrapper = shallowMount(component, {
            global: {
                plugins: [router],
                provide: {
                    repositoryFactory: { create: () => ({ search }) },
                    searchRankingService: { getSearchFieldsByEntity: () => ({}) },
                },
                stubs: { 'ct-block': true, 'ct-page': true, 'mt-data-table': true, 'mt-empty-state': true },
            },
        }) as unknown as VueWrapper<PaymentOrderListVm>;
        await flushPromises();
    });

    afterEach(() => wrapper.unmount());

    it('loads the state and application needed by the list', () => {
        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.criteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'state' }),
                expect.objectContaining({ association: 'app' }),
            ]),
        );
    });

    it('maps terminal states to distinct badge variants', () => {
        expect(wrapper.vm.statusVariant({ state: { technicalName: 'succeeded' } } as Entity<'payment_order'>)).toBe(
            'positive',
        );
        expect(wrapper.vm.statusVariant({ state: { technicalName: 'failed' } } as Entity<'payment_order'>)).toBe('critical');
    });
});
