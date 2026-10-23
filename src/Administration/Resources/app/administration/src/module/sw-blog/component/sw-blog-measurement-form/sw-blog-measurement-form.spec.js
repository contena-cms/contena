/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-blog/component/sw-blog-measurement-form', () => {
    let wrapper;
    let store;

    async function createWrapper(propsOverride = {}, privileges = []) {
        store = Contena.Store.get('swProductDetail');
        store.blog.width = 1000;
        store.blog.height = 2000;
        store.blog.length = 3000;
        store.blog.weight = 2;
        store.lengthUnit = 'mm';
        store.weightUnit = 'kg';

        const acl = {
            can: (privilege) => {
                if (!privilege) {
                    return true;
                }

                return privileges.includes(privilege);
            },
        };

        return mount(await wrapTestComponent('sw-blog-measurement-form', { sync: true }), {
            props: {
                allowEdit: true,
                ...propsOverride,
            },
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': {
                        template: `
                            <div class="sw-inherit-wrapper">
                                <slot name="content" v-bind="{
                                    currentValue: value,
                                    isInherited: false,
                                    updateCurrentValue: (val) => $emit('update:value', val)
                                }"></slot>
                            </div>`,
                        props: ['value', 'hasParent', 'inheritedValue'],
                    },
                    'mt-unit-field': {
                        template: `
                            <div
                                class="mt-unit-field"
                                :measurement-type="measurementType"
                            >
                                <label>{{ label }}</label>
                                <input
                                    type="text"
                                    :value="modelValue"
                                    @input="$emit('update:model-value', $event.target.value)"
                                />
                                <select
                                    :value="defaultUnit"
                                    @change="$emit('update:default-unit', $event.target.value)"
                                >
                                    <option value="mm">mm</option>
                                    <option value="cm">cm</option>
                                    <option value="m">m</option>
                                    <option value="in">in</option>
                                    <option value="ft">ft</option>
                                    <option value="yd">yd</option>
                                    <option value="mi">mi</option>
                                </select>
                            </div>`,
                        props: {
                            modelValue: {
                                type: [String, Number],
                                required: true,
                            },
                            defaultUnit: {
                                type: String,
                                required: true,
                            },
                            label: {
                                type: String,
                                required: true,
                            },
                            measurementType: {
                                type: String,
                                required: true,
                            },
                        },
                    },
                    'i18n-t': {
                        template: '<div class="i18n-stub"><slot></slot></div>',
                    },
                },
                provide: {
                    acl,
                },
            },
        });
    }

    beforeEach(async () => {
        wrapper = await createWrapper({}, ['blog.editor']);
    });

    it('should be able to change the length unit', async () => {
        expect(wrapper.vm.blog.height).toBe(2000);
        // eslint-disable-next-line jest/prefer-to-have-length
        expect(wrapper.vm.blog.length).toBe(3000);

        const lengthField = wrapper.find('.mt-unit-field[measurement-type="length"]');
        const unitSelect = lengthField.find('select');

        await unitSelect.setValue('cm');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.blog.height).toBe(200);
        // eslint-disable-next-line jest/prefer-to-have-length
        expect(wrapper.vm.blog.length).toBe(300);

        expect(store.lengthUnit).toBe('cm');
    });
});
