import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface PaymentMethodListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
}

describe('module/ct-payment/page/ct-payment-method-list', () => {
    it('loads application method assignments instead of the provider registry', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/payment/methods', name: 'ct.payment.methods', component: { template: '<div />' } }],
        });
        await router.push('/payment/methods');
        await router.isReady();
        const create = jest.fn(() => ({ search: jest.fn().mockResolvedValue(Object.assign([], { total: 0 })) }));
        const wrapper = shallowMount(component, {
            global: {
                plugins: [router],
                provide: {
                    repositoryFactory: { create },
                    searchRankingService: { getSearchFieldsByEntity: () => ({}) },
                },
                stubs: { 'ct-block': true, 'ct-page': true, 'mt-data-table': true, 'mt-empty-state': true },
            },
        }) as unknown as VueWrapper<PaymentMethodListVm>;
        await flushPromises();

        expect(create).toHaveBeenCalledWith('payment_app_channel_method');
        expect(wrapper.vm.criteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'app' }),
                expect.objectContaining({ association: 'channelMethod' }),
                expect.objectContaining({ association: 'rule' }),
            ]),
        );
        wrapper.unmount();
    });
});
