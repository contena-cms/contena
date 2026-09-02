/**
 * @ct-package admin
 */

import path from 'node:path';
import { fileURLToPath } from 'node:url';
import js from '@eslint/js';
import { fixupPluginRules } from '@eslint/compat';
import importX from 'eslint-plugin-import-x';
import jestPlugin from 'eslint-plugin-jest';
import prettier from 'eslint-config-prettier';
import globals from 'globals';
import inclusiveLanguage from 'eslint-plugin-inclusive-language';
import fileProgress from 'eslint-plugin-file-progress';
import filenameRules from 'eslint-plugin-filename-rules';
import listeners from 'eslint-plugin-listeners';
import json from '@eslint/json';
import vueParser from 'vue-eslint-parser';

import ctTestRules from 'eslint-plugin-ct-test-rules';
// The factory is the single source of the base lint setup for admin AND
// extensions. pluginVue/ctDeprecationRules must be the factory's own objects:
// ESLint refuses to redefine a plugin key with a different object reference,
// and the factory blocks register these plugins for overlapping files.
import contenaAdminExtension, { pluginVue, ctCoreRules, ctDeprecationRules } from './extension-tooling/eslint.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// eslint-plugin-filename-rules doesn't define meta.schema, which ESLint 9 treats
// as "no options allowed". Patch the rule to accept an option.
const filenameRulesPatched = {
    ...filenameRules,
    rules: Object.fromEntries(
        Object.entries(filenameRules.rules).map(
            ([
                name,
                rule,
            ]) => [
                name,
                {
                    ...rule,
                    meta: {
                        ...rule.meta,
                        schema: rule.meta?.schema ?? [
                            {
                                oneOf: [
                                    { type: 'string' },
                                    { type: 'object' },
                                ],
                            },
                        ],
                    },
                },
            ],
        ),
    ),
};

const baseRules = {
    'file-progress/activate': 0,
    'max-len': [
        'error',
        125,
        { ignoreRegExpLiterals: true },
    ],
    'import/no-useless-path-segments': 0,
    'import/extensions': [
        'error',
        'ignorePackages',
        {
            js: 'never',
            ts: 'never',
            tsx: 'never',
            vue: 'always',
        },
    ],
    'no-console': [
        'error',
        {
            allow: [
                'warn',
                'error',
            ],
        },
    ],
    'no-warning-comments': [
        'error',
        { location: 'anywhere' },
    ],
    'inclusive-language/use-inclusive-words': 'error',
    'comma-dangle': [
        'error',
        'always-multiline',
    ],
    'ct-core-rules/require-position-identifier': [
        'error',
        {
            components: [
                'ct-button',
                'ct-card',
                'ct-tabs',
                'ct-extension-component-section',
            ],
        },
    ],
    'ct-core-rules/no-tc-translation': 'error',
    'ct-deprecation-rules/private-feature-declarations': 'error',
    'no-restricted-exports': 'off',
    'filename-rules/match': [
        2,
        /^.*(?:\.js|\.ts|\.vue|\.html)$/,
    ],
    'vue/multi-word-component-names': [
        'error',
        {
            ignores: ['index.html'],
        },
    ],
    'func-names': 'off',
    'listeners/no-missing-remove-event-listener': 'error',
    'listeners/matching-remove-event-listener': 'error',
    'listeners/no-inline-function-event-listener': 'error',

    // From @contena-ag/eslint-config-base (airbnb-base overrides)
    'no-multiple-empty-lines': [
        'error',
        { max: 2, maxEOF: 1 },
    ],
    'arrow-parens': 0,
    'arrow-body-style': 0,
    'generator-star-spacing': 0,
    'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
    indent: [
        'error',
        4,
        { SwitchCase: 1 },
    ],
    'no-use-before-define': [
        'error',
        { functions: false },
    ],
    'no-param-reassign': 0,
    'linebreak-style': [
        'error',
        'unix',
    ],
    'object-shorthand': 0,
    'no-useless-escape': 0,
    'no-prototype-builtins': 0,
    'object-curly-newline': [
        'error',
        { consistent: true },
    ],
    'no-underscore-dangle': 0,
    'prefer-destructuring': [
        'off',
        { object: true, array: false },
    ],
    'operator-linebreak': 0,
    'import/no-cycle': 0,
    'class-methods-use-this': 0,
    'no-unused-vars': [
        'error',
        { vars: 'all', args: 'after-used', ignoreRestSiblings: true, caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' },
    ],
    'vue/prefer-import-from-vue': 'off',
    'vue/one-component-per-file': 'off',
};

export default [
    // Global ignores (from .eslintignore)
    {
        ignores: [
            'build/*.js',
            'config/*.js',
            'eslint.config.ts',
            'jest.config.js',
            'jest.config.ts',
            'test/e2e/**/*',
            'scripts/**/*',
            '!scripts/extensionTooling/',
            '!scripts/extensionTooling/**/*',
            // Declaration-only type surface; admin-types imports the gitignored
            // generated entity schema, and spec-types references jest, so both
            // must stay outside the admin's own typed-lint program.
            'extension-tooling/admin-types.d.ts',
            'extension-tooling/spec-types.d.ts',
            'build/vue-setup-transform/**/*.d.ts',
            'build/vue-setup-transform/templates/**/*',
            '**/*.spec.vue2.js',
            '**/*.fixtures.js',
        ],
    },

    { ...js.configs.recommended, ignores: ['**/*.json'] },

    // The shared extension factory supplies the typed-lint bootstrap
    // (tseslint recommendedTypeChecked via projectService) and the common rule
    // sets, so the admin and extension configs cannot drift apart. Host
    // options: the admin IS src (no src-import boundary), tracks deprecated
    // usage through ct-deprecation-rules instead of
    // @typescript-eslint/no-deprecated, type-checks its spec files, and runs
    // its own SFC rules below.
    ...contenaAdminExtension({
        tsconfigRootDir: __dirname,
        srcImportBoundary: false,
        deprecatedApiSeverity: 'off',
        specFiles: 'typed',
    }),

    // Vue plugin setup (global) + parser for .vue files
    ...pluginVue.configs['flat/recommended']
        .filter((c) => c.name === 'vue/base/setup' || c.name === 'vue/base/setup-for-vue')
        .map((c) => ({ ...c, ignores: ['**/*.json'] })),
    // Vue rules scoped to JS and Vue files only (not TS)
    ...pluginVue.configs['flat/recommended']
        .filter((c) => c.name !== 'vue/base/setup' && c.name !== 'vue/base/setup-for-vue')
        .map((c) => ({
            ...c,
            files: [
                '**/*.js',
                '**/*.vue',
            ],
        })),

    // Base config for all files
    {
        ignores: ['**/*.json'],
        plugins: {
            import: importX,
            'inclusive-language': fixupPluginRules(inclusiveLanguage),
            'file-progress': fixupPluginRules(fileProgress),
            'filename-rules': fixupPluginRules(filenameRulesPatched),
            // Deliberately not fixup-wrapped: the wrapper would be a second
            // object under the key the factory already registers.
            'ct-core-rules': ctCoreRules,
            'ct-deprecation-rules': ctDeprecationRules,
            'ct-test-rules': fixupPluginRules(ctTestRules),
            listeners: fixupPluginRules(listeners),
        },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.jest,
                Contena: true,
                VueJS: true,
                Cypress: true,
                cy: true,
                autoStub: true,
                flushPromises: true,
                wrapTestComponent: true,
                resetFilters: true,
            },
        },
        settings: {
            'import-x/resolver': {
                node: {},
                typescript: {
                    alwaysTryTypes: true,
                    project: './tsconfig.json',
                },
                vite: {
                    viteConfig: {
                        resolve: {
                            extensions: [
                                '.js',
                                '.ts',
                                '.vue',
                                '.json',
                                '.less',
                            ],
                            alias: [
                                {
                                    find: 'vue',
                                    replacement: '@vue/compat/dist/vue.esm-bundler.js',
                                },
                                {
                                    find: 'src',
                                    replacement: path.join(__dirname, 'src'),
                                },
                                {
                                    find: 'test',
                                    replacement: path.join(__dirname, 'test'),
                                },
                            ],
                        },
                    },
                },
            },
        },
        rules: {
            ...baseRules,
        },
    },

    // JS files (non-spec): Vue parser + component rules
    {
        files: ['**/*.js'],
        ignores: ['**/*.spec.js'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                sourceType: 'module',
            },
        },
        rules: {
            'ct-core-rules/require-explicit-emits': 'error',
            'ct-core-rules/enforce-async-component-registers': 'error',
            'vue/require-prop-types': 'error',
            'vue/require-default-prop': 'error',
            'vue/no-mutating-props': 'error',
            'vue/component-definition-name-casing': [
                'error',
                'kebab-case',
            ],
            'vue/no-boolean-default': [
                'error',
                'default-false',
            ],
            'vue/order-in-components': [
                'error',
                {
                    order: [
                        'el',
                        'name',
                        'parent',
                        'functional',
                        [
                            'template',
                            'render',
                        ],
                        'inheritAttrs',
                        [
                            'provide',
                            'inject',
                        ],
                        'emits',
                        'extends',
                        'mixins',
                        'model',
                        [
                            'components',
                            'directives',
                            'filters',
                        ],
                        [
                            'props',
                            'propsData',
                        ],
                        'data',
                        'metaInfo',
                        'computed',
                        'watch',
                        'LIFECYCLE_HOOKS',
                        'methods',
                        [
                            'delimiters',
                            'comments',
                        ],
                        'renderError',
                    ],
                },
            ],
            'vue/no-deprecated-destroyed-lifecycle': 'error',
            'vue/no-deprecated-events-api': 'error',
            'vue/require-slots-as-functions': 'error',
            'vue/no-deprecated-props-default-this': 'error',
            'ct-deprecation-rules/no-compat-conditions': ['error'],
            'ct-deprecation-rules/no-empty-listeners': [
                'error',
                'enableFix',
            ],
            'ct-deprecation-rules/no-vue-options-api': 'off',
        },
    },

    // Native setup bindings are initialized as one setup scope. Computed and ref
    // callbacks are lazy, so they may safely refer to a binding declared later in
    // the same scope; eager callbacks are kept in declaration order by the native
    // SFC migration.
    {
        files: ['src/**/*.vue'],
        rules: {
            'no-use-before-define': [
                'error',
                { functions: false, variables: false },
            ],
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    vars: 'all',
                    args: 'after-used',
                    argsIgnorePattern: '^_',
                    ignoreRestSiblings: true,
                    caughtErrors: 'all',
                    caughtErrorsIgnorePattern: '^_',
                    destructuredArrayIgnorePattern: '^_',
                },
            ],
        },
    },

    // Test files
    {
        files: [
            '**/*.spec.js',
            '**/*.spec.ts',
            '**/*.spec/*.js',
            '**/*.spec/*.ts',
            '**/fixtures/*.js',
            'test/**/*.js',
            'test/**/*.ts',
        ],
        ...jestPlugin.configs['flat/recommended'],
        languageOptions: {
            ...jestPlugin.configs['flat/recommended'].languageOptions,
            globals: {
                ...jestPlugin.configs['flat/recommended'].languageOptions?.globals,
                ...globals.node,
                ...globals.commonjs,
            },
        },
        rules: {
            ...jestPlugin.configs['flat/recommended'].rules,
            'ct-test-rules/await-async-functions': 'error',
            'max-len': 0,
            'ct-deprecation-rules/private-feature-declarations': 0,
            'jest/expect-expect': 'error',
            'jest/no-duplicate-hooks': 'error',
            'jest/no-test-return-statement': 'error',
            'jest/prefer-hooks-in-order': 'error',
            'jest/prefer-hooks-on-top': 'error',
            'jest/prefer-to-be': 'error',
            'jest/require-top-level-describe': 'error',
            'jest/prefer-to-contain': 'error',
            'jest/prefer-to-have-length': 'error',
            'jest/consistent-test-it': [
                'error',
                { fn: 'it', withinDescribe: 'it' },
            ],
            'jest/valid-expect': [
                'error',
                { maxArgs: 2 },
            ],
            'jest/no-disabled-tests': 'error',
            'func-names': 'off',
        },
    },
    {
        files: [
            '**/*.spec.js',
            '**/*.spec.ts',
            '**/*.spec/*.spec.js',
            '**/*.spec/*.spec.ts',
        ],
        rules: {
            'ct-test-rules/test-file-max-lines-warning': [
                'warn',
                { max: 500 },
            ],
            'ct-test-rules/test-file-max-lines-error': [
                'error',
                { max: 1000 },
            ],
        },
    },

    // TypeScript rules on top of the factory's typed-lint bootstrap. The
    // factory already spreads tseslint recommendedTypeChecked with
    // projectService — `project` must not reappear anywhere: parserOptions
    // merge per key across flat configs, and typescript-estree throws when
    // both are set.
    {
        files: [
            '**/*.ts',
            '**/*.tsx',
        ],
        rules: {
            ...baseRules,
            '@typescript-eslint/ban-ts-comment': 0,
            '@typescript-eslint/no-unsafe-member-access': 'error',
            '@typescript-eslint/no-unsafe-call': 'error',
            '@typescript-eslint/no-unsafe-assignment': 'error',
            '@typescript-eslint/no-unsafe-return': 'error',
            '@typescript-eslint/explicit-module-boundary-types': 0,
            '@typescript-eslint/prefer-ts-expect-error': 'error',
            'no-shadow': 'off',
            '@typescript-eslint/no-shadow': ['error'],
            '@typescript-eslint/consistent-type-imports': ['error'],
            '@typescript-eslint/no-misused-spread': 'error',
            'import/extensions': [
                'error',
                'ignorePackages',
                { js: 'never', jsx: 'never', ts: 'never', tsx: 'never' },
            ],
            'no-void': 'off',
            'no-unused-vars': 'off',
            '@typescript-eslint/no-unused-vars': [
                'error',
                { caughtErrors: 'all', caughtErrorsIgnorePattern: '^_' },
            ],
            '@typescript-eslint/prefer-promise-reject-errors': 'warn',
            'ct-deprecation-rules/no-compat-conditions': ['error'],
            'ct-core-rules/enforce-async-component-registers': 'error',
            'ct-deprecation-rules/no-empty-listeners': [
                'error',
                'enableFix',
            ],
            'ct-deprecation-rules/no-vue-options-api': 'off',
        },
    },
    {
        ...prettier,
        files: [
            '**/*.js',
            '**/*.ts',
            '**/*.tsx',
            '**/*.vue',
        ],
    },
    {
        files: [
            'extension-tooling/**/*.mjs',
            'scripts/extensionTooling/**/*.ts',
        ],
        rules: {
            'filename-rules/match': 'off',
            'import/extensions': 'off',
            'no-console': 'off',
            'ct-deprecation-rules/private-feature-declarations': 'off',
        },
    },

    {
        files: ['build/vue-setup-transform/**/*.ts'],
        rules: {
            '@typescript-eslint/no-require-imports': 'off',
            '@typescript-eslint/no-unsafe-member-access': 'off',
            '@typescript-eslint/no-unsafe-call': 'off',
            '@typescript-eslint/no-unsafe-assignment': 'off',
            '@typescript-eslint/no-unsafe-return': 'off',
            '@typescript-eslint/no-unsafe-argument': 'off',
            'ct-deprecation-rules/private-feature-declarations': 'off',
        },
    },

    // Snippet JSON files: parse as JSON and flag entries that duplicate a global.default translation
    {
        files: ['src/**/snippet/*.json'],
        language: 'json/json',
        plugins: {
            json,
            'ct-core-rules': ctCoreRules,
        },
        rules: {
            'ct-core-rules/require-global-default-use': 'error',
        },
    },
];
