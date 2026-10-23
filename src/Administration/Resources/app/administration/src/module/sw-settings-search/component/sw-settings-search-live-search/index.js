/**
 * @sw-package inventory
 */
import template from './sw-settings-search-live-search.html.twig';
import './sw-settings-search-live-search.scss';
import '../sw-settings-search-live-search-keyword';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'liveSearchService'],

    emits: ['live-search-results-change', 'sales-channel-change'],

    mixins: [Mixin.getByName('notification')],

    props: {
        currentSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },

        searchTerms: {
            type: String,
            required: false,
            default: null,
        },

        searchResults: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            liveSearchTerm: '',
            channels: [],
            channelId: this.currentSalesChannelId,
            blogSortings: [],
            blogSortingKey: null,
            liveSearchResults: null,
            searchInProgress: false,
            showExampleModal: false,
        };
    },

    computed: {
        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        blogSortingRepository() {
            return this.repositoryFactory.create('blog_sorting');
        },

        isSearchEnable() {
            return this.channelId !== null;
        },

        searchColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-settings-search.liveSearchTab.labelName'),
                    rawData: true,
                },
                {
                    property: 'score',
                    label: this.$t('sw-settings-search.liveSearchTab.labelScore'),
                    rawData: true,
                },
            ];
        },

        blogs() {
            return this.liveSearchResults && this.liveSearchResults.elements;
        },

        blogSortingCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('active', true));
            criteria.addSorting(Criteria.sort('priority', 'DESC'));
            return criteria;
        },

        searchParams() {
            const params = {
                channelId: this.channelId,
                search: this.liveSearchTerm,
            };

            if (this.blogSortingKey) {
                params.order = this.blogSortingKey;
            }

            return params;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSalesChannels();
            this.fetchProductSortings();
            this.liveSearchTerm = this.searchTerms;
            this.liveSearchResults = this.searchResults;
        },

        searchOnFrontend() {
            if (!this.liveSearchTerm.length) {
                return;
            }

            this.searchInProgress = true;

            this.liveSearchService
                .search(this.searchParams, {}, {}, { 'sw-language-id': Contena.Context.api.languageId })
                .then((data) => {
                    this.liveSearchResults = data.data;
                    this.searchInProgress = false;
                    this.$emit('live-search-results-change', {
                        searchTerms: this.liveSearchTerm,
                        searchResults: this.liveSearchResults,
                    });
                })
                .catch((error) => {
                    const message =
                        error.response.status === 500
                            ? this.$t('sw-settings-search.notification.notSupportedLanguageError')
                            : error.message;

                    this.createNotificationError({
                        message,
                    });
                })
                .finally(() => {
                    this.searchInProgress = false;
                });
        },

        fetchSalesChannels() {
            this.channelRepository.search(new Criteria(1, 25)).then((response) => {
                this.channels = response;
            });
        },

        fetchProductSortings() {
            this.blogSortingRepository.search(this.blogSortingCriteria).then((response) => {
                this.blogSortings = response;
                const topSearchSorting = this.blogSortings.find((entry) => entry.key === 'score');

                if (topSearchSorting) {
                    this.blogSortingKey = topSearchSorting.key;
                }
            });
        },

        changeSalesChannel(channelId) {
            this.channelId = channelId;
            this.$emit('sales-channel-change', channelId);
        },

        onShowExampleModal() {
            this.showExampleModal = true;
        },

        onCloseExampleModal() {
            this.showExampleModal = false;
        },
    },
};
