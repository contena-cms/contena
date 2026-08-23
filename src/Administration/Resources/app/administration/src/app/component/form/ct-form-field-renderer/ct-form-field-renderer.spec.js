import { mount } from '@vue/test-utils';
import { MtRadioGroupRoot, MtRadioGroupList, MtRadioGroupItem } from '@contena/meteor-component-library';
import ContenaError from 'src/core/data/ContenaError';

async function createWrapper(additionalOptions = {}) {
    return mount(
        await wrapTestComponent('ct-form-field-renderer', {
            sync: true,
        }),
        {
            props: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
            },
            global: {
                components: {
                    MtRadioGroupRoot,
                    MtRadioGroupList,
                    MtRadioGroupItem,
                },
                stubs: {
                    'mt-text-field': {
                        template: '<div class="ct-text-field"><slot name="label"></slot><slot></slot></div>',
                    },
                    'ct-contextual-field': true,
                    'ct-block-field': true,
                    'ct-base-field': true,
                    'ct-field-error': true,
                },
                provide: {
                    validationService: {},
                    repositoryFactory: {
                        create() {
                            return {
                                get() {
                                    return Promise.resolve({});
                                },
                            };
                        },
                    },
                },
            },
            ...additionalOptions,
        },
    );
}

describe('components/form/ct-form-field-renderer', () => {
    beforeAll(() => {
        global.repositoryFactoryMock.showError = false;
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                label: '<template>Label from slot</template>',
            },
        });
        await flushPromises();
        const contentWrapper = wrapper.find('.ct-form-field-renderer');
        expect(contentWrapper.text()).toBe('Label from slot');
    });

    it('should show the value from the default slot', async () => {
        const wrapper = await createWrapper({
            slots: {
                default: '<p>I am in the default slot</p>',
            },
        });
        const contentWrapper = wrapper.find('.ct-form-field-renderer');
        expect(contentWrapper.text()).toBe('I am in the default slot');
    });

    it('should has props error', async () => {
        const wrapper = await createWrapper({
            propsData: {
                config: {
                    name: 'field2',
                    type: 'text',
                    config: { label: 'field2Label' },
                },
                value: 'data value',
                error: new ContenaError({ code: 'dummyCode' }),
            },
        });

        expect(wrapper.props().error).toBeInstanceOf(ContenaError);
    });

    it('should enable multi selection for meteor multi-select fields', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'multi-select',
                config: {
                    options: [],
                },
                value: [],
            },
        });

        expect(wrapper.vm.bind.enableMultiSelection).toBe(true);
    });

    it('should render radio fields with Meteor components and update the value', async () => {
        const wrapper = await createWrapper({
            props: {
                type: 'radio',
                config: {
                    options: [
                        { value: 'replace', label: 'Replace' },
                        { value: 'rename', label: 'Rename' },
                    ],
                },
                value: 'replace',
            },
        });

        const radioOptions = wrapper.findAll('input[type="radio"]');

        expect(radioOptions).toHaveLength(2);
        await wrapper.findAll('.mt-radio-group-item')[1].trigger('click');

        expect(wrapper.emitted('update:value')).toContainEqual(['rename']);
    });
});
