/*
 * @sw-package inventory
 */

import template from './sw-blog-visibility-detail.html.twig';
import './sw-blog-visibility-detail.scss';

const { Filter } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        truncateFilter() {
            return Filter.getByName('truncate');
        },

        filteredItems() {
            return this.blog.visibilities.filter((item) => {
                return !item.isDeleted;
            });
        },

        names() {
            const names = {};

            this.filteredItems.forEach((item) => {
                names[item.id] = item.channelInternal
                    ? item.channelInternal.translated.name
                    : item.channel.translated.name;
            });

            return names;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onPageChange({ page: this.page, limit: this.limit });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;

            this.total = this.filteredItems.length;
            this.items = this.filteredItems.slice(offset, offset + params.limit);
        },

        changeVisibilityValue(event, item) {
            item.visibility = Number(event);
        },
    },
};
