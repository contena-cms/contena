import { mount } from '@vue/test-utils';
import '../../../../../store/block-override.store';
import createDataScopeFixture from '../../ct-block-override.spec/test-utils/create-data-scope-fixture';

describe('src/app/component/structure/ct-block-override/ct-block: scoped slot reactivity', () => {
    it('re-renders block content when only the surrounding slot scope changes', async () => {
        // A scoped slot's scope reaches the slot function as an argument, not as a
        // reactive read, so ct-block's computed template must be invalidated when
        // the parent hands over a new slot function — otherwise block content
        // inside a scoped slot keeps rendering values from the previous scope.
        const wrapper = mount(
            {
                template: `
                    <scoped-host :label="label">
                        <template #default="{ label: scopedLabel }">
                            <ct-block name="scoped-slot-block" :data="$dataScope">
                                <span class="scoped-label">{{ scopedLabel }}</span>
                            </ct-block>
                        </template>
                    </scoped-host>
                `,
                components: {
                    'ct-block': await wrapTestComponent('ct-block', { sync: true }),
                    'scoped-host': {
                        template: '<div class="scoped-host"><slot :label="label"/></div>',
                        props: ['label'],
                    },
                },
                data() {
                    return { label: 'before' };
                },
            },
            {
                global: {
                    plugins: [createDataScopeFixture()],
                },
            },
        );

        expect(wrapper.get('.scoped-label').text()).toBe('before');

        await wrapper.setData({ label: 'after' });

        expect(wrapper.get('.scoped-label').text()).toBe('after');
    });
});
