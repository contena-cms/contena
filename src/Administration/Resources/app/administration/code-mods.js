const { ESLint } = require('eslint');
const process = require('process');
const commandLineArgs = require('command-line-args');
const getUsage = require('command-line-usage');
const fs = require('fs-extra');
const colors = require('picocolors');
const path = require('path');
const { globSync } = require('glob');

// Available Contena versions
const contenaVersions = [
    '6.6',
    '6.7',
];

// Command options
const optionDefinitions = [
    {
        description: 'Prints this help page.',
        name: 'help',
        alias: 'h',
        type: Boolean,
    },
    {
        description: 'Plugin folder name inside custom/plugins!',
        name: 'plugin-name',
        alias: 'p',
        type: String,
    },
    {
        description: 'Contena root. Default ../../../../../',
        name: 'contena-root',
        alias: 'r',
        type: String,
    },
    {
        description: 'Source files to fix. Default "src/Resources/app/administration/src"',
        name: 'src',
        alias: 's',
        multiple: true,
        type: String,
    },
    {
        description: 'Runs the automatic code fixer.',
        name: 'fix',
        alias: 'f',
        type: Boolean,
    },
    {
        description: 'Ignores that the folder is not a git repository.',
        name: 'ignore-git',
        alias: 'G',
        type: Boolean,
    },
    {
        description: `Define the Contena version for loading the correct codemods. Available: ${contenaVersions.join(', ')}`,
        name: 'contena-version',
        alias: 'v',
        type: String,
    },
];

// Command help
const sections = [
    {
        header: 'Contena Admin code mods',
        content: 'Run contena code mods in your plugin!',
    },
    {
        header: 'Synopsis',
        content: [
            '{bold Run as npm script inside <contenaRoot>/src/Administration/Resources/app/administration}:',
            '$ npm run code-mods -- [{bold --fix}] {bold --plugin-name} {underline CtExamplePlugin}',
            '$ npm run code-mods -- {bold --help}',
            '',
            '{bold Run as composer script inside <contenaRoot>}:',
            '$ composer run admin:code-mods -- [{bold --fix}] {bold --plugin-name} {underline CtExamplePlugin}',
            '$ composer run admin:code-mods -- {bold --help}',
        ],
    },
    {
        header: 'Options',
        optionList: optionDefinitions,
    },
];

// Use IIFE to be able to await
(async function codeMods() {
    // Parse options into object
    const options = commandLineArgs(optionDefinitions);
    if (options.help || Object.keys(options).length === 0) {
        // eslint-disable-next-line no-console
        console.log(getUsage(sections));
        process.exit();
    }

    const contenaVersion = options['contena-version'];
    if (contenaVersion && !contenaVersions.includes(contenaVersion)) {
        console.error(colors.red('Invalid Contena version. Available: 6.6, 6.7'));
        process.exit(1);
    }

    const pluginName = options['plugin-name'];
    if (!pluginName) {
        console.error(colors.red('You have to specify your plugin name.'));
        process.exit(1);
    }

    let customPluginsPath = path.resolve('../../../../../custom/plugins');
    if (options['contena-root']) {
        let optionsRoot = options['contena-root'];

        // remove trailing slash
        optionsRoot = optionsRoot.replace(/\/$/, '');

        customPluginsPath = path.resolve(`${optionsRoot}/custom/plugins`);
    }

    // Looking for all the possible plugins inside the plugin folder using a path pattern
    const pluginDir = `${customPluginsPath}/${pluginName}`;
    const ADMIN_PLUGIN_PATH_PATTERN = `${pluginDir}/src/**/Resources/app/administration/src/`;
    const srcDirectories = options.src || globSync(ADMIN_PLUGIN_PATH_PATTERN, { nodir: false });

    if (!srcDirectories.length) {
        console.error(colors.red(`Unable to locate "${pluginName}" in "${customPluginsPath}"!`));
        // eslint-disable-next-line no-console
        console.log(colors.red('You need to specify at least one src folder or use the default!'));
        process.exit(1);
    }

    let pluginIsGitRepository = false;
    try {
        pluginIsGitRepository = fs.statSync(`${pluginDir}/.git`).isDirectory();
    } catch (_e) {
        pluginIsGitRepository = false;
    }

    if (!pluginIsGitRepository && !options['ignore-git']) {
        console.error(
            colors.red('Plugin is no git repository. Make sure your plugin is in git and has a clean work space!'),
        );
        console.error(colors.red('If you feel adventurous you can ignore this with -G... You where warned!'));
        process.exit(1);
    }

    createPluginsTsConfigFile(pluginName);

    let index = 1;
    for (const srcDir of srcDirectories) {
        console.warn(colors.green(`(${index}/${srcDirectories.length}) Checking ${srcDir}...`));
        index += 1;

        const workingDir = `./${pluginName}`;
        if (fs.existsSync(workingDir)) {
            fs.rmSync(workingDir, { recursive: true, force: true });
        }

        // copy plugin into admin folder to make eslint work correctly
        copyFolderRecursiveSync(srcDir, workingDir);

        const fix = options.fix;
        try {
            await lintFiles([workingDir], fix, contenaVersion);

            // only copy back changes if fix is requested
            if (fix) {
                // copy back changes and delete working dir
                copyFolderRecursiveSync(workingDir, srcDir);
            }
        } catch (e) {
            console.error(colors.red(`Linting failed! ${srcDir}`));
            console.error(e);
        } finally {
            // always remove the working dir, because src files could collide
            fs.rmSync(workingDir, { recursive: true, force: true });
        }
    }

    removePluginsTsConfigFile();
})();

// Helper functions
function copyFolderRecursiveSync(source, target) {
    // don't copy .git folder - permission problems
    const excludeFolders = [
        '.git',
        'node_modules',
        'vendor',
    ]; // Folders to exclude

    // Ensure target directory exists
    fs.ensureDirSync(target);

    fs.copySync(source, target, {
        filter: (src) => {
            const relativePath = path.relative(source, src);
            return !excludeFolders.some((folder) => relativePath.includes(folder));
        },
    });
}

// Create an instance of ESLint with the configuration passed to the function
function createESLintInstance(overrideConfig, fix) {
    return new ESLint({
        overrideConfig,
        fix,
    });
}

// Lint the specified files and return the results
async function lintAndFix(eslint, filePaths, contenaVersion) {
    const results = await eslint.lintFiles(filePaths, contenaVersion);

    // Apply automatic fixes and output fixed code
    await ESLint.outputFixes(results);

    return results;
}

// Log results to console if there are any problems
async function outputLintingResults(results, eslint) {
    // 4. Format the results.
    const formatter = await eslint.loadFormatter('stylish');
    const resultText = formatter.format(results);

    // eslint-disable-next-line no-console
    console.log(resultText);
}

// Put previous functions all together
async function lintFiles(filePaths, fix, contenaVersion) {
    // Base plugins/parsers come from eslint.config.mjs; these entries only add the codemod-specific rules.
    const overrideConfig = [
        // JS files
        {
            files: ['**/*.js'],
            ignores: [
                '**/*.spec.js',
                '**/*.spec.vue3.js',
            ],
            rules: {
                'vue/no-deprecated-destroyed-lifecycle': 'error',
                'vue/no-deprecated-events-api': 'error',
                'vue/require-slots-as-functions': 'error',
                'vue/no-deprecated-props-default-this': 'error',
                'ct-deprecation-rules/no-compat-conditions': ['error'],
                'ct-deprecation-rules/no-empty-listeners': ['error'],
                'ct-core-rules/require-explicit-emits': 'error',
                'ct-deprecation-rules/private-feature-declarations': 'off',
                'comma-dangle': 'off',
                'import/extensions': 'off',
                'max-len': 'off',
                'quote-props': 'off',
                semi: 'off',
                'consistent-return': 'off',
            },
        },
        // TS/TSX files: override tsconfig to point at the plugin-specific one
        {
            files: [
                '**/*.ts',
                '**/*.tsx',
            ],
            languageOptions: {
                parserOptions: {
                    tsconfigRootDir: __dirname,
                    project: ['./tsconfig-plugins.json'],
                },
            },
            rules: {
                'ct-deprecation-rules/no-compat-conditions': ['error'],
                'ct-deprecation-rules/no-empty-listeners': ['error'],
                'ct-core-rules/require-explicit-emits': 'error',
                'ct-deprecation-rules/private-feature-declarations': 'off',
                '@typescript-eslint/no-unsafe-assignment': 'off',
                '@typescript-eslint/no-unsafe-member-access': 'off',
                '@typescript-eslint/no-unsafe-call': 'off',
                '@typescript-eslint/no-unsafe-return': 'off',
                '@typescript-eslint/no-unsafe-argument': 'off',
                '@typescript-eslint/no-unused-vars': 'off',
                'no-unused-vars': 'off',
                'import/extensions': 'off',
                '@typescript-eslint/consistent-type-imports': 'off',
                '@typescript-eslint/no-explicit-any': 'off',
                '@typescript-eslint/no-floating-promises': 'off',
                'object-curly-spacing': 'off',
                'rest-spread-spacing': 'off',
                'max-len': 'off',
                'prefer-object-spread': 'off',
                'array-callback-return': 'off',
                semi: 'off',
                eqeqeq: 'off',
                camelcase: 'off',
                indent: 'off',
                'comma-dangle': 'off',
                'no-multi-spaces': 'off',
                'consistent-return': 'off',
                '@typescript-eslint/require-await': 'off',
                '@typescript-eslint/no-redundant-type-constituents': 'off',
                'no-void': 'off',
                'no-tabs': 'off',
                'no-return-assign': 'off',
                '@typescript-eslint/unbound-method': 'off',
                'import/order': 'off',
                radix: 'off',
                'no-restricted-syntax': 'off',
                'import/no-unresolved': 'off',
                'no-return-await': 'off',
                '@typescript-eslint/no-unsafe-enum-comparison': 'off',
                '@typescript-eslint/no-empty-object-type': 'off',
                '@typescript-eslint/await-thenable': 'off',
                quotes: 'off',
                'padded-blocks': 'off',
                '@typescript-eslint/no-duplicate-type-constituents': 'off',
                'no-unsafe-optional-chaining': 'off',
                'no-useless-constructor': 'off',
                'lines-between-class-members': 'off',
                'no-undef': 'off',
                'default-param-last': 'off',
                'no-shadow': 'off',
                'keyword-spacing': 'off',
                'no-extra-semi': 'off',
                '@typescript-eslint/no-shadow': 'off',
                '@typescript-eslint/prefer-ts-expect-error': 'off',
                'quote-props': 'off',
                'comma-spacing': 'off',
                'no-use-before-define': 'off',
                '@typescript-eslint/prefer-promise-reject-errors': 'off',
                'function-paren-newline': 'off',
                'prefer-const': 'off',
                'dot-notation': 'off',
                'no-extra-boolean-cast': 'off',
                'import/prefer-default-export': 'off',
                'import/no-useless-path-segments': 'off',
                '@typescript-eslint/no-unnecessary-type-assertion': 'off',
                'no-unused-expressions': 'off',
                'brace-style': 'off',
                '@typescript-eslint/no-wrapper-object-types': 'off',
                'no-useless-catch': 'off',
                'no-self-assign': 'off',
                'no-unreachable': 'off',
                'func-names': 'off',
                'space-before-function-paren': 'off',
                'no-else-return': 'off',
                'no-continue': 'off',
                'no-plusplus': 'off',
                '@typescript-eslint/no-misused-promises': 'off',
                '@typescript-eslint/only-throw-error': 'off',
                '@typescript-eslint/restrict-template-expressions': 'off',
                '@typescript-eslint/restrict-plus-operands': 'off',
                'no-mixed-operators': 'off',
                'implicit-arrow-linebreak': 'off',
                'key-spacing': 'off',
                '@typescript-eslint/no-unused-expressions': 'off',
                'prefer-template': 'off',
                curly: 'off',
                'default-case': 'off',
                'no-async-promise-executor': 'off',
                'no-nested-ternary': 'off',
                'space-infix-ops': 'off',
                'array-bracket-spacing': 'off',
                'func-call-spacing': 'off',
                'function-call-argument-newline': 'off',
                'no-useless-return': 'off',
                'import/no-duplicates': 'off',
                'space-in-parens': 'off',
                'eol-last': 'off',
                'spaced-comment': 'off',
                'no-unreachable-loop': 'off',
                'no-cond-assign': 'off',
                'no-await-in-loop': 'off',
                'new-cap': 'off',
                'import/first': 'off',
                'switch-colon-spacing': 'off',
                'arrow-spacing': 'off',
                'space-before-blocks': 'off',
                'newline-per-chained-call': 'off',
                'import/newline-after-import': 'off',
                'no-useless-computed-key': 'off',
                'nonblock-statement-body-position': 'off',
                'no-trailing-spaces': 'off',
                '@typescript-eslint/no-this-alias': 'off',
            },
        },
    ];

    const eslint = createESLintInstance(overrideConfig, fix);
    const results = await lintAndFix(eslint, filePaths, contenaVersion);
    return outputLintingResults(results, eslint);
}

function createPluginsTsConfigFile(pluginName) {
    const tsConfig = {
        extends: './tsconfig.json',
        include: [
            `./${pluginName}/**/*`,
        ],
    };

    fs.writeFileSync('./tsconfig-plugins.json', JSON.stringify(tsConfig));
}

function removePluginsTsConfigFile() {
    fs.rmSync('./tsconfig-plugins.json', { force: true });
}
