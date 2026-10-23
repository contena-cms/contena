import template from './sw-desktop.html.twig';
import useTheme, { THEMES, THEME_LABELS } from 'src/app/composables/use-theme';
import './sw-desktop.scss';

const { hasOwnProperty } = Contena.Utils.object;

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    inject: ['installationIdChangeService', 'admin_userActivityApiService', 'snackbarService'],

    shortcuts: {
        CT: 'onCycleTheme',
    },

    data() {
        return {
            noNavigation: false,
            installationIdCheck: null,
            isInstallationIdCheckPending: true,
        };
    },

    computed: {
        desktopClasses() {
            return {
                'sw-desktop--no-nav': this.noNavigation,
                'sw-desktop--staging': this.isStaging,
            };
        },

        currentUser() {
            return Contena.Store.get('session').currentUser;
        },

        isStaging() {
            return Contena.Store.get('context').app.config.settings?.enableStagingMode === true;
        },

        showUsageDataConsentModalDataProvider() {
            return !this.isInstallationIdCheckPending && this.installationIdCheck === null;
        },
    },

    watch: {
        $route() {
            this.checkRouteSettings();
        },

        '$route.name': {
            handler(to, from) {
                if (from === undefined || to === from) {
                    return;
                }

                this.onUpdateSearchFrequently();
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkRouteSettings();
            this.updateInstallationIdChangeModal();
        },

        checkRouteSettings() {
            if (this.$route.meta && hasOwnProperty(this.$route.meta, 'noNav')) {
                this.noNavigation = this.$route.meta.noNav;
            } else {
                this.noNavigation = false;
            }
        },

        async updateInstallationIdChangeModal() {
            if (!Contena.Store.get('context').app.config.settings?.appsRequireAppUrl) {
                this.installationIdCheck = null;
                this.isInstallationIdCheckPending = false;
                return;
            }

            this.isInstallationIdCheckPending = true;

            try {
                this.installationIdCheck = await this.installationIdChangeService.checkInstallationId();
            } finally {
                this.isInstallationIdCheckPending = false;
            }
        },

        closeModal() {
            this.installationIdCheck = null;
        },

        async onCycleTheme() {
            const currentTheme = useTheme().theme.value;
            const nextTheme = THEMES[(THEMES.indexOf(currentTheme) + 1) % THEMES.length];

            try {
                await useTheme().saveUserTheme(nextTheme);
            } catch {
                useTheme().setTheme(currentTheme);
                this.snackbarService.addSnackbar({
                    message: this.$t('global.sw-desktop.theme.saveError'),
                    variant: 'error',
                });

                return;
            }

            this.snackbarService.addSnackbar({
                message: this.$t('global.sw-desktop.theme.changed', {
                    theme: this.$t(THEME_LABELS[nextTheme]),
                }),
                variant: 'success',
            });
        },

        onUpdateSearchFrequently() {
            const metadata = this.getModuleMetadata();

            if (!metadata || !metadata?.route?.name) {
                return false;
            }

            const data = {
                key: `${metadata.name}@${metadata.route.name}`,
                cluster: this.currentUser.id,
            };

            return this.admin_userActivityApiService.increment(data);
        },

        getModuleMetadata() {
            const { $module } = this.$route.meta;
            const routeName = this.$route?.name;

            if (!$module) {
                return false;
            }

            const { name, icon, color, entity, routes, title } = $module;

            if (!this.$te(title) || !routes?.index) {
                return false;
            }

            // special cases with searchMatcher function at the current module
            const searchMatcher = this.getModuleMetadataWithSearchMatcher($module, routeName);
            if (searchMatcher) {
                const { components, children, meta, props, ...route } = searchMatcher.route;
                return {
                    ...searchMatcher,
                    route,
                };
            }

            if (routes?.index?.name === routeName || routes.index?.children?.some((child) => child.name === routeName)) {
                const { components, children, meta, props, ...route } = routes.index;
                return {
                    name,
                    icon,
                    color,
                    title,
                    entity,
                    privilege: meta?.privilege,
                    route,
                };
            }

            if (routes?.create?.name === routeName || routes.create?.children?.some((child) => child.name === routeName)) {
                const { components, children, meta, props, ...route } = routes.create;
                return {
                    name,
                    icon,
                    color,
                    entity,
                    privilege: meta?.privilege,
                    route,
                    action: true,
                };
            }

            return false;
        },

        getModuleMetadataWithSearchMatcher(module, routeName) {
            if (typeof module.searchMatcher !== 'function') {
                return false;
            }

            const { title } = module;

            // get metadata in searchMatcher
            const metadata = module.searchMatcher(
                new RegExp(`^${this.$t(title).toLowerCase()}(.*)`),
                this.$t(title, 2),
                module,
            );

            return metadata.find(
                (item) => item.route.name === routeName || item.route?.children?.some((child) => child.name === routeName),
            );
        },
    },
};
