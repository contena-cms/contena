/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-blog-comparison.html.twig';
import './sw-sales-channel-detail-blog-comparison.scss';

const { Mixin, Defaults } = Contena;
const { Criteria } = Contena.Data;
const { warn } = Contena.Utils.debug;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'channelService',
        'repositoryFactory',
        'blogExportService',
        'entityMappingService',
        'acl',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        channel: {
            required: true,
        },

        blogExport: {
            required: true,
        },

        isLoading: {
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null,
            previewContent: null,
            previewErrors: null,
            isLoadingPreview: false,
            isPreviewSuccessful: false,
            isLoadingValidate: false,
            isValidateSuccessful: false,
            isLoadingReset: false,
        };
    },

    computed: {
        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerce() {
            return this.channel?.typeId === Defaults.agenticCommerceTypeId;
        },

        editorConfig() {
            return {
                enableBasicAutocompletion: true,
            };
        },

        blogExportRepository() {
            return this.repositoryFactory.create('blog_export');
        },

        domainRepository() {
            return this.repositoryFactory.create(this.channel.domains.entity, this.channel.domains.source);
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);

            return criteria.addFilter(Criteria.equals('type', 'page'));
        },

        outerCompleterFunctionHeader() {
            return this.outerCompleterFunction({
                blogExport: 'blog_export',
            });
        },

        outerCompleterFunctionBody() {
            return this.outerCompleterFunction({
                blogExport: 'blog_export',
                blog: 'blog',
            });
        },

        outerCompleterFunctionFooter() {
            return this.outerCompleterFunction({
                blogExport: 'blog_export',
            });
        },
    },

    methods: {
        validateTemplate() {
            const notificationValidateSuccess = {
                message: this.$t('sw-sales-channel.detail.blogComparison.notificationMessageValidateSuccessful'),
            };

            this.isLoadingValidate = true;

            this.blogExportService
                .validateProductExportTemplate(this.blogExport)
                .then((data) => {
                    this.isLoadingValidate = false;

                    if (data.errors) {
                        this.previewContent = data.content;
                        this.previewErrors = data.errors;
                        return;
                    }

                    this.createNotificationSuccess(notificationValidateSuccess);
                    this.isValidateSuccessful = true;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception.response.data.errors[0].detail,
                    });
                    warn(this._name, exception.message, exception.response);

                    this.isLoadingValidate = false;
                    this.isValidateSuccessful = false;
                });
        },

        preview() {
            this.isLoadingPreview = true;

            this.blogExportService
                .previewProductExport(this.blogExport)
                .then((data) => {
                    this.isLoadingPreview = false;
                    this.previewContent = data.content;

                    if (data.errors) {
                        this.previewErrors = data.errors;
                        return;
                    }

                    this.isPreviewSuccessful = true;
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: exception.response.data.errors[0].detail,
                    });
                    warn(this._name, exception.message, exception.response);

                    this.isLoadingPreview = false;
                });
        },

        outerCompleterFunction(mapping) {
            const entityMappingService = this.entityMappingService;

            return function completerFunction(prefix) {
                const entityMapping = entityMappingService.getEntityMapping(prefix, mapping);
                return Object.keys(entityMapping).map((val) => {
                    return { value: val };
                });
            };
        },

        onPreviewClose() {
            this.previewContent = null;
            this.previewErrors = null;
            this.isPreviewSuccessful = false;
        },

        resetToDefault() {
            const provider = this.blogExport.provider || 'open-ai';
            const registry = Contena.Service('exportTemplateService').getProductExportTemplateRegistry();
            const template = Object.values(registry).find((entry) => entry.providerName === provider);

            if (!template) {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.agenticCommerce.errorLoadingTemplate'),
                });

                return;
            }

            this.blogExport.headerTemplate = template.headerTemplate;
            this.blogExport.bodyTemplate = template.bodyTemplate;
            this.blogExport.footerTemplate = template.footerTemplate;

            this.createNotificationInfo({
                message: this.$t('sw-sales-channel.detail.agenticCommerce.resetTemplateSuccess'),
            });
        },

        resetValid() {
            this.isValidateSuccessful = false;
        },
    },
};
