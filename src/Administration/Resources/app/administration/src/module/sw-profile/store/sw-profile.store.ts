/**
 * @sw-package fundamentals@framework
 */
const swProfileStore = Contena.Store.register('swProfile', {
    state() {
        return {
            minSearchTermLength: 2,
            searchPreferences: [],
            admin_userSearchPreferences: null,
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
export default swProfileStore;

/**
 * @private
 */
export type SwProfileStore = ReturnType<typeof swProfileStore>;
