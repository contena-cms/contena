describe('directives/click-outside', () => {
    it('should register the directive', () => {
        expect(Contena.Directive.getByName('click-outside')).toBeDefined();
    });
});
