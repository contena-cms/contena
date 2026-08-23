import type { RouteLocationNormalized } from 'vue-router';

const utils = Contena.Utils;

/**
 * @private
 */
export default {
    beforeEnter(this: void, to: RouteLocationNormalized) {
        if (to.params.id) {
            return true;
        }

        return {
            name: 'ct.experience.studio.create',
            params: { id: utils.createId() },
            query: to.query,
            replace: true,
        };
    },
};
