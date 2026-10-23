/**
 * @sw-package framework
 */

import template from './sw-admin_user-card.html.twig';
import './sw-admin_user-card.scss';

/**
 * @private
 * @description Renders a compact admin_user information card using the provided admin_user data.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-admin_user-card title="Account" :admin_user="{
 *     email: 'test@example.com',
 *     active: true,
 *     addresses: [{
 *         salutation: 'Mister',
 *         title: 'Doctor',
 *         firstName: 'John',
 *         lastName: 'Doe',
 *         street: 'Main St 123',
 *         zipcode: '12456',
 *         city: 'Anytown',
 *         country: { name: 'Germany' }
 *     }],
 *     salutation: 'Mister',
 *     title: 'Doctor',
 *     firstName: 'John',
 *     lastName: 'Doe',
 *     street: 'Main St 123',
 *     zipcode: '12456',
 *     city: 'Anytown',
 *     country: { name: 'Germany' }
 * }">
 * <template #actions>
 *     <sw-button size="small" disabled>
 *         <sw-icon name="regular-pencil-s" small></sw-icon>
 *         Edit admin_user
 *     </sw-button>
 *
 *     <sw-button size="small" disabled>
 *         <sw-icon name="regular-key" small></sw-icon>
 *         Change password
 *      </sw-button>
 * </template>
 * </sw-admin_user-card>
 */
export default {
    template,

    props: {
        admin_user: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },
        title: {
            type: String,
            required: true,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions;
        },
        hasAdditionalDataSlot() {
            return !!this.$slots['data-additional'];
        },
        hasSummarySlot() {
            return !!this.$slots.summary;
        },

        moduleColor() {
            if (!this.$route.meta.$module) {
                return '';
            }
            return this.$route.meta.$module.color;
        },

        salutationFilter() {
            return Contena.Filter.getByName('salutation');
        },
    },
};
