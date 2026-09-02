/**
 * Covers constraints on the `<script setup>` body itself, independent of any specific macro:
 * top-level await, ES module exports, the reserved `__ctSetup` binding prefix, and ambient
 * `declare` hoisting.
 */

import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformContenaSetupSfc } from './helpers';

describe('build/vue-setup-transform script setup constraints', () => {
    it('rejects top-level await', () => {
        const source = stripIndent`
            <script setup>
            const value = await loadValue();
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'await.vue')).toThrow(
            'Top-level await is not supported inside Contena setup blocks.',
        );
    });

    it('rejects ES module exports like native script setup', () => {
        const source = stripIndent`
            <script setup>
            export const count = 1;
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('allows importing Vue macros from vue while keeping them macros', () => {
        const source = stripIndent`
            <script setup>
            import { defineProps } from 'vue';
            const props = defineProps({ count: Number });
            const doubled = props.count * 2;
            ctDefinePublic({ doubled });
            </script>
        `;

        const result = transformOrFail(source, 'vue-macro-import.vue').code;

        // The import is legal (Vue itself drops it again during compilation) and the call stays a props
        // macro in place; only the binding is renamed to its author alias.
        expect(result).toContain("import { defineProps } from 'vue';");
        expect(result).toContain('const __ctSetupAuthor_props = defineProps({ count: Number });');
    });

    it('rejects importing a Vue macro name from anywhere but vue', () => {
        const source = stripIndent`
            <script setup>
            import { defineProps } from './my-utils';
            const props = defineProps({ count: Number });
            ctDefinePublic({ props });
            </script>
        `;

        // Vue would still treat the calls as macros (macros match by name and never yield to an
        // import), silently hijacking the imported function - so the import is rejected outright.
        expect(() => transformContenaSetupSfc(source, 'macro-name-import.vue')).toThrow(
            '"defineProps" is reserved by the Contena setup transform and must not be declared or imported.',
        );
    });

    it('still rejects declaring a Vue macro name locally', () => {
        const source = stripIndent`
            <script setup>
            const defineProps = () => ({});
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'declared-macro-name.vue')).toThrow(
            '"defineProps" is reserved by the Contena setup transform and must not be declared or imported.',
        );
    });

    it('rejects top-level bindings using the reserved __ctSetup prefix', () => {
        const source = stripIndent`
            <script setup>
            const __ctSetupProps = 1;
            const count = __ctSetupProps;
            ctDefinePublic({ count });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'reserved-prefix.vue')).toThrow(
            '"__ctSetupProps" uses the reserved "__ctSetup" prefix of the Contena setup transform and must not be declared or imported.',
        );
    });

    it('rejects a `__proto__` binding that the generated state map would silently drop', () => {
        const source = stripIndent`
            <script setup>
            const __proto__ = 7;
            ctDefinePublic({ __proto__ });
            </script>
        `;

        // `__proto__: alias` is prototype-setter syntax, not an own key, so the footer would read the
        // prototype instead of the value. Reject rather than silently corrupt.
        expect(() => transformContenaSetupSfc(source, 'proto.vue')).toThrow('"__proto__" cannot be a Contena setup binding');
    });

    it('keeps ambient declare declarations in place without collecting them as state', () => {
        const source = stripIndent`
            <script setup lang="ts">
            declare const injected: number;
            const count = injected + 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'declare.vue').code;

        // Ambient declarations describe runtime values provided from elsewhere: they are not runtime
        // bindings, so they are neither renamed nor collected as returned setup state.
        expect(result).toContain('declare const injected: number;');
        expect(result.indexOf('declare const injected')).toBeLessThan(result.indexOf('attachOverrides('));
        expect(result).toContain('const __ctSetupAuthor_count = injected + 1;');
        expect(result).not.toMatch(/\n\s*injected,/);
    });

    it('accepts type-only exports and leaves them in place', () => {
        const source = stripIndent`
            <script setup lang="ts">
            export type PublicCount = number;
            export interface PublicShape {
                count: number;
            }
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'type-only-export.vue').code;

        expect(result).toContain('export type PublicCount = number;');
        // The interface member key is a type-space name and must survive the runtime rename untouched.
        expect(result).toContain('count: number;');
        expect(result.indexOf('export interface PublicShape')).toBeLessThan(
            result.indexOf('Contena.Component.attachOverrides('),
        );
        expectVueCompilerScriptToCompile(result, 'type-only-export.vue');
    });

    it('accepts exports inside an ambient module augmentation', () => {
        const source = stripIndent`
            <script setup lang="ts">
            declare module 'vue' {
                export interface ComponentCustomProperties {
                    $foo: string;
                }
            }
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'ambient-module-export.vue').code;

        expect(result).toContain("declare module 'vue' {");
        expect(result.indexOf("declare module 'vue'")).toBeLessThan(result.indexOf('Contena.Component.attachOverrides('));
    });

    it('still rejects a value-carrying named export', () => {
        const source = stripIndent`
            <script setup lang="ts">
            export const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'value-export.vue')).toThrow(
            '<script setup> cannot contain ES module exports.',
        );
    });

    it('rejects a top-level binding named Contena that would shadow the runtime global', () => {
        const source = stripIndent`
            <script setup>
            const Contena = { custom: true };
            const count = Contena.custom;
            ctDefinePublic({ count });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'reserved-contena.vue')).toThrow(
            '"Contena" is reserved by the Contena setup transform',
        );
    });

    it('rejects an unsupported script lang', () => {
        const source = stripIndent`
            <script setup lang="coffee">
            count = 1
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'coffee.vue')).toThrow(
            'Unsupported <script setup lang="coffee"> in a Contena setup block.',
        );
    });

    it('accepts a tsx script lang', () => {
        const source = stripIndent`
            <script setup lang="tsx">
            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'tsx-lang.vue').code;

        expect(result).toContain('Contena.Component.attachOverrides(');
    });

    it('reports transform errors at the offending author source offset', () => {
        const source = stripIndent`
            <template><div /></template>
            <script setup>
            const value = await loadValue();
            ctDefinePublic({});
            </script>
        `;

        let thrown: unknown;

        try {
            transformContenaSetupSfc(source, 'offset.vue');
        } catch (error) {
            thrown = error;
        }

        const error = thrown as { name: string; index: number; endIndex: number };

        // The absolute SFC offset must land on the offending `await`, not at 0 (block-relative) or the
        // block start - this is the contract index.ts's withBlockOffset() exists to preserve.
        expect(error.name).toBe('ContenaSetupTransformError');
        expect(source.slice(error.index, error.index + 'await'.length)).toBe('await');
        // The error also carries the full node range (endIndex), so ESLint can underline the whole
        // offending expression rather than a single point.
        expect(source.slice(error.index, error.endIndex)).toBe('await loadValue()');
    });

    it('carries the full binding range on a reserved-name error so the whole name is underlined', () => {
        const source = stripIndent`
            <template><div /></template>
            <script setup>
            const Contena = { custom: true };
            ctDefinePublic({});
            </script>
        `;

        let thrown: unknown;

        try {
            transformContenaSetupSfc(source, 'reserved-range.vue');
        } catch (error) {
            thrown = error;
        }

        const error = thrown as { name: string; index: number; endIndex: number };

        expect(error.name).toBe('ContenaSetupTransformError');
        // The range spans the offending binding identifier, not just its first character.
        expect(source.slice(error.index, error.endIndex)).toBe('Contena');
    });
});
