/* eslint-disable ct-deprecation-rules/private-feature-declarations */
export type ActionButtonConfig = Record<string, unknown>;

const actionButtonsStore = Contena.Store.register({
    id: 'actionButtons',
    state: () => ({ buttons: [] as ActionButtonConfig[] }),
    actions: {
        add(button: ActionButtonConfig): void {
            this.buttons.push(button);
        },
    },
});

export type ActionButtonsStore = ReturnType<typeof actionButtonsStore>;
export default actionButtonsStore;
