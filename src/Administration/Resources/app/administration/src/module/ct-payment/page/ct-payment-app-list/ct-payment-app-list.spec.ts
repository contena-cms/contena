import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface PaymentAppListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
}

describe('module/ct-payment/page/ct-payment-app-list', () => {
    it('loads Payment applications in app-code order', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/payment/apps', name: 'ct.payment.apps', component: { template: '<div />' } }],
        });
        await router.push('/payment/apps');
        await router.isReady();
        const search = jest.fn().mockResolvedValue(Object.assign([], { total: 0 }));
        const wrapper = shallowMount(component, {
            global: {
                plugins: [router],
                provide: {
                    repositoryFactory: { create: () => ({ search }) },
                    searchRankingService: { getSearchFieldsByEntity: () => ({}) },
                },
                stubs: { 'ct-block': true, 'ct-page': true, 'mt-data-table': true, 'mt-empty-state': true },
            },
        }) as unknown as VueWrapper<PaymentAppListVm>;
        await flushPromises();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.criteria.sortings).toContainEqual(expect.objectContaining({ field: 'appCode', order: 'ASC' }));
        wrapper.unmount();
    });
});
