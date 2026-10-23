/*
 * @sw-package inventory
 */

import template from './sw-blog-basic-form.html.twig';
import './sw-blog-basic-form.scss';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        showSettingsInformation: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            blogNumberRangeId: null,
            hideCoverImageDescriptionHint: true,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        ...mapPropertyErrors('blog', [
            'name',
            'description',
            'blogNumber',
            'manufacturerId',
            'active',
            'markAsTopseller',
        ]),

        isTitleRequired() {
            return Contena.Store.get('context').isSystemDefaultLanguage;
        },

        blogNumberRangeLink() {
            if (!this.blogNumberRangeId) {
                return {
                    name: 'sw.settings.number.range.index',
                };
            }

            return {
                name: 'sw.settings.number.range.detail',
                params: { id: this.blogNumberRangeId },
            };
        },

        blogNumberHelpText() {
            return this.$t(
                'sw-blog.basicForm.blogNumberHelpText.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(this.blogNumberRangeLink)}
                           :inline="true">
                           ${this.$t('sw-blog.basicForm.blogNumberHelpText.linkText')}
                       </sw-internal-link>`,
                },
                0,
            );
        },

        highlightHelpText() {
            const themesLink = {
                name: 'sw.theme.manager.index',
            };

            const snippetLink = {
                name: 'sw.settings.snippet.detail',
                params: { key: 'listing.boxLabelTopseller' },
            };

            return this.$t(
                'sw-blog.basicForm.highlightHelpText.label',
                {
                    themesLink: `<sw-internal-link
                                 :router-link=${JSON.stringify(themesLink)}
                                 :inline="true">
                                 ${this.$t('sw-blog.basicForm.highlightHelpText.themeLinkText')}
                             </sw-internal-link>`,
                    snippetLink: `<sw-internal-link
                                  :router-link=${JSON.stringify(snippetLink)}
                                  :inline="true">
                                  ${this.$t('sw-blog.basicForm.highlightHelpText.snippetLinkText')}
                              </sw-internal-link>`,
                },
                0,
            );
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProductNumberRangeId();
            this.loadCoverImageDescriptionHintConfig();
        },

        async loadCoverImageDescriptionHintConfig() {
            const config = (await Contena.Service('admin_userConfigService').search(['blog.hideCoverImageDescriptionHint']))
                ?.data?.['blog.hideCoverImageDescriptionHint'];

            this.hideCoverImageDescriptionHint = !!config?.value;
        },

        async onCloseCoverImageDescriptionHint() {
            this.hideCoverImageDescriptionHint = true;

            await Contena.Service('admin_userConfigService').upsert({
                'blog.hideCoverImageDescriptionHint': {
                    value: true,
                },
            });
        },

        updateIsTitleRequired() {
            this.isTitleRequired = Contena.Context.api.languageId === Contena.Context.api.systemLanguageId;
        },

        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        },

        loadProductNumberRangeId() {
            const criteria = new Contena.Data.Criteria(1, 25);

            criteria.addFilter(Contena.Data.Criteria.equals('type.technicalName', 'blog'));
            criteria.addFilter(Contena.Data.Criteria.equals('global', true));

            return Contena.Service('repositoryFactory')
                .create('number_range')
                .searchIds(criteria, Contena.Context.api, {
                    cacheKey: ['shared-data', 'number-range-ids', 'blog'],
                    ttl: 5 * 60 * 1000,
                })
                .then((numberRangeIds) => {
                    this.blogNumberRangeId = numberRangeIds.data[0];
                });
        },
    },
};
