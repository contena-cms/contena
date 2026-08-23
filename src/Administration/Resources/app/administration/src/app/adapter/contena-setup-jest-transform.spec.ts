import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import ContenaSetupJestTransformOverride from './_mocks_/ct-jest-transform-fixture.override.vue';
import ContenaSetupJestTransformBase from './_mocks_/ct-jest-transform-fixture.vue';

describe('test/transformer/contenaSetupVueTransformer', () => {
    afterAll(() => {
        delete _overridesMap['ct-jest-transform-fixture'];
    });

    it('transforms and mounts Contena setup Vue files through the real Jest Vue transformer', async () => {
        mount(ContenaSetupJestTransformOverride);

        const wrapper = mount(ContenaSetupJestTransformBase, {
            props: {
                label: 'Transformed',
            },
        });

        await flushPromises();
        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toBe('Transformed: 2');
        expect(wrapper.emitted('save')).toEqual([
            [
                2,
            ],
        ]);
    });
});
