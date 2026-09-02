/**
 * Covers how the transform finds (and refuses to find) the Contena setup block in raw SFC
 * source: filename-based mode/name inference, script-like text in attributes/comments/strings,
 * sibling script blocks, and parse-error passthrough to Vue.
 */

import { stripIndent, transformOrFail, transformContenaSetupSfc } from './helpers';

describe('build/vue-setup-transform SFC block detection', () => {
    it('transforms plain native script setup blocks using filename metadata', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'ct-native.vue');

        expect(result.code).toContain('Contena.Component.attachOverrides(');
        expect(result.code).toContain("name: 'ct-native'");
    });

    it('keeps the Vue script setup range when an attribute value contains a script-like string', () => {
        const source = stripIndent`
            <script setup data-example="<script">
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'script-attribute.vue').code;

        expect(result).toContain('Contena.Component.attachOverrides(');
        expect(result).toContain("name: 'script-attribute'");
    });

    it('ignores fake Contena setup script tags in non-top-level contexts', () => {
        const source = stripIndent`
            <!-- <script setup></script> -->
            <template>
                <div data-example="<script setup>"></div>
            </template>
            <style>
            .example::before { content: "<script setup>"; }
            </style>
            <script setup>
            // <script setup>
            /* <script setup> */
            const single = '<script setup>';
            const fake = "<script setup>";
            const template = \`<script setup>\${'<script setup>'}\`;
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'scanner.vue').code;

        // Exactly the real block is lowered; the fake tags survive verbatim as comment/string text
        // (the binding is renamed, but the string literal content is untouched).
        expect(result.match(/attachOverrides\(/g)).toHaveLength(1);
        expect(result).toContain("= '<script setup>';");
        expect(result).toContain('/* <script setup> */');
    });

    it.each([
        [
            'an Options API script block',
            stripIndent`
                <script>
                export default { name: 'ct-thing' };
                </script>
            `,
        ],
        [
            'a template-only SFC',
            stripIndent`
                <template><div>no script at all</div></template>
            `,
        ],
    ])('rejects %s, which could never be extended', (_name, source) => {
        // Every `.vue` component in the Administration is extendable, and the extension surface is
        // declared by markers that only exist inside `<script setup>`. An SFC without that block would
        // compile into a component nothing can override, so it is refused rather than passed through.
        expect(() => transformContenaSetupSfc(source, 'ct-thing.vue')).toThrow(
            'A Contena setup component needs a <script setup> block.',
        );
    });

    it('takes the component name from the directory for an index file', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        // `ct-thing/index.vue` is a documented form, so the directory name is what gets validated and
        // registered - `index` itself would never pass the name rule.
        expect(transformOrFail(source, 'ct-thing/index.vue').code).toContain("name: 'ct-thing'");
    });

    it.each([
        [
            'a template-only override',
            stripIndent`
                <template><div>nothing registers me</div></template>
            `,
        ],
        [
            'an override with a non-setup script block',
            stripIndent`
                <template><div /></template>
                <script>
                export default { name: 'ct-thing' };
                </script>
            `,
        ],
    ])('rejects %s, which would silently register nothing', (_name, source) => {
        // The `.override.vue` filename declares the intent, and an override registers itself from its
        // `<script setup>` body - so without that block the file is transformed away to nothing and the
        // override never applies. Returning null here (as for a plain `.vue`) would be a silent no-op.
        expect(() => transformContenaSetupSfc(source, 'ct-thing.override.vue')).toThrow(
            'An override component needs a <script setup> block to register its override.',
        );
    });

    it('rejects an additional normal script block next to Contena setup', () => {
        const source = stripIndent`
            <script>
            export const moduleValue = 1;
            </script>
            <script setup>
            const count = 1;
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'two-scripts.vue')).toThrow(
            'A Contena setup block cannot be combined with another <script> block',
        );
    });

    it('preserves the codemod module prelude marker beside setup code', () => {
        const source = stripIndent`
            <script data-sfc-migration-module>
            export const moduleValue = 1;
            </script>
            <script setup>
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'marked-module.vue');

        expect(result.code).toContain('<script data-sfc-migration-module>');
        expect(result.code).toContain('export const moduleValue = 1;');
        expect(result.code).toContain("name: 'marked-module'");
    });

    it('skips transformation when Vue reports SFC parse errors', () => {
        const source = stripIndent`
            <template>
                <div>
            </template>
            <script setup>
            const count = 1;
        `;

        expect(transformContenaSetupSfc(source, 'malformed.vue')).toBeNull();
    });
});
