const swBulkStore = Contena.Store.register('swBulkEdit', {
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
export default swBulkStore;

/**
 * @private
 */
export type SwBulkStore = ReturnType<typeof swBulkStore>;
