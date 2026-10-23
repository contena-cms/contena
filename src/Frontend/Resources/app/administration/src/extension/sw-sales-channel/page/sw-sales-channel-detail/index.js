import template from './sw-sales-channel-detail.html.twig';

const { Component } = Contena;

/**
 * @sw-package discovery
 */
Component.override('sw-sales-channel-detail', {
    template,

    inject: ['themeService'],

    methods: {
        getLoadSalesChannelCriteria() {
            const criteria = this.$super('getLoadSalesChannelCriteria');

            criteria.addAssociation('themes');

            return criteria;
        },

        async onSave() {
            this.isLoading = true;
            await this.assignSalesChannelTheme();
            await this.$super('onSave');
        },

        async assignSalesChannelTheme() {
            const originThemeId = this.channel.getOrigin().extensions?.themes?.[0]?.id;
            const newThemeId = this.channel.extensions?.themes?.[0]?.id;

            if (originThemeId === newThemeId) {
                return;
            }

            try {
                await this.themeService.assignTheme(newThemeId, this.channel.id);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-theme-manager.general.messageSaveError'),
                });
            } finally {
                const themes = this.channel.extensions?.themes;

                themes?.splice(0, themes.length, ...(this.channel.getOrigin().extensions?.themes ?? []));
            }
        },
    },
});
