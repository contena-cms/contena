import template from './sw-admin_user-sso-status-label.html.twig';

/**
 * @private
 * @sw-package framework
 */
export default {
    name: 'sw-admin_user-sso-status-label',
    template,

    props: {
        admin_user: {
            type: Object,
            required: true,
        },
    },

    computed: {
        status() {
            if (this.admin_user.active) {
                return 'active';
            }

            if (!this.admin_user.active && this.admin_user.email === this.admin_user.firstName && this.admin_user.email === this.admin_user.lastName) {
                return 'invited';
            }

            return 'inactive';
        },

        statusText() {
            return this.$t(`sw-users-permissions.sso.admin_user-listing.status-label.${this.status}`);
        },

        variant() {
            switch (this.status) {
                case 'active':
                    return 'positive';
                case 'invited':
                    return 'attention';
                default:
                    return 'critical';
            }
        },
    },
};
