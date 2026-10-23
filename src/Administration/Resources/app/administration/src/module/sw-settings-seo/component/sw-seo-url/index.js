/**
 * @sw-package inventory
 */

import './store';
import template from './sw-seo-url.html.twig';
import './sw-seo-url.scss';

const Criteria = Contena.Data.Criteria;
const EntityCollection = Contena.Data.EntityCollection;
const { Defaults } = Contena;

/**
 * Sequences that are not URL-allowed inside a SEO path: a `%` that is not
 * part of a valid percent-escape, the fragment marker `#`, backslashes and
 * ASCII control characters. Query strings (`?`) and valid `%XX` escapes are
 * allowed. Keep this regex in sync with
 * `Contena\\Core\\Content\\Seo\\Validation\\Constraint\\ValidSeoPathInfo::DISALLOWED_CHARACTERS_PATTERN`.
 */
// eslint-disable-next-line no-control-regex
const DISALLOWED_SEO_PATH_CHARS = /%(?![0-9A-Fa-f]{2})|[#\\\x00-\x1F\x7F]/;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['on-change-sales-channel'],

    mixins: [],

    props: {
        channelId: {
            type: String,
            required: false,
            default: null,
        },

        urls: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        hasDefaultTemplate: {
            type: Boolean,
            required: false,
            default: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 25,
        },
    },

    data() {
        return {
            currentSalesChannelId: this.channelId,
            showEmptySeoUrlError: false,
        };
    },

    computed: {
        seoUrlCollection() {
            return Contena.Store.get('swSeoUrl').seoUrlCollection;
        },

        currentSeoUrl() {
            if (!Contena.Store.get('swSeoUrl')) {
                return {};
            }

            return Contena.Store.get('swSeoUrl').currentSeoUrl;
        },

        defaultSeoUrl() {
            return Contena.Store.get('swSeoUrl').defaultSeoUrl;
        },

        seoUrlRepository() {
            return this.repositoryFactory.create('seo_url');
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        isUnsupportedSalesChannel() {
            if (!Contena.Store.get('swSeoUrl')) {
                return true;
            }

            if (Contena.Store.get('swSeoUrl').channelCollection === null) {
                return true;
            }

            const channel = Contena.Store.get('swSeoUrl').channelCollection.find((entry) => {
                return entry.id === this.currentSalesChannelId;
            });

            // Blog comparison and agentic commerce sales channels do not serve SEO URLs.
            const unsupportedTypeIds = [Defaults.blogComparisonTypeId, Defaults.agenticCommerceTypeId];

            return this.currentSalesChannelId !== null && unsupportedTypeIds.includes(channel?.typeId);
        },

        currentSalesChannel() {
            const channelCollection = Contena.Store.get('swSeoUrl')?.channelCollection;

            return channelCollection?.find((entry) => entry.id === this.currentSalesChannelId) ?? null;
        },

        currentSalesChannelIsHeadless() {
            return this.currentSalesChannel?.typeId === Defaults.apiSalesChannelTypeId;
        },

        headlessExternalFrontendUrl() {
            const url = this.currentSalesChannel?.domains?.find(
                (domain) => domain.isExternalFrontend && domain.languageId === Contena.Context.api.languageId,
            )?.url;

            if (!url || url.endsWith('/')) {
                return url ?? null;
            }

            return `${url}/`;
        },

        seoUrlHelptext() {
            if (this.isUnsupportedSalesChannel) {
                return this.$t('sw-seo-url.textSeoUrlsNotSupported');
            }

            if (this.currentSalesChannelIsHeadless && !this.headlessExternalFrontendUrl) {
                return this.$t('sw-seo-url-template-card.general.textExternalFrontendRequired');
            }

            return null;
        },

        seoPathInfoError() {
            const seoPathInfo = this.currentSeoUrl?.seoPathInfo;

            if (typeof seoPathInfo !== 'string' || seoPathInfo === '') {
                return null;
            }

            if (!DISALLOWED_SEO_PATH_CHARS.test(seoPathInfo)) {
                return null;
            }

            return {
                code: 'CONTENT__SEO_URL_INVALID_CHARACTERS',
                detail: this.$t('sw-seo-url.errorInvalidCharacters'),
            };
        },

        hasAdditionalSeoSlot() {
            return this.$slots.hasOwnProperty('seo-additional');
        },

        allowInput() {
            return (
                (this.hasDefaultTemplate || this.currentSalesChannelId !== null) &&
                (!this.currentSalesChannelIsHeadless || !!this.headlessExternalFrontendUrl)
            );
        },
    },

    watch: {
        urls() {
            this.initSeoUrlCollection();
            this.refreshCurrentSeoUrl();
        },
    },

    created() {
        Contena.Utils.EventBus.on('sw-blog-detail-save-finish', this.clearDefaultSeoUrls);

        this.createdComponent();
    },

    beforeUnmount() {
        Contena.Utils.EventBus.off('sw-blog-detail-save-finish', this.clearDefaultSeoUrls);
    },

    methods: {
        createdComponent() {
            this.initSalesChannelCollection();
            this.initSeoUrlCollection();
            if (!this.showEmptySeoUrlError) {
                this.refreshCurrentSeoUrl();
            }
        },

        initSalesChannelCollection() {
            const channelCriteria = new Criteria(1, this.resultLimit);
            channelCriteria.addAssociation('type');
            channelCriteria.addAssociation('domains');

            this.channelRepository.search(channelCriteria).then((channelCollection) => {
                Contena.Store.get('swSeoUrl').channelCollection = channelCollection;
            });
        },

        initSeoUrlCollection() {
            this.showEmptySeoUrlError = false;
            const seoUrlCollection = new EntityCollection(
                this.seoUrlRepository.route,
                this.seoUrlRepository.schema.entity,
                Contena.Context.api,
                new Criteria(1, this.resultLimit),
            );

            const defaultSeoUrlData = this.urls.find((entityData) => {
                return entityData.channelId === null;
            });

            if (defaultSeoUrlData === undefined && (this.hasDefaultTemplate || this.urls.length <= 0)) {
                this.showEmptySeoUrlError = true;
            }

            const defaultSeoUrlEntity = this.seoUrlRepository.create();
            Object.assign(defaultSeoUrlEntity, defaultSeoUrlData);
            seoUrlCollection.add(defaultSeoUrlEntity);
            Contena.Store.get('swSeoUrl').defaultSeoUrl = defaultSeoUrlEntity;

            this.urls.forEach((entityData) => {
                const entity = this.seoUrlRepository.create();
                Object.assign(entity, entityData);

                seoUrlCollection.add(entity);
            });

            if (!Contena.Store.get('swSeoUrl').defaultSeoUrl) {
                this.showEmptySeoUrlError = true;
            }

            Contena.Store.get('swSeoUrl').seoUrlCollection = seoUrlCollection;
            Contena.Store.get('swSeoUrl').originalSeoUrls = this.urls;
            this.clearDefaultSeoUrls();
        },

        clearDefaultSeoUrls() {
            this.seoUrlCollection.forEach((entity) => {
                if (entity.id === this.defaultSeoUrl.id) {
                    return;
                }

                if (entity.seoPathInfo === this.defaultSeoUrl.seoPathInfo) {
                    entity.seoPathInfo = null;
                }
            });
        },

        refreshCurrentSeoUrl() {
            const actualLanguageId = Contena.Context.api.languageId;

            const currentSeoUrl = this.seoUrlCollection.find((entity) => {
                return entity.languageId === actualLanguageId && entity.channelId === this.currentSalesChannelId;
            });

            if (!currentSeoUrl) {
                const entity = this.seoUrlRepository.create();
                // Fetch any seo url as template, since we need to know foreignKey, pathInfo and the routeName
                const seoUrl =
                    this.seoUrlCollection.find((item) => {
                        return item.pathInfo && item.routeName && item.foreignKey;
                    }) || {};

                entity.foreignKey = this.defaultSeoUrl?.foreignKey ?? seoUrl.foreignKey;
                entity.isCanonical = true;
                entity.languageId = actualLanguageId;
                entity.channelId = this.currentSalesChannelId;
                entity.routeName = this.defaultSeoUrl?.routeName ?? seoUrl.routeName;
                entity.pathInfo = this.defaultSeoUrl?.pathInfo ?? seoUrl.pathInfo;
                entity.isModified = true;

                this.seoUrlCollection.add(entity);

                Contena.Store.get('swSeoUrl').currentSeoUrl = entity;

                return;
            }

            Contena.Store.get('swSeoUrl').currentSeoUrl = currentSeoUrl;
        },

        onSalesChannelChanged(channelId) {
            this.currentSalesChannelId = channelId;
            this.$emit('on-change-sales-channel', channelId);
            this.refreshCurrentSeoUrl();
        },
    },
};
