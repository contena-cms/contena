import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, ref, watch } from 'vue';
import { _overridesMap, createExtendableSetup, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';

describe('composition extension effect scopes', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('disposes watchers created by late overrides when the component unmounts', async () => {
        const externalSource = ref(0);
        const watcherCalls = jest.fn();
        const component = defineComponent({
            template: '<div>Count: {{ count }}</div>',
            setup: (props, context) =>
                createExtendableSetup(
                    {
                        props,
                        context,
                        name: 'effectScopeComponent',
                    },
                    () => ({
                        public: {
                            count: ref(1),
                        },
                    }),
                ),
        });
        const wrapper = mount(component);

        overrideComponentSetup()('effectScopeComponent', () => {
            watch(externalSource, watcherCalls);

            return {
                count: ref(5),
            };
        });

        await flushPromises();
        expect(wrapper.text()).toBe('Count: 5');

        externalSource.value += 1;
        await nextTick();
        expect(watcherCalls).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        externalSource.value += 1;
        await nextTick();

        expect(watcherCalls).toHaveBeenCalledTimes(1);
    });
});
