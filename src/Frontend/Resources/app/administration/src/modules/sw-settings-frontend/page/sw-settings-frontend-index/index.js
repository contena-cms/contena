import template from './sw-settings-frontend-index.html.twig';
import './sw-settings-frontend-index.scss';

/**
 * @deprecated tag:v6.8.0 - Will be @private
 * @sw-package framework
 */
export default {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            selectedSalesChannelId: null,
            frontendSettings: {
                'core.frontendSettings.iconCache': true,
                'core.frontendSettings.asyncThemeCompilation': false,
                'core.frontendSettings.speculationRules': false,
            },
            channelFrontendSettings: {
                'core.frontendSettings.iconCache': null,
                'core.frontendSettings.speculationRules': null,
            },
        };
    },

    computed: {
        isGlobalConfig() {
            return this.selectedSalesChannelId === null;
        },

        currentSalesChannelFrontendSettings() {
            return this.isGlobalConfig ? this.frontendSettings : this.channelFrontendSettings;
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.loadPageContent();
        },

        async loadPageContent() {
            await this.loadfrontendSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        async loadfrontendSettings() {
            this.isLoading = true;

            try {
                const values = await this.systemConfigApiService.getValues('core.frontendSettings');

                this.frontendSettings = {
                    'core.frontendSettings.iconCache': true,
                    'core.frontendSettings.asyncThemeCompilation': false,
                    'core.frontendSettings.speculationRules': false,
                    ...values,
                };

                if (!this.isGlobalConfig) {
                    await this.loadSalesChannelFrontendSettings();
                }
            } finally {
                this.isLoading = false;
            }
        },

        async loadSalesChannelFrontendSettings() {
            const values = await this.systemConfigApiService.getValues(
                'core.frontendSettings',
                this.selectedSalesChannelId,
            );

            this.channelFrontendSettings = {
                'core.frontendSettings.iconCache': values['core.frontendSettings.iconCache'] ?? null,
                'core.frontendSettings.speculationRules': values['core.frontendSettings.speculationRules'] ?? null,
            };
        },

        async saveFrontendSettings() {
            await this.savefrontendSettings();
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed.
         */
        async savefrontendSettings() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            if (this.frontendSettings['core.frontendSettings.asyncThemeCompilation'] === '') {
                this.frontendSettings['core.frontendSettings.asyncThemeCompilation'] = false;
            }

            if (this.currentSalesChannelFrontendSettings['core.frontendSettings.iconCache'] === '') {
                this.currentSalesChannelFrontendSettings['core.frontendSettings.iconCache'] = this.isGlobalConfig
                    ? true
                    : null;
            }

            if (this.currentSalesChannelFrontendSettings['core.frontendSettings.speculationRules'] === '') {
                this.currentSalesChannelFrontendSettings['core.frontendSettings.speculationRules'] = this.isGlobalConfig
                    ? false
                    : null;
            }

            try {
                await Promise.all([
                    this.systemConfigApiService.saveValues({
                        'core.frontendSettings.asyncThemeCompilation':
                            this.frontendSettings['core.frontendSettings.asyncThemeCompilation'],
                    }),
                    this.systemConfigApiService.saveValues(
                        {
                            'core.frontendSettings.iconCache':
                                this.currentSalesChannelFrontendSettings['core.frontendSettings.iconCache'],
                            'core.frontendSettings.speculationRules':
                                this.currentSalesChannelFrontendSettings['core.frontendSettings.speculationRules'],
                        },
                        this.selectedSalesChannelId,
                    ),
                ]);

                this.isSaveSuccessful = true;
            } finally {
                this.isLoading = false;
            }
        },

        async onSalesChannelChanged(channelId) {
            this.selectedSalesChannelId = channelId || null;

            if (this.isGlobalConfig) {
                return;
            }

            this.isLoading = true;

            try {
                await this.loadSalesChannelFrontendSettings();
            } finally {
                this.isLoading = false;
            }
        },
    },
};
