import { MAIN_HIDDEN } from '@contena-ag/meteor-admin-sdk/es/location';
import template from './sw-hidden-iframes.html.twig';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    computed: {
        extensions() {
            return Contena.Store.get('extensions').privilegedExtensions;
        },

        MAIN_HIDDEN() {
            return MAIN_HIDDEN;
        },
    },
};
