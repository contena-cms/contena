/**
 * Covers the Contena marker macros and override runtime helpers: `ctDefinePublic()` /
 * `ctDefineOverride()` shape, placement, and mode rules, plus `useCtProps()` /
 * `useCtPreviousState()` rejected in base mode.
 */

import { stripIndent, transformOrFail, transformContenaSetupSfc } from './helpers';

describe('build/vue-setup-transform setup markers', () => {
    it.each([
        [
            'ctDefinePublic({ [dynamicKey]: count });',
            'ctDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'ctDefinePublic({ public: count });',
            'ctDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            "ctDefinePublic({ 'public': count });",
            'ctDefinePublic() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'ctDefinePublic({ ...publicState });',
            'Spread properties are not supported inside ctDefinePublic().',
        ],
        [
            'ctDefinePublic(publicState);',
            'ctDefinePublic() requires exactly one object-literal argument.',
        ],
        [
            // A nested call is not rejected on its own, matching how Vue treats its own macros: it only
            // recognises them at the top level. The marker is simply missing where it counts, so the
            // required-marker rule reports it.
            'if (true) { ctDefinePublic({ count }); }',
            'A base Contena setup component must declare its extension surface.',
        ],
        [
            'const __ctOverride = {}; ctDefinePublic({ __ctOverride });',
            '"__ctOverride" is reserved for Contena override-private state and cannot be exposed with ctDefinePublic().',
        ],
    ])('rejects invalid ctDefinePublic usage: %s', (publicMarker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${publicMarker}
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'public.vue')).toThrow(expectedMessage);
    });

    it('requires ctDefineOverride() in override mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'missing-override.override.vue')).toThrow(
            'ctDefineOverride() must be called exactly once at the top level of an override Contena setup block.',
        );
    });

    it('requires ctDefinePublic() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            </script>
        `;

        // A transformed base component is an extension point - its filename is the public override
        // target - so the marker is mandatory even with nothing public, rather than letting a file become
        // extendable just by carrying a <script setup> block.
        expect(() => transformContenaSetupSfc(source, 'missing-public.vue')).toThrow(
            'A base Contena setup component must declare its extension surface. Add ctDefinePublic({ ... }) ' +
                'at the top level - pass an empty object if no binding is public.',
        );
    });

    it.each([
        [
            'public.vue',
            'ctDefinePublic({ count });\nctDefinePublic({});',
            'Only one ctDefinePublic() call is allowed in a base Contena setup block.',
        ],
        [
            'override.override.vue',
            'ctDefineOverride({ count });\nctDefineOverride({});',
            'Only one ctDefineOverride() call is allowed in an override Contena setup block.',
        ],
    ])('rejects a second top-level marker call in %s', (filename, markers, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${markers}
            </script>
        `;

        // Only the first marker is carried downstream, so this is the one check that keeps a duplicate
        // from being silently ignored - it counts off the full macro-entry list rather than that field.
        expect(() => transformContenaSetupSfc(source, filename)).toThrow(expectedMessage);
    });

    it.each([
        [
            'public.vue',
            'const marker = ctDefinePublic({ count });',
            'ctDefinePublic() is a compile-time marker and returns nothing.',
        ],
        [
            'override.override.vue',
            'const marker = ctDefineOverride({ count });',
            'ctDefineOverride() is a compile-time marker and returns nothing.',
        ],
    ])('rejects a marker assigned to a variable in %s', (filename, marker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${marker}
            </script>
        `;

        // The marker statement is removed from the output, so a declaration form would leave the call
        // behind as a reference to a name that does not exist at runtime - and its entries would be
        // silently ignored, because only the statement form is read.
        expect(() => transformContenaSetupSfc(source, filename)).toThrow(expectedMessage);
    });

    it('accepts an empty ctDefinePublic() for a base component with no public state', () => {
        const source = stripIndent`
            <script setup>
            const internalOnly = 1;

            ctDefinePublic({});
            </script>
        `;

        const result = transformOrFail(source, 'empty-public.vue').code;

        expect(result).toContain('public: {},');
        expect(result).toContain('internalOnly: __ctSetupAuthor_internalOnly,');
    });

    it('rejects ctDefineOverride() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ctDefineOverride({ count });
            </script>
        `;

        // The wrong-mode complaint must win over the missing-ctDefinePublic() one: both are true here,
        // but only this one explains what the author actually did wrong.
        expect(() => transformContenaSetupSfc(source, 'base-override.vue')).toThrow(
            'ctDefineOverride() is a Contena setup compile-time macro for override components. ' +
                'It declares which base component bindings this override replaces. ' +
                'Base components must use ctDefinePublic() to expose overrideable setup bindings instead.',
        );
    });

    it('rejects imported and unknown ctDefineOverride() bindings', () => {
        const source = stripIndent`
            <script setup>
            import { computed } from 'vue';

            const count = 1;

            ctDefineOverride({
                computed,
                missing,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-import.override.vue')).toThrow(
            'Imported binding "computed" cannot be exposed with ctDefineOverride().',
        );
    });

    it.each([
        [
            'ctDefineOverride({ [dynamicKey]: count });',
            'ctDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'ctDefineOverride({ override: count });',
            'ctDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            "ctDefineOverride({ 'override': count });",
            'ctDefineOverride() only supports shorthand bindings such as { a, b }.',
        ],
        [
            'ctDefineOverride({ ...overrideState });',
            'Spread properties are not supported inside ctDefineOverride().',
        ],
        [
            'ctDefineOverride(overrideState);',
            'ctDefineOverride() requires exactly one object-literal argument.',
        ],
        [
            // As above: a nested call is left alone, and the required-marker rule reports the absence.
            'if (true) { ctDefineOverride({ count }); }',
            'ctDefineOverride() must be called exactly once at the top level',
        ],
        [
            'ctDefineOverride({ count, count });',
            'Duplicate override Contena setup binding key "count".',
        ],
        [
            'const __ctOverride = {}; ctDefineOverride({ __ctOverride });',
            '"__ctOverride" is reserved for Contena override-private state and cannot be exposed with ctDefineOverride().',
        ],
    ])('rejects invalid ctDefineOverride usage: %s', (overrideMarker, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            const count = 1;
            ${overrideMarker}
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override.override.vue')).toThrow(expectedMessage);
    });

    it('rejects useCtProps() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const props = useCtProps();
            const count = props.initialCount ?? 0;

            ctDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'base-use-ct-props.vue')).toThrow(
            "useCtProps() is only supported in override Contena setup blocks. Base components must use Vue's defineProps() macro instead.",
        );
    });

    it('rejects useCtPreviousState() in base mode', () => {
        const source = stripIndent`
            <script setup>
            const previousState = useCtPreviousState();
            const count = 1;

            ctDefinePublic({
                count,
            });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'base-previous-state.vue')).toThrow(
            'useCtPreviousState() is only supported in override Contena setup blocks.',
        );
    });
});
