/**
 * @ct-package framework
 *
 * Portable flat-config factory for Administration extensions. All parsers,
 * plugins, and rule packages resolve from the installed Administration's own
 * node_modules, so extensions never install lint dependencies themselves.
 */

import eslintJs from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import tseslint from 'typescript-eslint';
import swDeprecationRules from 'eslint-plugin-ct-deprecation-rules';
import swPluginRules from 'eslint-plugin-plugin-rules';
import swCoreRules from 'eslint-plugin-ct-core-rules';

const javascriptFilePatterns = [
    '**/*.js',
    '**/*.mjs',
    '**/*.cjs',
];
const typescriptFilePatterns = [
    '**/*.ts',
    '**/*.tsx',
];
const vueFilePatterns = ['**/*.vue'];
const specFilePatterns = [
    '**/*.spec.ts',
    '**/*.spec.tsx',
    '**/*.spec.js',
];
const templateFilePatterns = [...vueFilePatterns];
/** Extensions consume the Administration through the global Contena object, never through its sources. */
const NO_ADMIN_INTERNALS_RULE = [
    'error',
    {
        patterns: [
            {
                group: [
                    'src',
                    'src/*',
                    '@administration/*',
                    '**/src/Administration/Resources/app/administration/src/*',
                ],
                message: 'Use the global Contena object instead of importing Administration internals.',
            },
        ],
    },
];
const typedRules = Object.assign({}, ...tseslint.configs.recommendedTypeChecked.map((config) => config.rules ?? {}));
/**
 * Creates the shared flat config for Administration extensions.
 *
 * - `tsconfigRootDir` (required): the directory ESLint resolves tsconfigs
 *   from — the project root for generated root configs, the plugin's admin
 *   folder for shim-based configs.
 * - `extensionRoots`: relative paths (from the config file location) that
 *   scope every file glob to discovered Administration extension sources.
 * - `internalApiSeverity`: umbrella severity for the API-boundary rules
 *   (usage of `@deprecated` members). Internal plugins that intentionally
 *   consume internal APIs may lower this in their own config.
 * - `ignores`: additional global ignore patterns.
 *
 * Host options — the Administration's own config composes this factory too
 * (so the base rules cannot drift apart); these knobs decouple the pieces a
 * host needs to own itself. Extension defaults reproduce the umbrella knob:
 *
 * - `deprecatedApiSeverity`: severity of `@typescript-eslint/no-deprecated`
 *   alone (defaults to `internalApiSeverity`).
 * - `templateDeprecationSeverity`: severity of the `ct-deprecation-rules`
 *   template rules alone (defaults to `internalApiSeverity`). A host that
 *   needs rule *options* re-declares the entries in a later config block —
 *   flat-config rule entries replace wholesale.
 * - `srcImportBoundary`: `false` disables the "never import Administration
 *   internals" rules — only sensible for the Administration itself, which IS
 *   `src`.
 * - `specFiles`: `'untyped'` (default) parses spec files standalone with the
 *   type-checked rules off; `'typed'` omits that block entirely, for hosts
 *   whose tsconfig covers spec files with jest types.
 */
export function contenaAdminExtension(options = {}) {
    const {
        tsconfigRootDir,
        extensionRoots = [],
        internalApiSeverity = 'error',
        ignores = [],
        deprecatedApiSeverity = internalApiSeverity,
        templateDeprecationSeverity = internalApiSeverity,
        srcImportBoundary = true,
        specFiles = 'untyped',
    } = options;

    if (!tsconfigRootDir) {
        throw new Error(
            'contenaAdminExtension requires the "tsconfigRootDir" option. ' +
                'Pass the directory that contains your eslint config, e.g. ' +
                'contenaAdminExtension({ tsconfigRootDir: import.meta.dirname }).',
        );
    }

    const scope = (patterns) => {
        if (extensionRoots.length === 0) {
            return patterns;
        }

        return extensionRoots.flatMap((extensionRoot) =>
            patterns.map((pattern) => `${extensionRoot.replace(/\/+$/, '')}/${pattern}`),
        );
    };

    // No program covers spec files, so type-aware rules have nothing to resolve
    // them against: specs are parsed standalone with the jest globals available
    // and the type-checked rules switched off.
    const specFilesConfig = {
        ...tseslint.configs.disableTypeChecked,
        name: 'contena/admin-extension/spec-files',
        files: scope(specFilePatterns),
        languageOptions: {
            ...tseslint.configs.disableTypeChecked.languageOptions,
            globals: { ...globals.jest },
        },
    };

    const config = [
        {
            name: 'contena/admin-extension/ignores',
            ignores: [
                '**/node_modules/**',
                '**/Resources/public/**',
                '**/dist/**',
                ...ignores,
            ],
        },
        {
            ...eslintJs.configs.recommended,
            name: 'contena/admin-extension/javascript',
            files: scope(javascriptFilePatterns),
        },
        ...tseslint.configs.recommendedTypeChecked.map((typescriptConfig, index) => ({
            ...typescriptConfig,
            name: `contena/admin-extension/typescript-${index}`,
            files: scope(typescriptFilePatterns),
            languageOptions: {
                ...typescriptConfig.languageOptions,
                parserOptions: {
                    ...typescriptConfig.languageOptions?.parserOptions,
                    projectService: true,
                    tsconfigRootDir,
                },
            },
        })),
        ...pluginVue.configs['flat/recommended'].map((vueConfig, index) => ({
            ...vueConfig,
            name: `contena/admin-extension/vue-${index}`,
            files: scope(vueFilePatterns),
        })),
        {
            name: 'contena/admin-extension/vue-typescript',
            files: scope(vueFilePatterns),
            languageOptions: {
                parser: vueParser,
                parserOptions: {
                    parser: tseslint.parser,
                    projectService: true,
                    extraFileExtensions: ['.vue'],
                    tsconfigRootDir,
                },
            },
            plugins: {
                '@typescript-eslint': tseslint.plugin,
            },
            rules: {
                ...typedRules,
                'vue/html-indent': [
                    'error',
                    4,
                    { baseIndent: 1 },
                ],
            },
        },
        {
            name: 'contena/admin-extension/native-setup',
            files: scope(vueFilePatterns),
            languageOptions: {
                globals: {
                    swDefinePublic: 'readonly',
                    swDefineOverride: 'readonly',
                    useSwPreviousState: 'readonly',
                    useSwProps: 'readonly',
                    useSwContext: 'readonly',
                },
            },
            plugins: {
                'ct-core-rules': swCoreRules,
            },
            rules: {
                'ct-core-rules/valid-contena-setup': 'error',
                'ct-core-rules/native-setup-filename': 'error',
            },
        },
        {
            name: 'contena/admin-extension/runtime-contract',
            files: scope([
                ...javascriptFilePatterns,
                ...typescriptFilePatterns,
                ...vueFilePatterns,
            ]),
            languageOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
                globals: {
                    ...globals.browser,
                    Contena: 'readonly',
                },
            },
            plugins: {
                'plugin-rules': swPluginRules,
            },
            rules: {
                'plugin-rules/no-src-imports': srcImportBoundary ? 'error' : 'off',
                'no-restricted-imports': srcImportBoundary ? NO_ADMIN_INTERNALS_RULE : 'off',
            },
        },
        {
            name: 'contena/admin-extension/api-boundary',
            files: scope([
                ...typescriptFilePatterns,
                ...vueFilePatterns,
            ]),
            rules: {
                '@typescript-eslint/no-deprecated': deprecatedApiSeverity,
            },
        },
        {
            name: 'contena/admin-extension/template-deprecations',
            files: scope(templateFilePatterns),
            plugins: {
                'ct-deprecation-rules': swDeprecationRules,
            },
            rules: {
                'ct-deprecation-rules/no-deprecated-components': templateDeprecationSeverity,
                'ct-deprecation-rules/no-deprecated-component-usage': templateDeprecationSeverity,
            },
        },
        // typescript-eslint types `.vue` SFCs only partially - without the Vue
        // language service the program falls back to `any` for some Vue surfaces
        // (component instances, `defineExpose`/`useTemplateRef`, async components),
        // which makes the `no-unsafe-*` family fire on idiomatic Vue. Turn just
        // those five off for `.vue`, after the `vue-typescript` block so this
        // overrides the entries `recommendedTypeChecked` set there - the same
        // trade-off `@vue/eslint-config-typescript` makes via its
        // `allowComponentTypeUnsafety` default. The resolvable type-aware rules
        // and no-unused-vars stay on.
        {
            name: 'contena/admin-extension/vue-component-type-unsafety',
            files: scope(vueFilePatterns),
            rules: {
                '@typescript-eslint/no-unsafe-argument': 'off',
                '@typescript-eslint/no-unsafe-assignment': 'off',
                '@typescript-eslint/no-unsafe-call': 'off',
                '@typescript-eslint/no-unsafe-member-access': 'off',
                '@typescript-eslint/no-unsafe-return': 'off',
            },
        },
        ...(specFiles === 'typed' ? [] : [specFilesConfig]),
    ];

    return config;
}

export { pluginVue, swCoreRules, swDeprecationRules, swPluginRules, tseslint };

export default contenaAdminExtension;
