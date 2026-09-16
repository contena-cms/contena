import contenaSetupVueTransformer from '../../test/transformer/contenaSetupVueTransformer';
import { stripIndent } from './index.spec/helpers';

describe('test/transformer/contenaSetupVueTransformer integration', () => {
    it.each([
        'process',
        'getCacheKey',
    ] as const)('formats author diagnostics for Jest through %s', (method) => {
        const source = stripIndent`
            <template>
                <div />
            </template>
            <script setup>
            const broken = { a: 1 b: 2 };
            ctDefinePublic({});
            </script>
        `;

        let error: unknown;

        try {
            contenaSetupVueTransformer[method](source, '/example/ct-broken.vue', { config: {} }, { instrument: false });
        } catch (thrown) {
            error = thrown;
        }

        expect(error).toHaveProperty('message', expect.stringContaining('/example/ct-broken.vue:5:23\n'));
        expect(error).toHaveProperty('stack', expect.stringContaining('5  |  const broken = { a: 1 b: 2 };'));
    });

    it('applies the Contena setup transform before delegating Vue files to vue-jest', () => {
        const source = stripIndent`
            <template>
                <button type="button" @click="emit('save', count)">
                    {{ label }}: {{ count }}
                </button>
            </template>

            <script setup lang="ts">
            import { ref } from 'vue';

            const props = withDefaults(defineProps<{
                label?: string,
            }>(), {
                label: 'Counter',
            });
            const emit = defineEmits<{
                save: [value: number],
            }>();
            const count = ref(1);

            ctDefinePublic({
                count,
            });
            </script>
        `;

        const transformed = contenaSetupVueTransformer.process(
            source,
            '/administration/src/ct-jest-transform-fixture.vue',
            { config: {} },
            { instrument: false },
        ) as string | { code: string };
        const code = typeof transformed === 'string' ? transformed : transformed.code;

        expect(code).toContain('Contena.Component.attachOverrides(');
        expect(code).toContain("'ct-jest-transform-fixture'");
        expect(code).toContain('props: {');
        expect(code).toContain('emits: ["save"]');
        expect(code).toContain('exports.default');
        expect(code).not.toContain('ctDefinePublic');
    });
});
