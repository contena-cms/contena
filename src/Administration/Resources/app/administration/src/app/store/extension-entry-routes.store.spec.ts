describe('extension-entry-routes.store', () => {
    let store = Contena.Store.get('extensionEntryRoutes');

    beforeEach(() => {
        store = Contena.Store.get('extensionEntryRoutes');
    });

    afterEach(() => {
        store.routes = {};
    });

    it('has initial state', () => {
        expect(store.routes).toStrictEqual({});
    });

    it('can add item', () => {
        store.addItem({
            extensionName: 'test',
            route: 'test.route',
        });

        expect(store.routes).toStrictEqual({
            test: {
                extensionName: 'test',
                route: 'test.route',
            },
        });
    });
});
