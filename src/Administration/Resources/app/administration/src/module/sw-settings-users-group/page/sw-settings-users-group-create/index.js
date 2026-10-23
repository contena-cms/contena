/**
 * @sw-package discovery
 *
 * @private
 */
export default {
    methods: {
        createdComponent() {
            this.isLoading = true;
            Contena.Store.get('context').resetLanguageToDefault();
            this.userGroup = this.userGroupRepository.create();
            this.isLoading = false;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (!this.validateSaveRequest()) {
                return;
            }

            try {
                await this.userGroupRepository.save(this.userGroup);

                this.isSaveSuccessful = true;

                this.$router.push({
                    name: 'sw.settings.user.group.detail',
                    params: { id: this.userGroup.id },
                });
            } catch (_err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$t('sw-settings-user-group.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
