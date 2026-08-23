import { inferContenaSetupFromFilename, normalizeContenaSetupBlock } from './contena-setup-block';
import type { ScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/contena-setup-block', () => {
    it.each([
        [
            'src/module/ct-example.vue',
            { mode: 'base', componentName: 'ct-example' },
        ],
        [
            'src/module/ct-example.override.vue',
            { mode: 'override', componentName: 'ct-example' },
        ],
        [
            'src/module/ct-example/index.vue',
            { mode: 'base', componentName: 'ct-example' },
        ],
        [
            'src\\module\\ct-example\\index.override.vue',
            { mode: 'override', componentName: 'ct-example' },
        ],
        [
            'src/module/ct-example.vue?vue&type=script&setup=true',
            { mode: 'base', componentName: 'ct-example' },
        ],
    ])('infers Contena setup mode and component name from %s', (filename, expected) => {
        expect(inferContenaSetupFromFilename(filename)).toEqual(expected);
    });

    it('normalizes script setup blocks with filename-inferred metadata', () => {
        const baseBlock = createScriptBlock('tsx');
        const overrideBlock = createScriptBlock(null);

        const base = normalizeContenaSetupBlock(baseBlock, 'ct-example.vue');
        const override = normalizeContenaSetupBlock(overrideBlock, 'ct-example.override.vue');

        expect(base).toMatchObject({
            mode: 'base',
            componentName: 'ct-example',
            lang: 'tsx',
        });
        expect(override).toMatchObject({
            mode: 'override',
            componentName: 'ct-example',
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
