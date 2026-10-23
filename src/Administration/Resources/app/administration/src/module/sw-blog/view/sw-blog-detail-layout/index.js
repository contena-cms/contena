/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-layout.html.twig';
import './sw-blog-detail-layout.scss';

const { Context, Utils } = Contena;
const { Criteria } = Contena.Data;
const { get } = Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'cmsService',
        'feature',
        'acl',
    ],

    data() {
        return {
            showLayoutModal: false,
            isConfigLoading: false,
        };
    },

    computed: {
        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        cmsPageId() {
            return get(this.blog, 'cmsPageId', null);
        },

        showCmsForm() {
            return (!this.isLoading || !this.isConfigLoading) && !this.currentPage.locked;
        },

        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        cmsPageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks').addSorting(Criteria.sort('position', 'ASC')).addAssociation('slots');

            return criteria;
        },

        languageId() {
            return Contena.Context.api.languageId;
        },

        currentPage() {
            return Contena.Store.get('cmsPage').currentPage;
        },

        cmsPageState() {
            return Contena.Store.get('cmsPage');
        },
    },

    watch: {
        cmsPageId() {
            this.cmsPageState.resetCmsPageState();
            this.handleGetCmsPage();
        },

        blog: {
            deep: true,
            handler(value) {
                if (!value) {
                    return;
                }

                this.updateCmsPageDataMapping();
            },
        },

        languageId() {
            this.cmsPageState.resetCmsPageState();
            this.handleGetCmsPage();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            // Keep current layout configuration if page sections exist
            const sections = this.currentPage?.sections ?? [];

            if (sections.length) {
                return;
            }

            this.handleGetCmsPage();
        },

        onOpenLayoutModal() {
            if (!this.acl.can('blog.editor')) {
                return;
            }

            this.showLayoutModal = true;
        },

        onCloseLayoutModal() {
            this.showLayoutModal = false;
        },

        onOpenInPageBuilder() {
            if (!this.currentPage) {
                this.$router.push({ name: 'sw.cms.create' });
            } else {
                this.$router.push({
                    name: 'sw.cms.detail',
                    params: { id: this.currentPage.id },
                });
            }
        },

        onSelectLayout(cmsPageId) {
            if (!this.blog) {
                return;
            }

            this.blog.cmsPageId = cmsPageId;
            this.resetSlotConfig();
            Contena.Store.get('swProductDetail').blog = this.blog;
        },

        handleGetCmsPage() {
            if (!this.cmsPageId) {
                return;
            }

            this.isConfigLoading = true;

            this.cmsPageRepository.get(this.cmsPageId, Context.api, this.cmsPageCriteria).then((cmsPage) => {
                this.cmsPageState.setCurrentPage(cmsPage);
                this.updateCmsPageDataMapping();
                this.isConfigLoading = false;
            });
        },

        updateCmsPageDataMapping() {
            this.cmsPageState.setCurrentMappingEntity('blog');
            this.cmsPageState.setCurrentMappingTypes(this.cmsService.getEntityMappingTypes('blog'));
            this.cmsPageState.setCurrentDemoEntity(this.blog);
        },

        onResetLayout() {
            this.onSelectLayout(null);
        },

        elementUpdate(element) {
            const slotContent = this.blog.slotConfig[element.id]?.content;
            if (slotContent && slotContent.value) {
                slotContent.value = element.config.content.value;
            }
        },

        resetSlotConfig() {
            this.blog.slotConfig = null;

            this.blog.translations?.forEach((translation) => {
                translation.slotConfig = null;
            });
        },
    },
};
