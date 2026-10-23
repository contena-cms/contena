/**
 * @sw-package framework
 */

// eslint-disable-next-line @typescript-eslint/no-require-imports
const resolveContenaModule = require('./resolve-module') as (specifier: string) => unknown;

// `Contena` is declared `const` on the global, which puts it outside the keys `replaceProperty` accepts.
const globalObject = global as unknown as { Contena: unknown };

describe('contena:* Jest module resolution', () => {
    it('reads a named export off the global at access time, not at import', () => {
        // Resolved before the global is replaced, which is the case the getters exist for: a spec swaps
        // `window.Contena` for a partial mock long after the module graph is built.
        const utils = resolveContenaModule('contena:utils') as { createId: () => string };
        const contena = jest.replaceProperty(globalObject, 'Contena', { Utils: { createId: () => 'first' } });

        expect(utils.createId()).toBe('first');

        contena.replaceValue({ Utils: { createId: () => 'second' } });

        expect(utils.createId()).toBe('second');
    });

    it('resolves a store on every call', () => {
        const useStore = resolveContenaModule('contena:stores/notification') as () => unknown;
        const first = { source: 'first' };
        const second = { source: 'second' };
        const contena = jest.replaceProperty(globalObject, 'Contena', { Store: { get: () => first } });

        expect(useStore()).toBe(first);

        contena.replaceValue({ Store: { get: () => second } });

        expect(useStore()).toBe(second);
    });
});
