import template from './sw-sales-channel-detail-theme.html.twig';
import './sw-sales-channel-detail-theme.scss';

const { Mixin } = Contena;
const Criteria = Contena.Data.Criteria;

const PENDING_THEME_CONFIG_DOMAIN = 'frontend';
const PENDING_THEME_CONFIG_KEY = 'frontend.pendingThemeAssignment';
const PENDING_CHECK_INTERVAL = 10000;

/**
 * @deprecated tag:v6.8.0 - Will be @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    inject: [
        'repositoryFactory',
        'themeService',
        'systemConfigApiService',
        'acl',
    ],

    props: {
        channel: {
            required: true,
        },
    },

    data() {
        return {
            theme: null,
            pendingTheme: null,
            pendingCheckTimeoutId: null,
            activeCheckId: 0,
            showThemeSelectionModal: false,
            showChangeModal: false,
            newThemeId: null,
            isLoading: false,
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        pendingTooltip() {
            if (!this.pendingTheme) {
                return '';
            }

            return this.theme
                ? this.$t('sales-channel-theme.pendingAssignment.description', {
                      liveThemeName: this.theme.name,
                      pendingThemeName: this.pendingTheme.name,
                  })
                : this.$t('sales-channel-theme.pendingAssignment.descriptionNoLiveTheme', {
                      pendingThemeName: this.pendingTheme.name,
                  });
        },
    },

    watch: {
        'channel.extensions.themes': {
            deep: true,
            handler() {
                this.getTheme(this.channel?.extensions?.themes[0]?.id);
            },
        },
        channel() {
            this.checkPendingAssignment();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    methods: {
        createdComponent() {
            if (this.channel?.extensions?.themes[0]) {
                this.theme = this.channel.extensions.themes[0];
                this.getTheme(this.theme.id);
            }

            this.checkPendingAssignment();
        },

        beforeUnmountComponent() {
            // invalidate any in-flight check so a late-resolving read cannot re-schedule polling
            this.activeCheckId += 1;
            this.clearPendingCheck();
        },

        async getTheme(themeId) {
            if (!themeId) {
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('previewMedia');

            this.theme = await this.themeRepository.get(themeId, Contena.Context.api, criteria);
        },

        async checkPendingAssignment() {
            if (!this.channel?.id) {
                return;
            }

            // Tag this run so a result resolving after unmount or a sales-channel change is discarded.
            this.activeCheckId += 1;
            const checkId = this.activeCheckId;

            try {
                const [pendingThemeId, liveThemeId] = await Promise.all([this.loadPendingThemeId(), this.loadLiveThemeId()]);

                if (checkId !== this.activeCheckId) {
                    return;
                }

                if (pendingThemeId && pendingThemeId !== liveThemeId) {
                    if (this.pendingTheme?.id !== pendingThemeId) {
                        this.pendingTheme = await this.themeRepository.get(pendingThemeId, Contena.Context.api);

                        if (checkId !== this.activeCheckId) {
                            return;
                        }
                    }

                    this.schedulePendingCheck();

                    return;
                }

                this.clearPendingCheck();
                this.pendingTheme = null;

                if (liveThemeId && liveThemeId !== this.theme?.id) {
                    await this.getTheme(liveThemeId);
                }
            } catch {
                if (checkId !== this.activeCheckId) {
                    return;
                }

                // best-effort indicator: on any read error stop polling and hide the spinner
                this.clearPendingCheck();
                this.pendingTheme = null;
            }
        },

        async loadPendingThemeId() {
            // Query the parent domain: getValues() matches "<domain>.%", so the full key would
            // return nothing. Read the exact key out of the returned "frontend.*" map.
            const values = await this.systemConfigApiService.getValues(PENDING_THEME_CONFIG_DOMAIN, this.channel.id);

            return values?.[PENDING_THEME_CONFIG_KEY] ?? null;
        },

        async loadLiveThemeId() {
            const criteria = new Criteria();
            criteria.addAssociation('themes');

            const channel = await this.channelRepository.get(this.channel.id, Contena.Context.api, criteria);

            return channel?.extensions?.themes?.[0]?.id ?? null;
        },

        schedulePendingCheck() {
            this.clearPendingCheck();
            this.pendingCheckTimeoutId = setTimeout(() => this.checkPendingAssignment(), PENDING_CHECK_INTERVAL);
        },

        clearPendingCheck() {
            if (this.pendingCheckTimeoutId) {
                clearTimeout(this.pendingCheckTimeoutId);
                this.pendingCheckTimeoutId = null;
            }
        },

        openThemeModal() {
            if (!this.acl.can('channel.editor')) {
                return;
            }

            this.showThemeSelectionModal = true;
        },

        closeThemeModal() {
            this.showThemeSelectionModal = false;
        },

        openInThemeManager() {
            if (!this.theme) {
                this.$router.push({ name: 'sw.theme.manager.index' });
            } else {
                this.$router.push({ name: 'sw.theme.manager.detail', params: { id: this.theme.id } });
            }
        },

        async onChangeTheme(themeId) {
            this.showThemeSelectionModal = false;

            await this.getTheme(themeId);
            this.channel.extensions.themes[0] = this.theme;
        },
    },
};
