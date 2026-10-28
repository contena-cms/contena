/**
 * @sw-package framework
 */

import { inferContenaSetupFromFilename, normalizeContenaSetupBlock } from './contena-setup-block';
import type { ScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/contena-setup-block', () => {
    it.each([
        ['src/module/sw-example.vue', { mode: 'base', componentName: 'sw-example' }],
        ['src/module/sw-example.override.vue', { mode: 'override', componentName: 'sw-example' }],
        ['src/module/sw-example/index.vue', { mode: 'base', componentName: 'sw-example' }],
        ['src\\module\\sw-example\\index.override.vue', { mode: 'override', componentName: 'sw-example' }],
        ['src/module/sw-example.vue?vue&type=script&setup=true', { mode: 'base', componentName: 'sw-example' }],
    ])('infers Contena setup mode and component name from %s', (filename, expected) => {
        expect(inferContenaSetupFromFilename(filename)).toEqual(expected);
    });

    it('normalizes script setup blocks with filename-inferred metadata', () => {
        const baseBlock = createScriptBlock('tsx');
        const overrideBlock = createScriptBlock(null);

        const base = normalizeContenaSetupBlock(baseBlock, 'sw-example.vue');
        const override = normalizeContenaSetupBlock(overrideBlock, 'sw-example.override.vue');

        expect(base).toMatchObject({
            mode: 'base',
            componentName: 'sw-example',
            lang: 'tsx',
        });
        expect(override).toMatchObject({
            mode: 'override',
            componentName: 'sw-example',
            lang: null,
        });
    });
});

function createScriptBlock(lang: string | null): ScriptBlock {
    return {
        type: 'scriptSetup',
        contentStart: '<script setup>'.length,
        contentEnd: '<script setup>'.length,
        content: '',
        lang,
    };
}
