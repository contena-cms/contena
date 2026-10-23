import template from './sw-cms-el-blog-box.html.twig';
import './sw-cms-el-blog-box.scss';

const { Mixin, Filter } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Mixin.getByName('cms-element'), Mixin.getByName('placeholder')],

    computed: {
        blog() {
            if (!this.element?.data?.blog) {
                return {
                    name: 'Lorem ipsum dolor',
                    description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                    sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                    sed diam voluptua.`,
                    price: [{ gross: 19.9 }],
                    cover: {
                        media: {
                            url: '/administration/administration/static/img/cms/preview_glasses_large.webp',
                            alt: 'Lorem Ipsum dolor',
                        },
                    },
                };
            }

            return this.element.data.blog;
        },

        displaySkeleton() {
            return !this.element?.data?.blog;
        },

        mediaUrl() {
            if (this.blog.cover && this.blog.cover.media) {
                if (this.blog.cover.media.id) {
                    return this.blog.cover.media.url;
                }

                return this.assetFilter(this.blog.cover.media.url);
            }

            return this.assetFilter('administration/administration/static/img/cms/preview_glasses_large.webp');
        },

        altTag() {
            if (!this.blog?.cover?.media?.alt) {
                return null;
            }

            return this.blog.cover.media.alt;
        },

        displayModeClass() {
            if (this.element.config.displayMode.value === 'standard') {
                return null;
            }

            return `is--${this.element.config.displayMode.value}`;
        },

        verticalAlignStyle() {
            if (!this.element.config?.verticalAlign?.value) {
                return null;
            }

            return `align-content: ${this.element.config.verticalAlign.value};`;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        truncateFilter() {
            return Filter.getByName('truncate');
        },

        currencyFilter() {
            return Filter.getByName('currency');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-box');
            this.initElementData('blog-box');
        },
    },
};
