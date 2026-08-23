import type { Extension } from '../service/extension-store-action.service';

/**
 * @private
 */
export interface ContenaExtensionsState {
    myExtensions: {
        loading: boolean;
        data: Extension[];
    };
}

const contenaExtensionsStore = Contena.Store.register({
    id: 'contenaExtensions',

    state: () =>
        ({
            myExtensions: {
                loading: true,
                data: [],
            },
        }) as ContenaExtensionsState,

    actions: {
        loadMyExtensions() {
            this.myExtensions.loading = true;
        },

        setLoading(value: boolean = true) {
            this.myExtensions.loading = value;
        },

        setMyExtensions(myExtensions: Extension[]) {
            this.myExtensions.data = myExtensions;
            this.myExtensions.loading = false;
        },
    },
});

/**
 * @private
 */
export type ContenaExtensionsStore = ReturnType<typeof contenaExtensionsStore>;

/**
 * @private
 */
export default contenaExtensionsStore;
