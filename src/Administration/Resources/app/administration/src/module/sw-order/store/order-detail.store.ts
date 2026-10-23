/**
 * @sw-package checkout
 */
import type { ContextState } from '../../../app/composables/use-context';

const { Criteria } = Contena.Data;

/**
 * Kept outside of the store state on purpose: it only deduplicates concurrent requests and must not be reactive.
 */
let pendingCustomerRequest: {
    userId: EntityKey<'user'>;
    request: Promise<Entity<'user'> | null>;
} | null = null;

interface OrderAddressId {
    orderAddressId: EntityKey<'order_address'>;
    userAddressId: EntityKey<'user_address'>;
    type: string;
    edited: boolean;
}

const swOrderDetailStore = Contena.Store.register({
    id: 'swOrderDetail',

    state() {
        return {
            order: null as Entity<'order'> | null,
            user: null as Entity<'user'> | null,
            loading: {
                order: false, // live version id
                recalculation: false, // custom version id
                states: false,
            },
            editing: false,
            savedSuccessful: false,
            versionContext: null as ContextState['api'] | null,
            orderAddressIds: [] as OrderAddressId[],
        };
    },

    getters: {
        isLoading: (state) => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        isEditing: (state) => {
            return state.editing;
        },
    },

    actions: {
        setLoading(value: [keyof typeof this.loading, boolean]) {
            const name = value[0];
            const data = value[1];

            // check for use from .js files
            if (typeof data !== 'boolean') {
                return;
            }
            this.loading[name] = data;
        },

        /**
         * Loading the user once into the store keeps order detail components (i.e. order address selection
         * components) in sync, so creations and edits in one component are immediately available in all others.
         */
        loadCustomer(userId: EntityKey<'user'>, reload = false): Promise<Entity<'user'> | null> {
            if (!reload && this.user?.id === userId) {
                return Promise.resolve(this.user);
            }

            if (!reload && pendingCustomerRequest?.userId === userId) {
                return pendingCustomerRequest.request;
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('addresses.country');

            const request = Contena.Service('repositoryFactory')
                .create('user')
                .get(userId, Contena.Context.api, criteria)
                .then((user) => {
                    this.user = user;

                    return user;
                })
                .finally(() => {
                    if (pendingCustomerRequest?.request === request) {
                        pendingCustomerRequest = null;
                    }
                });

            pendingCustomerRequest = { userId, request };

            return request;
        },

        resetCustomer() {
            this.user = null;
            pendingCustomerRequest = null;
        },

        setOrderAddressIds(value?: OrderAddressId | null) {
            if (!value) {
                this.orderAddressIds = [];
                return;
            }

            const { orderAddressId, userAddressId, type, edited } = value;

            // Handle deletion scenario where orderAddressId matches userAddressId
            if (String(orderAddressId) === String(userAddressId) && !edited) {
                this.orderAddressIds = this.orderAddressIds.filter(
                    (ids) => !(ids.orderAddressId === orderAddressId && ids.type === type),
                );

                return;
            }

            // Find index of the existing item
            const index = this.orderAddressIds.findIndex(
                (ids) => ids.orderAddressId === orderAddressId && ids.type === type,
            );

            // If found, update the existing item
            if (index !== -1) {
                this.orderAddressIds[index].userAddressId = userAddressId;

                return;
            }

            // Add a new item if no existing item was found
            this.orderAddressIds.push(value);
        },
    },
});

/**
 * @private
 */
export default swOrderDetailStore;

/**
 * @private
 */
export type SwOrderDetailStore = ReturnType<typeof swOrderDetailStore>;
