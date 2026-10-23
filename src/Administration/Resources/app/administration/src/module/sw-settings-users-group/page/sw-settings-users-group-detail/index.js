import './sw-settings-user-group-detail.scss';
import template from './sw-settings-user-group-detail.html.twig';

/**
 * @sw-package discovery
 */
const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const { ContenaError } = Contena.Classes;
const types = Contena.Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('userGroup'),
    ],

    props: {
        userGroupId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: false,
            userGroup: null,
            isSaveSuccessful: false,
            openSeoModal: false,
            registrationTitleError: null,
            seoUrls: [],
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.userGroup, 'name', '');
        },

        userGroupRepository() {
            return this.repositoryFactory.create('user_group');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        userGroupCriteria() {
            const criteria = new Criteria(1, 1);

            criteria
                .addAssociation('registrationSalesChannels')
                .getAssociation('registrationSalesChannels')
                .addAssociation('domains')
                .addAssociation('seoUrls');

            criteria
                .getAssociation('registrationSalesChannels')
                .getAssociation('seoUrls')
                .addFilter(Criteria.equals('pathInfo', `/user-group-registration/${this.userGroupId}`))
                .addFilter(Criteria.equals('isCanonical', true))
                .addAssociation('language');

            return criteria;
        },

        registrationSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria
                .addAssociation('domains')
                .addAssociation('seoUrls')
                .getAssociation('seoUrls')
                .addFilter(Criteria.equals('pathInfo', `/user-group-registration/${this.userGroupId}`))
                .addFilter(Criteria.equals('isCanonical', true))
                .addAssociation('language');

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        seoUrlCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.userGroup?.registrationSalesChannels?.length) {
                const channelIds = this.userGroup.registrationSalesChannels?.getIds();

                criteria.addFilter(Criteria.equalsAny('channelId', channelIds));
            }

            criteria.addFilter(Criteria.equals('pathInfo', `/user-group-registration/${this.userGroupId}`));
            criteria.addFilter(Criteria.equals('languageId', Contena.Context.api.languageId));
            criteria.addFilter(Criteria.equals('isCanonical', true));
            criteria.addAssociation('channel.domains');
            criteria.addGroupField('seoPathInfo');
            criteria.addGroupField('channelId');

            return criteria;
        },

        entityDescription() {
            return this.placeholder(
                this.userGroup,
                'name',
                this.$t('sw-settings-user-group.detail.placeholderNewCustomerGroup'),
            );
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        hasRegistration: {
            get() {
                return this.userGroup && this.userGroup.registration !== undefined;
            },
            set(value) {
                if (value) {
                    this.userGroup.registration = this.userGroupRegistrationRepository.create();
                } else {
                    this.userGroup.registration = null;
                }
            },
        },

        technicalUrl() {
            return `<domain-url>/user-group-registration/${this.userGroupId}#`;
        },

        ...mapPropertyErrors('userGroup', ['name']),

        allowSave() {
            return this.userGroup && this.userGroup.isNew()
                ? this.acl.can('user_groups.creator')
                : this.acl.can('user_groups.editor');
        },

        showCustomFields() {
            return this.userGroup && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        userGroupId() {
            if (!this.userGroupId) {
                this.createdComponent();
            }
        },
        'userGroup.registrationTitle'() {
            this.registrationTitleError = null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (!this.userGroupId) {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });

                this.isLoading = true;
                return;
            }

            this.loadCustomFieldSets();
            this.loadCustomerGroup();
        },

        async loadCustomerGroup() {
            this.isLoading = true;

            try {
                this.userGroup = await this.userGroupRepository.get(
                    this.userGroupId,
                    Contena.Context.api,
                    this.userGroupCriteria,
                );
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        async loadSeoUrls() {
            if (!this.userGroup?.registrationSalesChannels?.length) {
                this.seoUrls = [];
                return;
            }
            this.seoUrls = await this.seoUrlRepository.search(this.seoUrlCriteria);
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('user_group').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.user.group.index' });
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement. Seo URLs are now constructed in the template.
         */
        getSeoUrl(seoUrl) {
            let shopUrl = '';

            seoUrl.channel.domains.forEach((domain) => {
                if (domain.languageId === seoUrl.languageId) {
                    shopUrl = domain.url;
                }
            });

            return `${shopUrl}/${seoUrl.seoPathInfo}`;
        },

        validateSaveRequest() {
            if (
                Contena.Context.api.languageId === Contena.Context.api.systemLanguageId &&
                this.userGroup.registrationActive &&
                types.isEmpty(this.userGroup.registrationTitle)
            ) {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });

                this.registrationTitleError = new ContenaError({
                    code: 'CUSTOMER_GROUP_REGISTERATION_MISSING_TITLE',
                    detail: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                });

                this.isLoading = false;
                this.isSaveSuccessful = false;
                return false;
            }

            return true;
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (!this.validateSaveRequest()) {
                return;
            }

            try {
                await this.userGroupRepository
                    .save(this.userGroup)
                    .then((response) => (response !== undefined ? this.loadCustomerGroup() : Promise.resolve()));

                this.isSaveSuccessful = true;
            } catch (_err) {
                this.createNotificationError({
                    message: this.$t('sw-settings-user-group.detail.notificationErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
};
