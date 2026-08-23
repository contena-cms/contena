import { shallowMount, type VueWrapper } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';
import type DataDictionaryService from 'src/app/service/data-dictionary.service';
import DataDictionarySelect from './ct-data-dictionary-select.vue';

describe('ct-data-dictionary-select', () => {
    type DataDictionarySelectVm = ComponentPublicInstance & {
        onUpdateModelValue(value: string | null): void;
    };

    let wrapper: VueWrapper<DataDictionarySelectVm>;
    let dataDictionaryService: Pick<DataDictionaryService, 'getOptions'>;

    beforeEach(async () => {
        dataDictionaryService = {
            getOptions: jest.fn().mockResolvedValue([{ value: 'male', code: 'male', label: '男' }]),
        };
        wrapper = shallowMount(DataDictionarySelect, {
            props: {
                technicalName: 'core.gender',
                modelValue: 'male',
            },
            global: {
                provide: {
                    dataDictionaryService,
                },
            },
        }) as unknown as VueWrapper<DataDictionarySelectVm>;
        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('loads options for the configured dictionary', () => {
        expect(dataDictionaryService.getOptions).toHaveBeenCalledWith('core.gender', true);
        expect(wrapper.findComponent({ name: 'mt-select' }).props()).toMatchObject({
            modelValue: 'male',
            options: [{ value: 'male', code: 'male', label: '男' }],
            isLoading: false,
        });
    });

    it('emits model updates from the select', async () => {
        wrapper.vm.onUpdateModelValue('female');
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')).toEqual([['female']]);
    });
});
