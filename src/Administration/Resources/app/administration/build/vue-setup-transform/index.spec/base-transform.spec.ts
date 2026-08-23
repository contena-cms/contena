import {
    expectVueCompilerScriptToCompile,
    stripIndent,
    stripWhitespace,
    transformOrFail,
    transformContenaSetupSfc,
} from './helpers';
import { ContenaSetupTransformError } from '../utils/transform-error';

function getDirectNamedSlotDiagnostic(source: string, filename: string): ContenaSetupTransformError {
    let thrown: unknown;

    try {
        transformContenaSetupSfc(source, filename);
    } catch (error) {
        thrown = error;
    }

    if (!(thrown instanceof ContenaSetupTransformError)) {
        throw new Error('Expected a direct named-slot diagnostic.');
    }

    return thrown;
}

describe('build/vue-setup-transform base transforms', () => {
    it.each([
        '#footer',
        '#[slotName]',
        'v-slot:footer',
        'v-slot:[slotName]',
    ])('rejects a direct non-default named slot %s on a base ct-block', (slotDirective) => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_example_component_body">
                <template ${slotDirective}>content</template>
            </ct-block>
            </template>
            <script setup>
            const slotName = 'footer';

            swDefinePublic({});
            </script>
        `;

        const diagnostic = getDirectNamedSlotDiagnostic(source, 'base-direct-named-slot.vue');

        expect(diagnostic.message).toContain('A direct non-default named slot below <ct-block> is not supported.');
        expect(diagnostic.index).toBe(source.indexOf(`<template ${slotDirective}>`));
    });

    it('allows a named slot that belongs to a deeper child component', () => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_example_component_body">
                <ChildComponent>
                    <template #footer>content</template>
                </ChildComponent>
            </ct-block>
            </template>
            <script setup>
            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'base-deeper-child-named-slot.vue').code;

        expect(result).toContain('<ct-block :data="$dataScope" name="sw_example_component_body">');
    });

    it('pins the whole generated output for a base component with props, private state and an <ct-block>', () => {
        const source = stripIndent`
            <template>
                <ct-block name="sw_example_headline">
                    <h1>{{ title }}</h1>
                    <p>{{ doubled }}</p>
                </ct-block>
            </template>
            <script setup lang="ts">
            import { ref, computed } from 'vue';

            declare global {
                interface ComponentNamingApi {
                    example: string;
                }
            }

            const props = defineProps<{
                initialCount?: number;
            }>();
            const title = ref('Hello');
            const count = ref(props.initialCount ?? 0);
            const doubled = computed(() => count.value * 2);
            const internalNote = ref('secret');

            swDefinePublic({
                title,
                count,
                doubled,
            });
            </script>
        `;

        // The one end-to-end assertion for base lowering: the author body stays native (macros in place,
        // `declare global` untouched, every binding renamed to its __swSetupAuthor_ alias), the template
        // keeps its content with a generated `:data="$dataScope"` added to the <ct-block>, and a single
        // footer re-declares the original names by destructuring attachOverrides().
        //
        // Whitespace-insensitive on both sides, because the transform does not beautify its output - the
        // Vue round-trip below is what guarantees the result is still valid code.
        const expected = stripWhitespace`
            <template>
                <ct-block :data="$dataScope" name="sw_example_headline">
                    <h1>{{ title }}</h1>
                    <p>{{ doubled }}</p>
                </ct-block>
            </template>
            <script setup lang="ts">
            import { ref, computed } from 'vue';

            declare global {
                interface ComponentNamingApi {
                    example: string;
                }
            }

            const __swSetupAuthor_props = defineProps<{
                initialCount?: number;
            }>();
            const __swSetupAuthor_title = ref('Hello');
            const __swSetupAuthor_count = ref(__swSetupAuthor_props.initialCount ?? 0);
            const __swSetupAuthor_doubled = computed(() => __swSetupAuthor_count.value * 2);
            const __swSetupAuthor_internalNote = ref('secret');

            const {
                props,
                title,
                count,
                doubled,
                internalNote,
            } = Contena.Component.attachOverrides({
                name: 'ct-example',
                public: {
                    title: __swSetupAuthor_title,
                    count: __swSetupAuthor_count,
                    doubled: __swSetupAuthor_doubled,
                },
                private: {
                    props: __swSetupAuthor_props,
                    internalNote: __swSetupAuthor_internalNote,
                },
            });
            </script>
        `;

        const result = transformOrFail(source, 'ct-example.vue').code;

        expect(stripWhitespace(result)).toBe(expected);
        expectVueCompilerScriptToCompile(result, 'ct-example.vue');
    });

    it('supports import-only script setup blocks with empty state', () => {
        const source = stripIndent`
            <template><ChildComponent /></template>
            <script setup>
            import ChildComponent from './child.vue';

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'import-only.vue').code;

        expect(result).toContain("import ChildComponent from './child.vue';");
        expect(result).toContain('public: {},');
        expect(result).toContain('private: {},');
    });

    it('supports macro-only script setup blocks with empty state', () => {
        const source = stripIndent`
            <template><button @click="$emit('save')">save</button></template>
            <script setup>
            defineOptions({ inheritAttrs: false });
            defineEmits(['save']);

            swDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'macro-only.vue').code;

        expect(result).toContain('defineOptions({ inheritAttrs: false });');
        expect(result).toContain("defineEmits(['save']);");
        expect(result).toContain('public: {},');
        expect(result).toContain('private: {},');
    });

    it('adds the generated data scope to base ct-block declarations', () => {
        const source = stripIndent`
            <template>
            <article>
                <ct-block name="sw_example_component_headline">
                    <h2>{{ headline }}</h2>
                </ct-block>
            </article>
            </template>
            <script setup>
            const headline = 'Headline';

            swDefinePublic({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-ct-block-data.vue').code;

        expect(result).toContain('<ct-block :data="$dataScope" name="sw_example_component_headline">');
    });

    it('returns destructured runtime declarations as setup bindings', () => {
        const source = stripIndent`
            <template>
            <p>{{ publicTitle }}{{ localLabel }}{{ firstItem }}{{ rest.enabled }}</p>
            </template>
            <script setup>
            const source = {
                title: 'Title',
                nested: {
                    label: 'Label',
                },
                enabled: true,
            };
            const items = ['first'];
            const fallbackLabel = 'Fallback';

            const {
                title: publicTitle,
                nested: {
                    label: localLabel = fallbackLabel,
                },
                ...rest
            } = source;
            const [firstItem] = items;

            swDefinePublic({
                publicTitle,
            });
            </script>
        `;

        const collapsed = stripWhitespace(transformOrFail(source, 'base-destructured-runtime.vue').code);

        // Every destructured runtime binding is renamed at its declaration and re-exposed from the
        // footer; the public one keeps its name for the template.
        expect(collapsed).toContain(
            stripWhitespace`
                const {
                    title: __swSetupAuthor_publicTitle,
                    nested: {
                        label: __swSetupAuthor_localLabel = __swSetupAuthor_fallbackLabel,
                    },
                    ...__swSetupAuthor_rest
                } = __swSetupAuthor_source;
            `,
        );
        expect(collapsed).toContain('const [__swSetupAuthor_firstItem] = __swSetupAuthor_items;');
        expect(collapsed).toContain('publicTitle: __swSetupAuthor_publicTitle');
        expect(collapsed).toContain(
            stripWhitespace`
                private: {
                    source: __swSetupAuthor_source,
                    items: __swSetupAuthor_items,
                    fallbackLabel: __swSetupAuthor_fallbackLabel,
                    localLabel: __swSetupAuthor_localLabel,
                    rest: __swSetupAuthor_rest,
                    firstItem: __swSetupAuthor_firstItem,
                }
            `,
        );
    });

    it('adds the generated data scope to nested and self-closing base ct-block declarations', () => {
        const source = stripIndent`
            <template>
            <ct-block name="outer">
                <ct-block name="inner" />
            </ct-block>
            </template>
            <script setup>
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-nested-ct-block-data.vue').code;

        expect(result).toContain('<ct-block :data="$dataScope" name="outer">');
        expect(result).toContain('<ct-block :data="$dataScope" name="inner" />');
    });

    it.each([
        'data="scope"',
        ':data="scope"',
        'v-bind:data="scope"',
    ])('rejects the authored data binding %s on base ct-block declarations', (dataBinding) => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_example_component_body" ${dataBinding}>
                <p>{{ body }}</p>
            </ct-block>
            </template>
            <script setup>
            const scope = {};
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'ct-authored-data.vue')).toThrow(
            'The data binding of <ct-block> is generated by the Contena setup transform and must not be authored.',
        );
    });

    it('rejects a <ct-block extends> in a base component', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="sw_example_component_body">
                <p>{{ body }}</p>
            </ct-block>
            </template>
            <script setup>
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'base-extends.vue')).toThrow(
            '<ct-block extends="..."> is only valid in an override component.',
        );
    });

    it('emits the owned base block names for the ownership registry', () => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_outer">
                <ct-block name="sw_inner" />
            </ct-block>
            </template>
            <script setup>
            const count = 1;

            swDefinePublic({
                count,
            });
            </script>
        `;

        const result = transformOrFail(source, 'base-owned-blocks.vue');

        expect(result.ownedBlockNames).toEqual([
            'sw_outer',
            'sw_inner',
        ]);
    });

    it('rejects v-bind objects on base ct-block declarations', () => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_example_component_body" v-bind="attrs">
                <p>{{ body }}</p>
            </ct-block>
            </template>
            <script setup>
            const attrs = {};
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'ct-authored-v-bind.vue')).toThrow('"v-bind" is not supported');
    });

    it('rejects non-identity attributes on base ct-block declarations', () => {
        const source = stripIndent`
            <template>
            <ct-block name="sw_example_component_body" class="highlight">
                <p>{{ body }}</p>
            </ct-block>
            </template>
            <script setup>
            const body = 'Body';

            swDefinePublic({
                body,
            });
            </script>
        `;

        // ct-block renders as a fragment, so class has no host element; only a static name is allowed.
        expect(() => transformContenaSetupSfc(source, 'ct-authored-class.vue')).toThrow(
            'Only a static "name" attribute is allowed on <ct-block>; "class" is not supported.',
        );
    });
});
