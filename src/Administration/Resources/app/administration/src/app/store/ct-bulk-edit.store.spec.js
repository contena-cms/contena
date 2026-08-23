describe('src/app/store/ct-bulk-edit.store', () => {
    it('stores and resets selected entity identifiers', () => {
        const store = Contena.Store.get('swBulkEdit');

        store.setSelectedIds([
            'first-id',
            'second-id',
        ]);

        expect(store.selectedIds).toStrictEqual([
            'first-id',
            'second-id',
        ]);

        store.resetSelectedIds();

        expect(store.selectedIds).toStrictEqual([]);
    });
});
