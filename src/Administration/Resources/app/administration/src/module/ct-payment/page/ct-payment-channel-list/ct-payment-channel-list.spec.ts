import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './index';

interface PaymentChannelListVm {
    criteria: InstanceType<typeof Contena.Data.Criteria>;
}

describe('module/ct-payment/page/ct-payment-channel-list', () => {
    it('loads configured channels with their application and routing rule', async () => {
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [{ path: '/payment/channels', name: 'ct.payment.channels', component: { template: '<div />' } }],
        });
        await router.push('/payment/channels');
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
        }) as unknown as VueWrapper<PaymentChannelListVm>;
        await flushPromises();

        expect(create).toHaveBeenCalledWith('payment_channel_config');
        expect(wrapper.vm.criteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'channel' }),
                expect.objectContaining({ association: 'app' }),
                expect.objectContaining({ association: 'rule' }),
            ]),
        );
        wrapper.unmount();
    });
});
