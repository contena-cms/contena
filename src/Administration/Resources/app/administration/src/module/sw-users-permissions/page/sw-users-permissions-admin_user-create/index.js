/**
 * @sw-package fundamentals@framework
 */
import template from './sw-users-permissions-admin_user-create.html.twig';

const { Component } = Contena;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        ...mapPropertyErrors('admin_user', ['password']),
    },

    methods: {
        loadUser() {
            if (this.admin_user) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                this.admin_user = this.admin_userRepository.create(this.context);
                this.admin_user.active = true;
                resolve();
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.users.permissions.admin_user.detail',
                params: { id: this.admin_user.id },
            });
        },

        onSave() {
            if (!this.admin_user.localeId) {
                this.admin_user.localeId = this.currentUser.localeId;
            }
            this.$super('onSave');
        },
    },
};
