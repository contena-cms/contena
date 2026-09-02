const ctBulkStore = Contena.Store.register('ctBulkEdit', {
    state: () => ({
        selectedIds: [] as string[],
    }),

    actions: {
        setSelectedIds(selectedIds: string[]) {
            this.selectedIds = selectedIds;
        },

        resetSelectedIds() {
            this.selectedIds = [];
        },
    },
});

/**
 * @private
 */
export default ctBulkStore;

/**
 * @private
 */
export type CtBulkStore = ReturnType<typeof ctBulkStore>;
