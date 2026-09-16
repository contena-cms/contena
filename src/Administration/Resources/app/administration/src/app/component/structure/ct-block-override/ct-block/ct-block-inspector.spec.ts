/**
 * @group disabledCompat
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { getInspectedBlock, resetBlockInspector, setBlockInspectorEnabled } from 'src/core/factory/block-inspector';
import createDataScopeFixture from '../ct-block-override.spec/test-utils/create-data-scope-fixture';
import '../../../../store/block-override.store';

async function mountHost({ blockContent = '<div class="inner">content</div>', overrides = '' } = {}) {
    const ctBlock = await wrapTestComponent('ct-block', { sync: true });
    const ctBlockParent = await wrapTestComponent('ct-block-parent', { sync: true });

    return mount(
        {
            template: `
                <div class="host">
                    <owner-component />
                    ${overrides}
                </div>
            `,
            components: {
                'ct-block': ctBlock,
                'ct-block-parent': ctBlockParent,
                'owner-component': {
                    name: 'owner-component',
                    components: { 'ct-block': ctBlock },
                    template: `<section><ct-block name="inspected-block">${blockContent}</ct-block></section>`,
                },
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
            },
        },
    );
}

describe('ct-block with the block inspector enabled', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        resetBlockInspector();
        setBlockInspectorEnabled(true);
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        setBlockInspectorEnabled(false);
        resetBlockInspector();
    });

    it('marks the rendered root with the block name and registers the owning component', async () => {
        wrapper = await mountHost();

        expect(wrapper.find('.inner').attributes('data-ct-block')).toBe('inspected-block');
        expect(getInspectedBlock('inspected-block')).toEqual({
            name: 'inspected-block',
            component: 'owner-component',
            kind: 'native',
        });
    });

    it('marks every root of a multi-root block', async () => {
        wrapper = await mountHost({ blockContent: '<p class="a">a</p><p class="b">b</p>' });

        expect(wrapper.find('.a').attributes('data-ct-block')).toBe('inspected-block');
        expect(wrapper.find('.b').attributes('data-ct-block')).toBe('inspected-block');
    });

    it('marks the content a native override renders in place of the default', async () => {
        wrapper = await mountHost({
            overrides: '<ct-block extends="inspected-block"><span class="override">o</span></ct-block>',
        });

        expect(wrapper.find('.inner').exists()).toBe(false);
        expect(wrapper.find('.override').attributes('data-ct-block')).toBe('inspected-block');
    });

    it('renders no marker while the inspector is disabled', async () => {
        setBlockInspectorEnabled(false);
        wrapper = await mountHost();

        expect(wrapper.find('.inner').attributes('data-ct-block')).toBeUndefined();
        expect(getInspectedBlock('inspected-block')).toBeUndefined();
    });
});
