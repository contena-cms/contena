import { mapState } from 'pinia';
import useSession from 'src/app/composables/use-session';
import { useContenaServicesStore } from '../../store/contena-services.store';
import template from './sw-settings-services-index.html.twig';
import './sw-settings-services-index.scss';
import type { ServiceDescription } from '../../service/contena-services.service';
import extractError from '../../composables/extract-error';
import {
    getServicesWithContenaAccountRequirement,
    type ServiceWithContenaAccountRequirement,
} from '../../requirements/index';

import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import SwSettingsServicesServiceCard from '../../component/sw-settings-services-service-card';

type SwSettingsPageData = {
    grantPermissionsCardBackground: string;
    services: ServiceDescription[];
    suspended: boolean;
    loadingError: string;
};

/**
 * @sw-package framework
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    name: 'sw-settings-services-index',

    template,

    inject: ['acl'],

    components: {
        SwSettingsServicesHero,
        SwSettingsServicesGrantPermissionsCard,
        SwSettingsServicesRevokePermissionsModal,
        SwSettingsServicesDeactivateModal,
        SwSettingsServicesServiceCard,
    },

    data(): SwSettingsPageData {
        const assetFilter = Contena.Filter.getByName('asset');

        return {
            grantPermissionsCardBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            services: [],
            suspended: true,
            loadingError: '',
        };
    },

    computed: {
        ...mapState(useContenaServicesStore, ['config', 'currentRevision', 'consentGiven']),
        servicesWithAccountRequirement(): ServiceWithContenaAccountRequirement[] {
            return getServicesWithContenaAccountRequirement(this.services);
        },
    },

    created() {
        const contenaServicesService = Contena.Service('contenaServicesService');
        const serviceRegistryClient = Contena.Service('serviceRegistryClient');
        const contenaServicesStore = useContenaServicesStore();
        const sessionStore = useSession();

        Promise.all([
            this.reloadServices(),
            contenaServicesService.getServicesContext().then((servicesConsent) => {
                contenaServicesStore.config = servicesConsent;
            }),
            serviceRegistryClient
                .getCurrentRevision(sessionStore.currentLocale.value ?? 'en-GB')
                .then((serviceRevisions) => {
                    contenaServicesStore.revisions = serviceRevisions;
                }),
        ])
            .then(() => {
                this.suspended = false;
            })
            .catch((exception) => {
                const errorMessage = extractError(exception);

                Contena.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: errorMessage,
                });
            });
    },

    methods: {
        async activateServices() {
            try {
                const contenaServicesService = Contena.Service('contenaServicesService');
                const contenaServicesStore = useContenaServicesStore();

                contenaServicesStore.config = await contenaServicesService.enableAllServices();

                Contena.Store.get('notification').createNotification({
                    title: this.$t('sw-settings-services.index.services-enabled'),
                    variant: 'positive',
                    message: this.$t('sw-settings-services.index.services-scheduled'),
                });
            } catch (exceptionResponse) {
                Contena.Store.get('notification').createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            }
        },

        async reloadServices() {
            try {
                const contenaServicesService = Contena.Service('contenaServicesService');

                this.services = await contenaServicesService.getInstalledServices();
            } catch (exception) {
                this.loadingError = extractError(exception);

                Contena.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-settings-services.exception.service-list'),
                    autoClose: false,
                });

                this.services = [];
            }
        },
    },
});
