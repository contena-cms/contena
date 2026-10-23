/**
 * @sw-package framework
 */
import useSession from 'src/app/composables/use-session';
import template from './sw-settings-services-grant-permissions-modal.html.twig';
import './sw-settings-services-grant-permissions-modal.scss';
import { useContenaServicesStore } from '../../store/contena-services.store';
import extractErrorMessage from '../../composables/extract-error';
import { grantPermissions } from '../../composables/permissions';

/**
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    name: 'sw-settings-services-grant-permissions-modal',
    template,

    data() {
        const assetFilter = Contena.Filter.getByName('asset');

        return {
            grantPermissionsBackground: assetFilter(
                '/administration/administration/static/img/services/grant-permissions-background.svg',
            ),
            isLoading: false,
        };
    },

    computed: {
        feedbackLink() {
            return useContenaServicesStore().currentRevision?.links['docs-url'] ?? '';
        },

        showGrantPermissionsModal: {
            get() {
                return useContenaServicesStore().showGrantPermissionsModal;
            },
            set(value: boolean) {
                useContenaServicesStore().showGrantPermissionsModal = value;
            },
        },
    },

    methods: {
        prepareRevisions(isOpen: boolean) {
            this.showGrantPermissionsModal = isOpen;

            if (this.showGrantPermissionsModal && !this.feedbackLink) {
                Contena.Service('serviceRegistryClient')
                    .getCurrentRevision(useSession().currentLocale.value as string)
                    .then((revisions) => {
                        useContenaServicesStore().revisions = revisions;
                    })
                    .catch(() => {});
            }
        },

        async grantPermissions(done: () => void) {
            try {
                this.isLoading = true;

                await grantPermissions();
            } catch (exception) {
                Contena.Store.get('notification').createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: extractErrorMessage(exception),
                });
            } finally {
                this.isLoading = false;
                done();
            }
        },
    },
});
