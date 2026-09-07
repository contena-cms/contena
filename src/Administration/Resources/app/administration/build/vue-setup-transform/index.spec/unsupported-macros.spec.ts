/**
 * Covers Vue macros the transform rejects: unsupported macros such as `defineModel()` and
 * `defineExpose()` (nested calls stay untouched, like compiler-sfc), and base-only macros used in
 * override mode.
 */

import { stripIndent, transformOrFail, transformContenaSetupSfc } from './helpers';

describe('build/vue-setup-transform unsupported macros', () => {
    it.each([
        [
            'defineModel()',
            'Vue macro defineModel() is not supported inside Contena setup blocks.',
        ],
        [
            'defineExpose({})',
            'defineExpose() is not supported inside Contena setup blocks.',
        ],
    ])('rejects unsupported Vue macro %s', (macro, expectedMessage) => {
        const source = stripIndent`
            <script setup>
            ${macro};
            const count = 1;
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'macro.vue')).toThrow(expectedMessage);
    });

    it('ignores nested unsupported Vue macros like compiler-sfc does', () => {
        const source = stripIndent`
            <script setup>
            function createModel() {
                return defineModel();
            }

            const count = 1;
            ctDefinePublic({ count });
            </script>
        `;

        const result = transformOrFail(source, 'nested-unsupported-macro.vue').code;

        // The enclosing function is renamed as a top-level binding, but the nested defineModel() call
        // is a function-local and stays untouched (never rejected as a top-level unsupported macro).
        expect(result).toContain('return defineModel();');
    });

    it('rejects defineProps() in override mode', () => {
        const source = stripIndent`
            <script setup>
            const props = defineProps();
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-props.override.vue')).toThrow(
            'defineProps() is only supported in base Contena setup blocks.',
        );
    });

    it('rejects withDefaults() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const props = withDefaults(defineProps<{ label?: string }>(), {
                label: 'fallback',
            });
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-props-with-defaults.override.vue')).toThrow(
            'withDefaults() is only supported in base Contena setup blocks.',
        );
    });

    it('rejects defineEmits() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const emit = defineEmits(['save']);
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-emits.override.vue')).toThrow(
            'defineEmits() is only supported in base Contena setup blocks.',
        );
    });

    // An override cannot be told to use ctDefinePublic(): that marker is itself rejected in override
    // mode, so the advice would only swap one error for the next.
    it('sends an override authoring defineExpose() to ctDefineOverride(), not ctDefinePublic()', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineExpose({});
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-expose.override.vue')).toThrow(
            'Declare replacement bindings with ctDefineOverride({ ... }) instead.',
        );
    });

    it('points an authored defineExpose() at ctDefinePublic() instead', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const count = 1;
            defineExpose({ count });
            ctDefinePublic({ count });
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'base-expose.vue')).toThrow(
            'Use ctDefinePublic({ ... }) instead, which will call it for you automatically.',
        );
    });

    it('rejects defineSlots() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const slots = defineSlots();
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-slots.override.vue')).toThrow(
            'defineSlots() is only supported in base Contena setup blocks.',
        );
    });

    it('rejects defineOptions() in override mode', () => {
        const source = stripIndent`
            <script setup lang="ts">
            defineOptions({ inheritAttrs: false });
            ctDefineOverride({});
            </script>
        `;

        expect(() => transformContenaSetupSfc(source, 'override-options.override.vue')).toThrow(
            'defineOptions() is only supported in base Contena setup blocks.',
        );
    });
});
