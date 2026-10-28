/**
 * @sw-package framework
 *
 * Renders the ambient declarations for every `contena:*` specifier.
 *
 * Each module declares the default and named exports recorded in the registry:
 *
 *     import debug, { warn } from 'contena:utils/debug';
 *
 * Types come from the same global branches and registry interfaces as the runtime values.
 */

import type { ModuleRegistry } from '../../build/vite-plugins/virtual-contena-modules/definitions';

const UTILS_MODULE = 'src/core/service/util.service';
const DATA_MODULE = 'src/core/data/index';
const COMPOSABLES_MODULE = 'src/app/composables/index';

/** @private The command shown in generated files and drift diagnostics. */
export const REGENERATE_COMMAND = 'composer admin:generate-contena-modules';

/**
 * The stability marker every specifier carries.
 *
 * Per block rather than once per file: an editor shows the doc comment nearest the declaration being
 * hovered, so a file-level one would reach the first module and silently leave the rest looking stable.
 */
const EXPERIMENTAL = '/** @experimental stableVersion:v6.8.0 */';

/** The composables keep the marker of their own sources, which stabilise a major later. */
const COMPOSABLES_EXPERIMENTAL = '/** @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES */';

const FILE_HEADER = `/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0
 *
 * Types for the \`contena:*\` modules, which expose the global \`Contena\` object as ordinary
 * imports. \`build/vite-plugins/virtual-contena-modules\` generates their runtime counterpart from
 * the same \`contena-modules.json\`.
 *
 * Generated. Run \`${REGENERATE_COMMAND}\` after adding a utility, DAL class, composable, mixin, or store.
 */`;

function block(specifier: string, body: string[], stability = EXPERIMENTAL): string {
    return [
        stability,
        `declare module '${specifier}' {`,
        // A blank line keeps its emptiness: an indented one would fail the formatting check.
        ...body.map((line) => (line === '' ? '' : `    ${line}`)),
        '}',
        '',
    ].join('\n');
}

function namedExports(value: string, names: string[]): string[] {
    return names.map((name) => `export const ${name}: (typeof ${value})['${name}'];`);
}

/** A subpath of a branch that is itself an object, e.g. `contena:utils/debug`. */
function branchSubpath(specifier: string, branchModule: string, key: string, exports: string[], stability?: string): string {
    const body = [
        `import type branch from '${branchModule}';`,
        '',
        `const member: (typeof branch)['${key}'];`,
        '',
        'export default member;',
        ...namedExports('member', exports),
    ];

    return block(specifier, body, stability);
}

/** The root import of a branch, e.g. `contena:utils`, which publishes the whole branch and its members. */
function branchRoot(specifier: string, branchModule: string, exports: string[], stability?: string): string {
    const body = [
        `import type branch from '${branchModule}';`,
        '',
        'const members: typeof branch;',
        '',
        'export default members;',
        ...namedExports('members', exports),
    ];

    return block(specifier, body, stability);
}

function defaultOnlyModule(specifier: string, value: string, type: string): string {
    return block(specifier, [
        `const ${value}: ${type};`,
        '',
        `export default ${value};`,
    ]);
}

/** @private Renders all specifiers in registry order for deterministic diffs. */
export function renderDeclarations(registry: ModuleRegistry): string {
    const blocks: string[] = [];

    blocks.push(branchRoot('contena:utils', UTILS_MODULE, registry['contena:utils'].exports));
    Object.entries(registry['contena:utils'].subpaths).forEach(
        ([
            key,
            exports,
        ]) => blocks.push(branchSubpath(`contena:utils/${key}`, UTILS_MODULE, key, exports)),
    );

    blocks.push(branchRoot('contena:data', DATA_MODULE, registry['contena:data'].exports));
    Object.entries(registry['contena:data'].subpaths).forEach(
        ([
            key,
            exports,
        ]) => blocks.push(branchSubpath(`contena:data/${key}`, DATA_MODULE, key, exports)),
    );

    blocks.push(
        branchRoot(
            'contena:composables',
            COMPOSABLES_MODULE,
            registry['contena:composables'].exports,
            COMPOSABLES_EXPERIMENTAL,
        ),
    );
    Object.entries(registry['contena:composables'].subpaths).forEach(
        ([
            key,
            exports,
        ]) =>
            blocks.push(
                branchSubpath(`contena:composables/${key}`, COMPOSABLES_MODULE, key, exports, COMPOSABLES_EXPERIMENTAL),
            ),
    );

    Object.keys(registry['contena:mixins'].subpaths).forEach((key) =>
        blocks.push(defaultOnlyModule(`contena:mixins/${key}`, 'mixin', `MixinContainer['${key}']`)),
    );

    Object.keys(registry['contena:stores'].subpaths).forEach((key) =>
        blocks.push(defaultOnlyModule(`contena:stores/${key}`, 'useStore', `() => PiniaRootState['${key}']`)),
    );

    return [
        FILE_HEADER,
        '',
        '/* eslint-disable sw-deprecation-rules/private-feature-declarations -- Intentional public facade. */',
        '',
        ...blocks,
    ].join('\n');
}
