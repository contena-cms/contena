import template from './sw-blog-image.html.twig';
import './sw-blog-image.scss';

/**
 * @sw-package framework
 *
 * @private
 * @description Component which renders an image.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-image :item="item" isCover="true"></sw-image>
 */
export default {
    template,

    emits: ['sw-blog-image-cover', 'sw-blog-image-delete'],

    props: {
        mediaId: {
            type: String,
            required: true,
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isSpatial: {
            type: Boolean,
            required: false,
            default: false,
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isArReady: {
            type: Boolean,
            required: false,
            default: false,
        },

        isCover: {
            type: Boolean,
            required: false,
            default: false,
        },

        isPlaceholder: {
            type: Boolean,
            required: false,
            default: false,
        },

        showCoverLabel: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        blogImageClasses() {
            return {
                'is--placeholder': this.isPlaceholder,
                'is--cover': this.isCover && this.showCoverLabel,
                'is--spatial': this.isSpatial,
            };
        },
    },
};
