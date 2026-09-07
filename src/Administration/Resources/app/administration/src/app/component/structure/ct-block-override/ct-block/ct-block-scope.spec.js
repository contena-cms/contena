/**
 * Scoping behaviour of `<ct-block>`: block matching is scoped to `componentName + blockName` via the
 * `ct-internal-component-name` attribute that the Contena setup transform stamps on every native block.
 * These tests mount the blocks directly and set `ct-internal-component-name` explicitly to stand in for
 * that stamping.
 */
import { mount } from '@vue/test-utils';
import blockOverrideStore from '../../../../store/block-override.store';
import createDataScopeFixture from '../ct-block-override.spec/test-utils/create-data-scope-fixture';

async function createWrapper(template) {
    return mount(
        {
            template,
            components: {
                'ct-block': await wrapTestComponent('ct-block', { sync: true }),
                'ct-block-parent': await wrapTestComponent('ct-block-parent', { sync: true }),
            },
        },
        {
            global: {
                plugins: [createDataScopeFixture()],
            },
        },
    );
}

describe('ct-block component scoping', () => {
    beforeAll(() => {
        Contena.Store.register('blockOverride', blockOverrideStore);
    });

    it('does not apply an override from a different component scope', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <ct-block name="scoped_block" ct-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </ct-block>
                </div>
                <ct-block extends="scoped_block" ct-internal-component-name="component-b" :data="$dataScope">
                    <div class="override-b"></div>
                </ct-block>
            </div>
        `);

        expect(wrapper.find('.host-a > .default-a').exists()).toBe(true);
        expect(wrapper.find('.override-b').exists()).toBe(false);
    });

    it('applies an override registered under the same component scope', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <ct-block name="same_scope_block" ct-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </ct-block>
                </div>
                <ct-block extends="same_scope_block" ct-internal-component-name="component-a" :data="$dataScope">
                    <div class="override-a"></div>
                </ct-block>
            </div>
        `);

        expect(wrapper.find('.host-a > .default-a').exists()).toBe(false);
        expect(wrapper.find('.host-a > .override-a').exists()).toBe(true);
    });

    it('isolates same-named blocks in two different component scopes from each other', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host-a">
                    <ct-block name="dup_block" ct-internal-component-name="component-a" :data="$dataScope">
                        <div class="default-a"></div>
                    </ct-block>
                </div>
                <div class="host-b">
                    <ct-block name="dup_block" ct-internal-component-name="component-b" :data="$dataScope">
                        <div class="default-b"></div>
                    </ct-block>
                </div>
                <ct-block extends="dup_block" ct-internal-component-name="component-a" :data="$dataScope">
                    <ct-block-parent />
                    <div class="override-a"></div>
                </ct-block>
            </div>
        `);

        expect(wrapper.find('.host-a > .default-a').exists()).toBe(true);
        expect(wrapper.find('.host-a > .override-a').exists()).toBe(true);
        expect(wrapper.find('.host-b > .default-b').exists()).toBe(true);
        expect(wrapper.find('.host-b > .override-a').exists()).toBe(false);
    });

    it('still matches on the block name alone when no component scope is stamped', async () => {
        const wrapper = await createWrapper(`
            <div>
                <div class="host">
                    <ct-block name="unscoped_block" :data="$dataScope">
                        <div class="default"></div>
                    </ct-block>
                </div>
                <ct-block extends="unscoped_block" :data="$dataScope">
                    <div class="override"></div>
                </ct-block>
            </div>
        `);

        expect(wrapper.find('.host > .default').exists()).toBe(false);
        expect(wrapper.find('.host > .override').exists()).toBe(true);
    });
});
