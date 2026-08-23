import createAppMixin from 'src/app/init/mixin.init';

describe('src/app/init/mixin.init.js', () => {
    it('should register all app mixins', () => {
        createAppMixin();

        expect(Contena.Mixin.getByName('notification')).toBeDefined();
        expect(Contena.Mixin.getByName('placeholder')).toBeDefined();
        expect(Contena.Mixin.getByName('ct-inline-snippet')).toBeDefined();
        expect(Contena.Mixin.getByName('translate-with-fallback')).toBeDefined();
    });
});
