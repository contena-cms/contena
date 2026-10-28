/**
 * @sw-package framework
 *
 * Jest counterpart of the Vite-generated `contena:*` modules. Named getters follow tests that replace
 * `window.Contena`, and stores resolve per call. Default-only values retain their original identity.
 * The CommonJS object has no `__esModule` marker so compiled named imports read its properties.
 */

const path = require('path');
const definitions = require('../../../build/vite-plugins/virtual-contena-modules/definitions');
const { readRegistry } = require('../../../build/vite-plugins/virtual-contena-modules/index');

const { parseSpecifier, exportNames, resolveVirtualExport } = definitions;

const registry = readRegistry(path.resolve(__dirname, '../../..'));

/** Reads one member, or `undefined` when a partial Contena mock does not provide it. */
function read(specifier, member) {
    try {
        return resolveVirtualExport(registry, specifier, member, global.Contena);
    } catch {
        return undefined;
    }
}

/** The value one `contena:*` specifier publishes, read off the global object the test env set up. */
module.exports = function resolveContenaModule(specifier) {
    const parsed = parseSpecifier(specifier);
    const members = parsed ? exportNames(registry, parsed) : undefined;

    if (!parsed || !members) {
        throw new Error(`"${specifier}" is not a Contena virtual module.`);
    }

    if (members.length > 0) {
        const stub = {};

        members.forEach((member) =>
            Object.defineProperty(stub, member, {
                get: () => read(specifier, member),
                // Mixed default and named imports copy enumerable CommonJS properties.
                enumerable: true,
            }),
        );

        return stub;
    }

    if (parsed.family === 'contena:stores') {
        return (...args) => resolveVirtualExport(registry, specifier, 'default', global.Contena)(...args);
    }

    return resolveVirtualExport(registry, specifier, 'default', global.Contena);
};
