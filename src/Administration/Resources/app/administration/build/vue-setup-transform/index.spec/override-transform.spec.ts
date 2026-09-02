/**
 * Covers the end-to-end override lowering with no template involved: how an override `<script setup>`
 * becomes a hidden component that registers the setup override, the `ctDefineOverride` return payload,
 * and how imports and type declarations are preserved in the generated component.
 *
 * Template-driven forwarding lives in override-template.spec.ts; the destructuring-pattern edge cases
 * in override-template-patterns.spec.ts.
 */

import { expectVueCompilerScriptToCompile, stripIndent, stripWhitespace, transformOrFail } from './helpers';

describe('build/vue-setup-transform override transforms', () => {
    it('pins the whole generated output for an override with an <ct-block extends> and forwarded locals', () => {
        const source = stripIndent`
            <template>
                <ct-block extends="ct_example_headline">
                    <h1>{{ headline }} - {{ suffix }}</h1>
                </ct-block>
            </template>
            <script setup lang="ts">
            import { computed } from 'vue';

            const previousState = useCtPreviousState();
            const suffix = computed(() => '!');
            const headline = computed(() => previousState.title.value);

            ctDefineOverride({
                headline,
            });
            </script>
        `;

        // The one end-to-end assertion for override lowering, covering the three generated constructs that
        // only co-occur on the <ct-block extends> path: the module-root Symbol() namespace, the
        // `__ctOverride` payload keyed by it, and the `#default` slot scope that forwards the
        // override-local `suffix` into the block content. Imports are lifted out of the callback; the
        // author body is preserved inside it.
        //
        // Whitespace-insensitive on both sides - the transform does not beautify its output, so its
        // blank-line residue is not behaviour. The Vue round-trip below guards the token sequence.
        const expected = stripWhitespace`
            <template>
                <ct-block extends="ct_example_headline" #default="{ __ctOverride: { [__ctSetupNamespace]: { suffix } }, headline }">
                    <h1>{{ headline }} - {{ suffix }}</h1>
                </ct-block>
            </template>
            <script setup lang="ts">
            import { computed } from 'vue';

            const __ctSetupNamespace = Symbol('ct-example.override');

            Contena.Component.overrideComponentSetup()('ct-example', (__ctSetupPreviousState, __ctSetupProps, __ctSetupContext) => {
            const useCtPreviousState = () => __ctSetupPreviousState;
            const useCtProps = () => __ctSetupProps;
            const useCtContext = () => __ctSetupContext;

            const previousState = useCtPreviousState();
            const suffix = computed(() => '!');
            const headline = computed(() => previousState.title.value);

            return {
                headline,
                __ctOverride: {
                    [__ctSetupNamespace]: {
                        suffix,
                    },
                },
            };
            });
            </script>
        `;

        const result = transformOrFail(source, 'ct-example.override.vue').code;

        expect(stripWhitespace(result)).toBe(expected);
        expectVueCompilerScriptToCompile(result, 'ct-example.override.vue');
    });

    it('generates a registration template for an override without one', () => {
        const source = stripIndent`
            <script setup>
            const previousState = useCtPreviousState();
            const props = useCtProps();
            const context = useCtContext();

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'ct-my-component.override.vue').code;

        // A template-less override still has to render: the hidden component only registers its callback
        // once it mounts, and Vue warns about a component with neither template nor render function.
        expect(result).toContain('<template><!-- Contena override registration component --></template>');
        expect(result).toContain(
            "Contena.Component.overrideComponentSetup()('ct-my-component', (__ctSetupPreviousState, __ctSetupProps, __ctSetupContext) => {",
        );
        expect(result).toContain('const useCtPreviousState = () => __ctSetupPreviousState;');
        expect(result).toContain('const useCtProps = () => __ctSetupProps;');
        expect(result).toContain('const useCtContext = () => __ctSetupContext;');
        expect(result).toContain('return {};');
        expectVueCompilerScriptToCompile(result, 'ct-my-component.override.vue');
    });

    it('transforms ct-override blocks in .override.vue files', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ctDefineOverride({ count });
            </script>
        `;

        const result = transformOrFail(source, 'component-name.override.vue');

        expect(result.mode).toBe('override');
        expect(result.filename).toBe('component-name.override.vue');
        expect(result.code).toContain("Contena.Component.overrideComponentSetup()('component-name'");
    });

    it('keeps imports out of returned override state', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const doubled = computed(() => 2);

            ctDefineOverride({
                doubled,
            });
            </script>
        `;

        const result = transformOrFail(source, 'component.override.vue').code;

        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                doubled,
            };
        `);
        expect(result).not.toContain('computed,');
    });

    it('hoists type declarations as a group so cross-references and type-only exports survive', () => {
        const source = stripIndent`
            <script setup lang="ts">
            type Inner = { a: string };
            export type Outer = Inner;

            const props = useCtProps<Inner>();
            const label = props.a;

            ctDefineOverride({ label });
            </script>
        `;

        const result = transformOrFail(source, 'typed.override.vue').code;
        const callbackStart = result.indexOf('Contena.Component.overrideComponentSetup()');

        // A bare `type`/`interface` would be legal inside the generated callback; `export type` and an
        // ambient `declare` are not. They are hoisted as one group rather than selectively, because a
        // hoisted declaration can reference a preceding one - `export type Outer = Inner` here - and would
        // dangle if that one were left behind in the callback.
        expect(result.indexOf('type Inner = { a: string };')).toBeLessThan(callbackStart);
        expect(result.indexOf('export type Outer = Inner;')).toBeLessThan(callbackStart);
        expect(result).toContain('const props = useCtProps<Inner>();');
        expect(result.match(/type Inner/g)).toHaveLength(1);
        expectVueCompilerScriptToCompile(result, 'typed.override.vue');
    });

    it('uses ctDefineOverride() as the explicit override payload and keeps unused local state private', () => {
        const source = stripIndent`
            <script setup>
            import { computed, ref } from 'vue';

            const previousState = useCtPreviousState();
            const body = computed(() => previousState.body.value);
            const localInfo = ref('only for script logic');
            const localHeadline = computed(() => localInfo.value);
            const localFooter = computed(() => localInfo.value);

            ctDefineOverride({
                body,
                localHeadline,
                localFooter,
            });
            </script>
        `;

        const result = transformOrFail(source, 'explicit-payload.override.vue').code;

        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                body,
                localHeadline,
                localFooter,
            };
        `);
        expect(result).not.toContain('__ctOverride');
        expect(result).not.toContain('localInfo,');
    });
});
