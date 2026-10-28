/**
 * @sw-package framework
 */
describe('contena-apps.store', () => {
    const store = Contena.Store.get('contenaApps');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.apps).toStrictEqual([]);
        expect(store.selectedIds).toStrictEqual([]);
    });
});
