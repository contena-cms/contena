/**
 * @sw-package framework
 */
import createAppMixin from 'src/app/init/mixin.init';

describe('src/app/init/mixin.init.js', () => {
    it('should register all app mixins', () => {
        createAppMixin();

        expect(Contena.Mixin.getByName('sw-form-field')).toBeDefined();
        expect(Contena.Mixin.getByName('generic-condition')).toBeDefined();
        expect(Contena.Mixin.getByName('listing')).toBeDefined();
        expect(Contena.Mixin.getByName('notification')).toBeDefined();
        expect(Contena.Mixin.getByName('placeholder')).toBeDefined();
        expect(Contena.Mixin.getByName('position')).toBeDefined();
        expect(Contena.Mixin.getByName('remove-api-error')).toBeDefined();
        expect(Contena.Mixin.getByName('ruleContainer')).toBeDefined();
        expect(Contena.Mixin.getByName('salutation')).toBeDefined();
        expect(Contena.Mixin.getByName('sw-inline-snippet')).toBeDefined();
        expect(Contena.Mixin.getByName('translate-with-fallback')).toBeDefined();
        expect(Contena.Mixin.getByName('user-settings')).toBeDefined();
        expect(Contena.Mixin.getByName('validation')).toBeDefined();
    });
});
