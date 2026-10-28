/**
 * @sw-package framework
 *
 * Guards the place the `contena:*` contract is written down twice: the code strings the Vite plugin
 * emits, and the functions the Jest shims call.
 */

import path from 'node:path';
import vm from 'node:vm';
import {
    MODULE_FAMILIES,
    allSpecifiers,
    branchGuard,
    defaultExpression,
    exportNames,
    memberExpression,
    parseSpecifier,
    resolveVirtualExport,
    type VirtualModuleGlobal,
} from './definitions';
import { readRegistry } from './index';

const administrationRoot = path.resolve(__dirname, '../../..');
const registry = readRegistry(administrationRoot);

/**
 * A global object shaped like the registry says the real one is, with a marker string at every leaf.
 *
 * Built from the registry rather than a blanket proxy, because a namespace subpath reads two levels deep
 * and both levels have to behave like the real objects for the comparison to mean anything.
 *
 * A marker string stands where the real branch has a function or a class, so the two branch types are
 * asserted rather than satisfied.
 */
function createProbeGlobal(): VirtualModuleGlobal {
    const branchOf = (family: string, property: string): Record<string, unknown> =>
        Object.fromEntries(
            Object.entries(registry[family].subpaths).map(
                ([
                    key,
                    members,
                ]) => [
                    key,
                    members.length > 0
                        ? Object.fromEntries(
                              members.map((member) => [
                                  member,
                                  `${property}:${key}:${member}`,
                              ]),
                          )
                        : `${property}:${key}`,
                ],
            ),
        );

    return {
        Utils: branchOf('contena:utils', 'Utils') as unknown as VirtualModuleGlobal['Utils'],
        Data: branchOf('contena:data', 'Data') as unknown as VirtualModuleGlobal['Data'],
        Composables: branchOf('contena:composables', 'Composables') as unknown as VirtualModuleGlobal['Composables'],
        Mixin: { getByName: (key) => `Mixin:${key}` },
        Store: { get: (id) => `Store:${id}` },
    };
}

/** Evaluates a generated initialiser the way the generated module would. */
function evaluate(expression: string, contena: VirtualModuleGlobal): unknown {
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const initialiser = new Function('contena', `return (${expression});`) as (global: VirtualModuleGlobal) => unknown;

    return initialiser(contena);
}

/** Calls a lazily resolved export, so eager and lazy modules compare the same way. */
function unwrap(value: unknown): unknown {
    return typeof value === 'function' ? (value as () => unknown)() : value;
}

describe('build/vite-plugins/virtual-contena-modules/definitions', () => {
    it('has a registry entry for every module family', () => {
        expect(Object.keys(registry).sort()).toEqual([...MODULE_FAMILIES].sort());
    });

    describe('parseSpecifier', () => {
        it('splits a subpath import into its family and subpath', () => {
            expect(parseSpecifier('contena:utils/debug')).toEqual({ family: 'contena:utils', subpath: 'debug' });
            expect(parseSpecifier('contena:mixins/sw-form-field')).toEqual({
                family: 'contena:mixins',
                subpath: 'sw-form-field',
            });
        });

        it('splits a bare import of any known family', () => {
            expect(parseSpecifier('contena:utils')).toEqual({ family: 'contena:utils' });
            expect(parseSpecifier('contena:data')).toEqual({ family: 'contena:data' });
            expect(parseSpecifier('contena:composables')).toEqual({ family: 'contena:composables' });
            expect(parseSpecifier('contena:mixins')).toEqual({ family: 'contena:mixins' });
            expect(parseSpecifier('contena:stores')).toEqual({ family: 'contena:stores' });
        });

        it('leaves every other import alone', () => {
            expect(parseSpecifier('contena:nope')).toBeUndefined();
            expect(parseSpecifier('contena:utils/')).toBeUndefined();
            expect(parseSpecifier('constructor/member')).toBeUndefined();
            expect(parseSpecifier('toString')).toBeUndefined();
            expect(parseSpecifier('src/core/service/util.service')).toBeUndefined();
            expect(parseSpecifier('vue')).toBeUndefined();
        });
    });

    describe('the registry decides which root imports resolve', () => {
        it.each([
            'contena:utils',
            'contena:data',
            'contena:composables',
        ])('serves %s, because it publishes root exports', (family) => {
            expect(registry[family].exports.length).toBeGreaterThan(0);
            expect(exportNames(registry, parseSpecifier(family)!)).toEqual(registry[family].exports);
        });

        it.each([
            'contena:mixins',
            'contena:stores',
        ])('refuses %s, because it publishes none', (family) => {
            expect(registry[family].exports).toEqual([]);
            expect(exportNames(registry, parseSpecifier(family)!)).toBeUndefined();
        });

        it('separates a root import that does not resolve from a subpath that is default-only', () => {
            expect(exportNames(registry, parseSpecifier('contena:mixins')!)).toBeUndefined();
            expect(exportNames(registry, parseSpecifier('contena:mixins/sw-form-field')!)).toEqual([]);
        });

        it.each([
            'constructor',
            'toString',
            '__proto__',
        ])('refuses the inherited subpath %s', (subpath) => {
            expect(exportNames(registry, parseSpecifier(`contena:utils/${subpath}`)!)).toBeUndefined();
        });
    });

    describe('the emitted code and the runtime resolver agree', () => {
        it.each(allSpecifiers(registry))('%s', (specifier) => {
            const parsed = parseSpecifier(specifier);
            const probe = createProbeGlobal();

            expect(parsed).toBeDefined();

            const members = exportNames(registry, parsed!) ?? [];
            const emitted = [
                ...members.map((member) => unwrap(evaluate(memberExpression(parsed!, member), probe))),
                unwrap(evaluate(defaultExpression(parsed!) as string, probe)),
            ];
            const resolved = [
                ...members.map((member) => unwrap(resolveVirtualExport(registry, specifier, member, probe))),
                unwrap(resolveVirtualExport(registry, specifier, 'default', probe)),
            ];

            expect(emitted).toEqual(resolved);
        });
    });

    describe('the registry matches the global object', () => {
        it.each([
            [
                'contena:utils',
                () => Contena.Utils,
            ],
            [
                'contena:data',
                () => Contena.Data,
            ],
            [
                'contena:composables',
                () => Contena.Composables,
            ],
        ])('%s publishes exactly the keys of its branch', (family, branch) => {
            expect(registry[family].exports.sort()).toEqual(Object.keys(branch()).sort());
            expect(Object.keys(registry[family].subpaths).sort()).toEqual(Object.keys(branch()).sort());
        });

        it('promises no named export a utility namespace does not have', () => {
            const utils = Contena.Utils as unknown as Record<string, unknown>;

            Object.entries(registry['contena:utils'].subpaths).forEach(
                ([
                    key,
                    members,
                ]) => {
                    const value = utils[key] as Record<string, unknown>;
                    const missing = members.filter((member) => !(member in value));

                    expect(missing, `contena:utils/${key} promises members it does not have`).toEqual([]);
                },
            );
        });

        it('publishes no named exports for a mixin or a store, which must not be destructured', () => {
            const registryEntries = [
                ...Object.values(registry['contena:mixins'].subpaths),
                ...Object.values(registry['contena:stores'].subpaths),
            ];

            expect(registryEntries.every((members) => members.length === 0)).toBe(true);
        });
    });

    describe('branchGuard', () => {
        const runGuard = (family: string, contena: Record<string, unknown>): void => {
            vm.runInNewContext(branchGuard({ family }).join('\n'), { contena });
        };

        it('names the required and the installed version when an older Administration lacks the branch', () => {
            const contena = { Utils: {}, Context: { app: { config: { version: '6.7.15.0' } } } };

            expect(() => runGuard('contena:composables', contena)).toThrow(
                '"contena:composables" requires Contena 6.7.16.0 or later, but the installed version is 6.7.15.0. ' +
                    'Require contena/administration >=6.7.16.0 in the composer.json of the extension.',
            );
        });

        it('passes when the Administration has the branch', () => {
            expect(() => runGuard('contena:composables', { Composables: {} })).not.toThrow();
        });

        it.each([
            'contena:utils',
            'contena:data',
            'contena:mixins',
            'contena:stores',
        ])('adds nothing for %s, which every Administration with contena:* modules has', (family) => {
            expect(branchGuard({ family })).toEqual([]);
        });
    });

    describe('resolveVirtualExport', () => {
        it('rejects a specifier it does not serve', () => {
            expect(() => resolveVirtualExport(registry, 'contena:mixins', 'anything', createProbeGlobal())).toThrow(
                '"contena:mixins" is not a Contena virtual module.',
            );
        });

        it('names the module when a root import has no such export', () => {
            expect(() => resolveVirtualExport(registry, 'contena:utils', 'notAUtil', Contena as never)).toThrow(
                '"notAUtil" does not exist on Contena.Utils.',
            );
        });

        it('names the module when a subpath has no such export', () => {
            expect(() => resolveVirtualExport(registry, 'contena:utils/debug', 'notAMember', Contena as never)).toThrow(
                '"contena:utils/debug" has no export "notAMember".',
            );
        });
    });
});
