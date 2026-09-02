const ctProfileStore = Contena.Store.register('ctProfile', {
    state() {
        return {
            minSearchTermLength: 2,
            searchPreferences: [],
            userSearchPreferences: null,
        };
    },

    actions: {
        setMinSearchTermLength(minSearchTermLength: number) {
            this.minSearchTermLength = minSearchTermLength;
        },
    },
});

/**
 * @private
 */
export default ctProfileStore;

/**
 * @private
 */
export type CtProfileStore = ReturnType<typeof ctProfileStore>;
