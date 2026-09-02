/**
 * @ct-package framework
 *
 * Guards the eslint factory's host-option contract: the defaults must
 * reproduce the extension behavior exactly (generated plugin configs pass only
 * tsconfigRootDir/extensionRoots), while each host option flips only its own
 * blocks. The factory is an .mjs module Jest cannot import directly, so one
 * node subprocess builds every variant and serializes the relevant block
 * facts.
 */

import { execFileSync } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';

interface BlockSummary {
    name?: string;
    rules?: Record<string, string>;
    globals?: string[];
}

const factoryUrl = pathToFileURL(path.resolve(__dirname, '../../extension-tooling/eslint.mjs')).href;

const probeScript = `
const variants = {
    defaults: {},
    typedSpecs: { specFiles: 'typed' },
    noSrcBoundary: { srcImportBoundary: false },
    splitSeverities: {
        internalApiSeverity: 'warn',
        deprecatedApiSeverity: 'off',
        templateDeprecationSeverity: 'error',
    },
    umbrella: { internalApiSeverity: 'warn' },
};

const { contenaAdminExtension } = await import(${JSON.stringify(factoryUrl)});
const summarize = (config) =>
    config.map((block) => ({
        name: block.name,
        rules: block.rules
            ? Object.fromEntries(
                  Object.entries(block.rules).map(([rule, entry]) => [rule, Array.isArray(entry) ? entry[0] : entry]),
              )
            : undefined,
        globals: block.languageOptions?.globals ? Object.keys(block.languageOptions.globals) : undefined,
    }));
const result = Object.fromEntries(
    Object.entries(variants).map(([key, options]) => [
        key,
        summarize(contenaAdminExtension({ tsconfigRootDir: '/tmp', ...options })),
    ]),
);

process.stdout.write(JSON.stringify(result));
`;

describe('extension-tooling eslint factory host options', () => {
    let variants: Record<string, BlockSummary[]>;

    beforeAll(() => {
        const output = execFileSync(
            process.execPath,
            [
                '--input-type=module',
                '-e',
                probeScript,
            ],
            {
                cwd: path.resolve(__dirname, '../..'),
                encoding: 'utf8',
            },
        );

        variants = JSON.parse(output) as Record<string, BlockSummary[]>;
    });

    function ruleSeverity(blocks: BlockSummary[], blockName: string, rule: string): string | undefined {
        return blocks.find((block) => block.name === blockName)?.rules?.[rule];
    }

    it('reproduces the extension behavior exactly with the extension-facing options alone', () => {
        const blocks = variants.defaults;

        expect(blocks.map((block) => block.name)).toContain('contena/admin-extension/spec-files');
        expect(ruleSeverity(blocks, 'contena/admin-extension/runtime-contract', 'plugin-rules/no-src-imports')).toBe(
            'error',
        );
        expect(ruleSeverity(blocks, 'contena/admin-extension/runtime-contract', 'no-restricted-imports')).toBe('error');
        expect(ruleSeverity(blocks, 'contena/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
        expect(
            ruleSeverity(
                blocks,
                'contena/admin-extension/template-deprecations',
                'ct-deprecation-rules/no-deprecated-components',
            ),
        ).toBe('error');
    });

    it('bakes native setup support into every extension config by default', () => {
        const nativeSetup = variants.defaults.find((block) => block.name === 'contena/admin-extension/native-setup');

        expect(nativeSetup).toBeDefined();
        expect(nativeSetup?.rules?.['ct-core-rules/valid-contena-setup']).toBe('error');
        expect(nativeSetup?.rules?.['ct-core-rules/native-setup-filename']).toBe('error');
        expect(nativeSetup?.globals).toEqual(
            expect.arrayContaining([
                'ctDefinePublic',
                'ctDefineOverride',
                'useCtPreviousState',
                'useCtProps',
                'useCtContext',
            ]),
        );
    });

    it('turns off only the no-unsafe rules on .vue while keeping the rest of the type-aware set', () => {
        const blocks = variants.defaults;

        for (const rule of [
            '@typescript-eslint/no-unsafe-argument',
            '@typescript-eslint/no-unsafe-assignment',
            '@typescript-eslint/no-unsafe-call',
            '@typescript-eslint/no-unsafe-member-access',
            '@typescript-eslint/no-unsafe-return',
        ]) {
            expect(ruleSeverity(blocks, 'contena/admin-extension/vue-component-type-unsafety', rule)).toBe('off');
        }

        expect(
            ruleSeverity(blocks, 'contena/admin-extension/vue-typescript', '@typescript-eslint/no-floating-promises'),
        ).toBe('error');
        expect(ruleSeverity(blocks, 'contena/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
        expect(ruleSeverity(blocks, 'contena/admin-extension/vue-typescript', '@typescript-eslint/no-unused-vars')).not.toBe(
            'off',
        );
        expect(blocks.map((block) => block.name)).not.toContain('contena/admin-extension/vue-untyped');
    });

    it('keeps no-unused-vars on for .vue (the v10 parser links interpolation usage)', () => {
        const blocks = variants.defaults;

        expect(blocks.map((block) => block.name)).not.toContain('contena/admin-extension/vue-template-usage');
        expect(ruleSeverity(blocks, 'contena/admin-extension/vue-typescript', '@typescript-eslint/no-unused-vars')).not.toBe(
            'off',
        );
        expect(
            ruleSeverity(blocks, 'contena/admin-extension/vue-component-type-unsafety', 'no-unused-vars'),
        ).toBeUndefined();
        expect(
            ruleSeverity(blocks, 'contena/admin-extension/vue-component-type-unsafety', '@typescript-eslint/no-unused-vars'),
        ).toBeUndefined();
    });

    it("omits the spec-files block entirely for specFiles: 'typed'", () => {
        const names = variants.typedSpecs.map((block) => block.name);

        expect(names).not.toContain('contena/admin-extension/spec-files');
        // Everything else stays: the block count shrinks by exactly one.
        expect(variants.typedSpecs).toHaveLength(variants.defaults.length - 1);
    });

    it('flips only the two src-import rules for srcImportBoundary: false', () => {
        const blocks = variants.noSrcBoundary;

        // The block itself survives (it also carries globals and the plugin
        // registration) — only the rule severities flip.
        expect(blocks.map((block) => block.name)).toContain('contena/admin-extension/runtime-contract');
        expect(ruleSeverity(blocks, 'contena/admin-extension/runtime-contract', 'plugin-rules/no-src-imports')).toBe('off');
        expect(ruleSeverity(blocks, 'contena/admin-extension/runtime-contract', 'no-restricted-imports')).toBe('off');
        expect(ruleSeverity(blocks, 'contena/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'error',
        );
    });

    it('lets the deprecation severities diverge from the umbrella knob', () => {
        const blocks = variants.splitSeverities;

        expect(ruleSeverity(blocks, 'contena/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe('off');
        expect(
            ruleSeverity(
                blocks,
                'contena/admin-extension/template-deprecations',
                'ct-deprecation-rules/no-deprecated-component-usage',
            ),
        ).toBe('error');
    });

    it('keeps the umbrella knob driving both deprecation surfaces by default', () => {
        const blocks = variants.umbrella;

        expect(ruleSeverity(blocks, 'contena/admin-extension/api-boundary', '@typescript-eslint/no-deprecated')).toBe(
            'warn',
        );
        expect(
            ruleSeverity(
                blocks,
                'contena/admin-extension/template-deprecations',
                'ct-deprecation-rules/no-deprecated-components',
            ),
        ).toBe('warn');
    });
});
