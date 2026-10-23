import template from './sw-settings-frontend-configuration.html.twig';
import './sw-settings-frontend-configuration.scss';

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement.
 * @sw-package framework
 */
export default {
    template,

    inject: ['feature'],

    props: {
        frontendSettings: {
            type: Object,
            required: true,
        },
    },
};
