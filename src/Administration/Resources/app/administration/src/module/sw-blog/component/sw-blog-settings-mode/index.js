/*
 * @sw-package inventory
 */

import template from './sw-blog-settings-mode.html.twig';
import './sw-blog-settings-mode.scss';

/**
 * @deprecated tag:v6.8.0 - Component will be removed. No longer used.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['settings-change', 'settings-item-change'],

    props: {
        modeSettings: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        advancedMode() {
            return this.modeSettings.value.advancedMode;
        },

        settings() {
            switch (this.$route.name) {
                case 'sw.blog.detail.base': {
                    return this.modeSettings.value.settings.filter(({ name }) => name === 'general');
                }

                case 'sw.blog.detail.specifications': {
                    return this.modeSettings.value.settings.filter(({ name }) => name === 'specifications');
                }

                default: {
                    return this.modeSettings.value.settings;
                }
            }
        },
    },

    methods: {
        onChangeSetting() {
            this.$emit('settings-change');
        },

        onChangeSettingItem() {
            this.$emit('settings-item-change');
        },
    },
};
