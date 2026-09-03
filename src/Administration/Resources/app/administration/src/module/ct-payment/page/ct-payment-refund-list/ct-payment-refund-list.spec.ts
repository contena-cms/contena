import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface PaymentRefundListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
    statusVariant: (status: number) => string;
}

describe('module/ct-payment/page/ct-payment-refund-list', () => {
    let wrapper: VueWrapper<PaymentRefundListVm>;
    let search: jest.Mock;

    beforeEach(async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/payment/refund', name: 'ct.payment.refund', component: { template: '<div />' } }],
        });
        await router.push('/payment/refund');
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
        }) as unknown as VueWrapper<PaymentRefundListVm>;
        await flushPromises();
    });

    afterEach(() => wrapper.unmount());

    it('loads the payment order context used by refund rows', () => {
        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.criteria.associations).toContainEqual(expect.objectContaining({ association: 'order' }));
    });

    it('maps refund outcomes to badge variants', () => {
        expect(wrapper.vm.statusVariant(2)).toBe('positive');
        expect(wrapper.vm.statusVariant(3)).toBe('critical');
        expect(wrapper.vm.statusVariant(1)).toBe('attention');
    });
});
