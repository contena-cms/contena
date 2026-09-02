/**
 *
 * Covers which override-local setup bindings reach a `<ct-block extends>` slot scope.
 *
 * These are the positive cases: reference detection across Vue expression positions, input-alias
 * forwarding, and the scope rules that decide a binding is *not* forwarded (v-for aliases, slot
 * scopes, nested callback patterns). Rejections live in `override-template-guards.spec.ts`.
 */

import { stripIndent, stripWhitespace, transformOrFail } from './helpers';

describe('build/vue-setup-transform override template forwarding', () => {
    it('returns template-used override-local state through a deterministic private namespace', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p>{{ body }}</p>
                <small>{{ info }}</small>
            </ct-block>
            </template>
            <script setup lang="ts">
            import { computed, ref } from 'vue';

            const previousState = useCtPreviousState();
            const info = ref('local');
            const unused = ref('not exposed');
            const body = computed(() => previousState.body.value + info.value);

            ctDefineOverride({
                body,
            });
            </script>
        `;

        const result = transformOrFail(source, 'src/plugin/ct-example-component.override.vue').code;

        expect(result).toContain(
            `<ct-block extends="ct_example_component_body" #default="{ __ctOverride: { [__ctSetupNamespace]: { info } }, body }">`,
        );
        expect(stripWhitespace(result)).toContain(stripWhitespace`
            return {
                body,
                __ctOverride: {
                    [__ctSetupNamespace]: {
                        info,
                    },
                },
            };
        `);
        expect(result).not.toContain('unused,');
    });

    it('does not add generated data scope to override ct-block extensions', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_headline">
                <h2>{{ headline }}</h2>
            </ct-block>
            </template>
            <script setup>
            const headline = 'Headline';

            ctDefineOverride({
                headline,
            });
            </script>
        `;

        const result = transformOrFail(source, 'override-ct-block-data.override.vue').code;

        expect(result).toContain('<ct-block extends="ct_example_component_headline" #default="{ headline }">');
        expect(result).not.toContain(':data="$dataScope"');
    });

    it('detects override-local template references in Vue expression positions', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p v-if="visible">{{ info }}</p>
                <button
                    @[eventName]="track(info)"
                    :title="info"
                    :[dynamicProp]="info"
                    :info
                    v-bind="{ info, label: infoLabel }"
                />
                <span v-for="item in items">{{ item }}{{ info }}</span>
            </ct-block>
            </template>
            <script setup>
            const visible = true;
            const info = 'local';
            const eventName = 'click';
            const track = () => {};
            const dynamicProp = 'title';
            const infoLabel = 'label';
            const items = [];

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'template-references.override.vue').code;

        expect(result).toContain(
            `#default="{ __ctOverride: { [__ctSetupNamespace]: { visible, info, eventName, track, dynamicProp, infoLabel, items } } }"`,
        );
        // The v-for alias `item` is a template-local binding, not a setup reference.
        expect(result).not.toMatch(/\bitem,/);
    });

    it('detects override-local references in TypeScript and optional-chain template expressions', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p>{{ (maybeInfo as string | undefined)?.toUpperCase() }}</p>
                <p>{{ source?.[dynamicKey] }}</p>
            </ct-block>
            </template>
            <script setup lang="ts">
            const maybeInfo = 'local';
            const source = {
                headline: 'Headline',
            };
            const dynamicKey = 'headline';

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'typescript-template-references.override.vue').code;

        expect(result).toContain(`#default="{ __ctOverride: { [__ctSetupNamespace]: { maybeInfo, source, dynamicKey } } }"`);
    });

    it('forwards override input-alias references used in the template', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p>{{ previousState.body }}</p>
            </ct-block>
            </template>
            <script setup>
            const previousState = useCtPreviousState();

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'input-alias-reference.override.vue').code;

        // useCtPreviousState()/useCtProps()/useCtContext() are not returned as independent state, but an
        // override template may still read them, so a referenced alias is forwarded like any setup local.
        expect(result).toContain(`#default="{ __ctOverride: { [__ctSetupNamespace]: { previousState } } }"`);
    });

    it('ignores template identifiers that are not override-local setup references', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                plain info text
                <p>{{ [1].map((info) => info).join(',') }}</p>
                <p>{{ ({ info: localInfo }) => localInfo }}</p>
                <p>{{ ({ info: 'static key only' }) }}</p>
            </ct-block>
            </template>
            <script setup>
            const info = 'local';
            const track = () => {};

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'ignored-template-references.override.vue').code;

        expect(result).not.toContain('__ctOverride');
        expect(result).toContain('return {};');
    });

    it('ignores identifiers shadowed by v-for aliases, slot scopes, and nested callback patterns', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p v-for="({ info, label: localLabel }, index) in rows">
                    {{ info }}{{ localLabel }}{{ index }}{{ rows.length }}
                </p>

                <Child #default="{ info, nested: { localInfo }, items: [firstItem] }">
                    {{ info }}{{ localInfo }}{{ firstItem }}{{ rows.length }}
                </Child>

                <p>{{ items.map(({ info, label: localLabel }) => info + localLabel).join(',') }}</p>
            </ct-block>
            </template>
            <script setup>
            const info = 'setup info';
            const localInfo = 'setup nested info';
            const firstItem = 'setup first item';
            const localLabel = 'setup label';
            const index = 0;
            const rows = [];
            const items = [];

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'template-shadowing-patterns.override.vue').code;

        // Only `rows` and `items` are genuine setup references; every other name is shadowed by a
        // v-for alias, slot scope, or nested callback parameter and must not be forwarded.
        expect(result).toContain(`#default="{ __ctOverride: { [__ctSetupNamespace]: { rows, items } } }"`);
    });

    it.each([
        '#default="{ body }"',
        '#default="slotProps"',
        '#default',
        'v-slot="{ body }"',
    ])('rejects the authored default slot scope %s on ct-block', (slotBinding) => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body" ${slotBinding}>
                <p>{{ info }}</p>
            </ct-block>
            </template>
            <script setup>
            const info = 'local';

            ctDefineOverride({});
            </script>
        `;

        expect(() => transformOrFail(source, 'authored-slot-scope.override.vue')).toThrow(
            'The default slot scope of <ct-block> is generated by the Contena setup transform and must not be authored.',
        );
    });

    it('emits the extended block names for the ownership cross-check', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_headline">
                <h2>headline</h2>
            </ct-block>
            <ct-block extends="ct_example_component_body">
                <p>body</p>
            </ct-block>
            </template>
            <script setup>
            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'extended-names.override.vue');

        expect(result.extendedBlockNames).toEqual([
            'ct_example_component_headline',
            'ct_example_component_body',
        ]);
        expect(result.ownedBlockNames).toEqual([]);
    });

    it('forwards useCtProps() aliases referenced in override slot content', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <p>{{ props.title }}</p>
            </ct-block>
            </template>
            <script setup>
            const props = useCtProps();

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'usect-props-forward.override.vue').code;

        // useCtProps() is both a setup input and a runtime input alias; like useCtPreviousState() its
        // referenced name must reach the generated slot scope, or `props` resolves against the hidden
        // boot component and `props.title` throws during the base component's render.
        expect(result).toContain(`#default="{ __ctOverride: { [__ctSetupNamespace]: { props } } }"`);
    });

    it('forwards references inside named slot binding-pattern defaults', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <Child>
                    <template #item="{ label = fallbackLabel }">{{ label }}</template>
                </Child>
            </ct-block>
            </template>
            <script setup>
            const fallbackLabel = 'fallback';

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'named-slot-default-ref.override.vue').code;

        // The default expression of the named slot `#item` must be scanned like a default slot's, or
        // `fallbackLabel` resolves against the hidden component and `label` silently becomes undefined.
        expect(result).toContain(`#default="{ __ctOverride: { [__ctSetupNamespace]: { fallbackLabel } } }"`);
    });

    it('does not forward a setup binding shadowed by a named slot scope', () => {
        const source = stripIndent`
            <template>
            <ct-block extends="ct_example_component_body">
                <Child>
                    <template #item="{ info }">{{ info }}</template>
                </Child>
            </ct-block>
            </template>
            <script setup>
            const info = 'local';

            ctDefineOverride({});
            </script>
        `;

        const result = transformOrFail(source, 'named-slot-shadow.override.vue').code;

        // `info` inside `#item="{ info }"` is the slot's own binding, so the setup `info` is shadowed
        // and must not be forwarded (over-detection fix).
        expect(result).not.toContain('__ctOverride');
        expect(result).toContain('return {};');
    });
});
