import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import component from './index';

type ModalGridVm = {
    channelTypes: unknown[];
    onAddChannel: (typeId: string) => void;
    onOpenDetail: (typeId: string) => void;
};

describe('ct-channel-modal-grid', () => {
    it('loads all Channel types and forwards add/detail actions', async () => {
        const types = Object.assign([{ id: 'frontend-type', name: 'Frontend', translated: { name: 'Frontend' } }], {
            total: 1,
        });
        const search = jest.fn(() => Promise.resolve(types));
        const wrapper = shallowMount(component, {
            global: {
                provide: { repositoryFactory: { create: () => ({ search }) } },
                stubs: { 'ct-block': true, 'mt-loader': true, 'mt-icon': true, 'mt-button': true },
            },
        }) as unknown as VueWrapper<ModalGridVm>;
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.channelTypes).toHaveLength(1);
        expect(wrapper.vm.channelTypes[0]).toMatchObject(types[0]);

        wrapper.vm.onAddChannel('frontend-type');
        wrapper.vm.onOpenDetail('frontend-type');
        expect(wrapper.emitted('grid-channel-add')).toEqual([['frontend-type']]);
        expect(wrapper.emitted('grid-detail-open')?.[0]?.[0]).toEqual(types[0]);
    });
});
