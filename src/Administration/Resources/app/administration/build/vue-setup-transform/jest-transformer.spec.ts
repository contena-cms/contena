/**
 * @sw-package framework
 */

import contenaSetupVueTransformer from '../../test/transformer/contenaSetupVueTransformer';
import type * as transformModuleType from './index';
import { stripIndent } from './index.spec/helpers';

type TransformModule = typeof transformModuleType;

const browserslistDataWarning = {
    method: 'warn' as const,
    msg: 'Browserslist: browsers data',
};

describe('test/transformer/contenaSetupVueTransformer integration', () => {
    beforeAll(() => {
        global.allowedErrors.push(browserslistDataWarning);
    });

    afterAll(() => {
        const warningIndex = global.allowedErrors.indexOf(browserslistDataWarning);

        if (warningIndex !== -1) {
            global.allowedErrors.splice(warningIndex, 1);
        }
    });

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
            swDefinePublic({});
            </script>
        `;

        let error: unknown;

        try {
            contenaSetupVueTransformer[method](source, '/example/sw-broken.vue', { config: {} }, { instrument: false });
        } catch (thrown) {
            error = thrown;
        }

        expect(error).toHaveProperty('message', expect.stringContaining('/example/sw-broken.vue:5:23\n'));
        expect(error).toHaveProperty('stack', expect.stringContaining('5  |  const broken = { a: 1 b: 2 };'));
        expect(error).toHaveProperty('stack', expect.not.stringContaining('\n    at '));
    });

    it('keeps the throw-site frames of an analyzer bug', () => {
        let error: unknown;

        jest.isolateModules(() => {
            const transform = jest.requireActual<TransformModule>('./index.js');
            const internalError = new transform.ContenaSetupInternalError('Overlapping Contena setup source edits.', 0);
            internalError.loc = { file: '/example/sw-internal.vue', line: 1, column: 0 };
            internalError.frame = '1  |  <template />';

            // No authored input reaches an analyzer bug, so the transformer gets a transform that throws one. The
            // spread keeps the error classes the transformer checks against.
            jest.doMock('./index.js', () => ({
                ...transform,
                transformContenaSetupSfc: () => {
                    throw internalError;
                },
            }));

            const transformer = jest.requireActual<typeof contenaSetupVueTransformer>(
                '../../test/transformer/contenaSetupVueTransformer',
            );

            try {
                transformer.process('<template />', '/example/sw-internal.vue', { config: {} }, { instrument: false });
            } catch (thrown) {
                error = thrown;
            }
        });
        jest.dontMock('./index.js');

        expect(error).toHaveProperty('message', expect.stringContaining('/example/sw-internal.vue:1:1\n1  |  <template />'));
        expect(error).toHaveProperty(
            'stack',
            expect.stringContaining(`ContenaSetupInternalError: ${(error as Error).message}\n    at `),
        );
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

            swDefinePublic({
                count,
            });
            </script>
        `;

        const transformed = contenaSetupVueTransformer.process(
            source,
            '/administration/src/sw-jest-transform-fixture.vue',
            { config: {} },
            { instrument: false },
        ) as string | { code: string };
        const code = typeof transformed === 'string' ? transformed : transformed.code;

        expect(code).toContain('Contena.Component.attachOverrides(');
        expect(code).toContain("'sw-jest-transform-fixture'");
        expect(code).toContain('props: {');
        expect(code).toContain('emits: ["save"]');
        expect(code).toContain('exports.default');
        expect(code).not.toContain('swDefinePublic');
    });
});
