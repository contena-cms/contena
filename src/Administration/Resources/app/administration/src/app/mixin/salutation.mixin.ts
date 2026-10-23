/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import type { SalutationFilterEntityType } from '../composables/use-salutation';

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-salutation`; change both together.
 */
export default Contena.Mixin.register(
    'salutation',
    defineComponent({
        computed: {
            salutationFilter(): (entity: SalutationFilterEntityType, fallbackSnippet: string) => string {
                return Contena.Filter.getByName('salutation');
            },
        },

        methods: {
            salutation(entity: SalutationFilterEntityType, fallbackSnippet = '') {
                return this.salutationFilter(entity, fallbackSnippet);
            },
        },
    }),
);
