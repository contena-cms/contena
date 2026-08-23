const adminHelpCenterStore = Contena.Store.register({
    id: 'adminHelpCenter',

    state: () => {
        return {
            showHelpSidebar: false,
        };
    },
});

/**
 * @private
 */
export type AdminHelpCenterStore = ReturnType<typeof adminHelpCenterStore>;

/**
 * @private
 */
export default adminHelpCenterStore;
