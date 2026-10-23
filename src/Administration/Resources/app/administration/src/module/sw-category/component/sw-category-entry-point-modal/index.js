import template from './sw-category-entry-point-modal.html.twig';
import './sw-category-entry-point-modal.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'cmsPageTypeService'],

    emits: ['modal-close'],

    props: {
        channelCollection: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            temporaryCollection: [],
            channelOptions: [],
            selectedSalesChannelId: '',
            showLayoutSelectionModal: false,
            pageTypes: ['page', 'landingpage', 'blog_list'],
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
        };
    },

    computed: {
        selectedSalesChannel() {
            return this.temporaryCollection.find((channel) => channel.id === this.selectedSalesChannelId);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.channelCollection.forEach((channel) => {
                this.temporaryCollection.push({
                    id: channel.id,
                    name: channel.name,
                    homeEnabled: channel.homeEnabled,
                    homeName: channel.homeName,
                    homeMetaTitle: channel.homeMetaTitle,
                    homeMetaDescription: channel.homeMetaDescription,
                    homeKeywords: channel.homeKeywords,
                    homeCmsPageId: channel.homeCmsPageId,
                    homeCmsPage: channel.homeCmsPage ? { ...channel.homeCmsPage } : null,
                    translated: channel.translated ? { ...channel.translated } : null,
                });

                this.channelOptions.push({
                    value: channel.id,
                    label: channel.translated ? channel.translated.name : channel.name,
                });
            });

            if (this.channelCollection.length > 0) {
                this.selectedSalesChannelId = this.channelOptions[0].value;
            }
        },

        closeModal() {
            this.$emit('modal-close');
        },

        getCmsPageTypeName(name) {
            const fallback = this.$t('sw-category.base.cms.defaultDesc');

            if (!name) {
                return fallback;
            }

            const nameSnippetKey = this.cmsPageTypeService.getType(name)?.title;
            return nameSnippetKey ? this.$t(nameSnippetKey) : fallback;
        },

        onLayoutSelect(layoutId, layout) {
            this.selectedSalesChannel.homeCmsPage = layout;
            this.selectedSalesChannel.homeCmsPageId = layoutId;
        },

        onLayoutReset() {
            this.onLayoutSelect(null, null);
        },

        openInPagebuilder() {
            let to = { name: 'sw.cms.create' };
            if (this.selectedSalesChannel.homeCmsPage) {
                to = {
                    name: 'sw.cms.detail',
                    params: { id: this.selectedSalesChannel.homeCmsPageId },
                };
            }

            if (this.hasNotAppliedChanges()) {
                this.isDisplayingLeavePageWarning = true;
                this.nextRoute = to;
                return;
            }

            this.closeModal();

            this.$nextTick(() => {
                this.$router.push(to);
            });
        },

        openLayoutModal() {
            if (!this.acl.can('category.editor')) {
                return;
            }

            this.showLayoutSelectionModal = true;
        },

        closeLayoutModal() {
            this.showLayoutSelectionModal = false;
        },

        applyChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const tempSalesChannel = this.temporaryCollection[i];
                const realSalesChannel = this.channelCollection[i];

                realSalesChannel.name = tempSalesChannel.name;
                realSalesChannel.homeEnabled = tempSalesChannel.homeEnabled;
                realSalesChannel.homeName = tempSalesChannel.homeName;
                realSalesChannel.homeMetaTitle = tempSalesChannel.homeMetaTitle;
                realSalesChannel.homeMetaDescription = tempSalesChannel.homeMetaDescription;
                realSalesChannel.homeKeywords = tempSalesChannel.homeKeywords;
                realSalesChannel.homeCmsPageId = tempSalesChannel.homeCmsPageId;
                realSalesChannel.homeCmsPage = tempSalesChannel.homeCmsPage;
            }

            this.closeModal();
        },

        hasNotAppliedChanges() {
            for (let i = 0; i < this.temporaryCollection.length; i += 1) {
                const original = this.channelCollection[i];
                const copy = this.temporaryCollection[i];

                if (
                    this.isAttributeEqual(copy.name, original.name) ||
                    this.isAttributeEqual(copy.homeEnabled, original.homeEnabled) ||
                    this.isAttributeEqual(copy.homeName, original.homeName) ||
                    this.isAttributeEqual(copy.homeMetaTitle, original.homeMetaTitle) ||
                    this.isAttributeEqual(copy.homeMetaDescription, original.homeMetaDescription) ||
                    this.isAttributeEqual(copy.homeKeywords, original.homeKeywords) ||
                    this.isAttributeEqual(copy.homeCmsPageId, original.homeCmsPageId)
                ) {
                    return true;
                }
            }

            return false;
        },

        isAttributeEqual(copy, original) {
            if (copy === null) {
                copy = '';
            }

            if (original === null) {
                original = '';
            }

            return copy !== original;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            this.isDisplayingLeavePageWarning = false;
            this.$nextTick(() => {
                this.closeModal();
                this.$nextTick(() => {
                    this.$router.push({
                        name: destination.name,
                        params: destination.params,
                    });
                });
            });
        },
    },
};
