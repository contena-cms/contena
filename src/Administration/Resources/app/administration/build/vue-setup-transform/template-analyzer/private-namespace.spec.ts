import { transformContenaSetupSfc } from '../index.ts';

/**
 * Covers the override-private namespace: a module-root `Symbol()` used as a computed key.
 *
 * The symbol replaces an earlier filename+sha1 string key. What matters is not the binding's name -
 * which is fixed - but that it is declared once at module scope and referenced as a computed key in both
 * halves of the file, so each override files its locals under the shared `__swOverride` channel without
 * needing a globally unique name.
 */
describe('build/vue-setup-transform/template-analyzer private namespace', () => {
    const source = `<template>
    <ct-block extends="body">{{ info }}</ct-block>
</template>
<script setup lang="ts">
const info = 'local';
swDefineOverride({});
</script>`;

    it('declares the namespace symbol at module root, outside the override callback', () => {
        const result = transformContenaSetupSfc(source, 'ct-thing.override.vue')?.code ?? '';

        expect(result).toContain("const __swSetupNamespace = Symbol('ct-thing.override');");

        // Must sit before the callback: the callback runs once per base-component instance, so a symbol
        // created inside it would differ every time and no state lookup would ever match.
        expect(result.indexOf('const __swSetupNamespace')).toBeLessThan(
            result.indexOf('Contena.Component.overrideComponentSetup()'),
        );
    });

    it('uses the symbol as a computed key in both the slot scope and the returned state', () => {
        const result = transformContenaSetupSfc(source, 'ct-thing.override.vue')?.code ?? '';

        expect(result).toContain('#default="{ __swOverride: { [__swSetupNamespace]: { info } } }"');
        expect(result).toContain('[__swSetupNamespace]: {');
    });

    it('carries no filename or hash into the generated key', () => {
        const result = transformContenaSetupSfc(source, 'src/deep/path/ct-thing.override.vue')?.code ?? '';

        expect(result).not.toContain('sw_thing_override');
        expect(result).not.toMatch(/__swOverride: \{ [A-Za-z_$][A-Za-z0-9_$]*_[a-f0-9]{5}:/u);
    });

    it('generates identical text for two overrides of the same component', () => {
        const first = transformContenaSetupSfc(source, 'a/ct-thing.override.vue')?.code ?? '';
        const second = transformContenaSetupSfc(source, 'b/ct-thing.override.vue')?.code ?? '';

        // Uniqueness is provided at runtime by Symbol(), not by the generated name: each override is its
        // own module and evaluates its own declaration, so two identical texts still yield two symbols.
        expect(first).toEqual(second);
    });
});
