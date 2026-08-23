import { computed, h, inject } from 'vue';
import parentsInjectionKey from '../ct-block/parents-injection-key';

/**
 *
 * @description
 * The `ct-block-parent` component is used to render the parent block content. It is used in combination with the
 * `ct-block` component to extend the content of the `ct-block-extension` component.
 * See the `ct-block-extension` component for more information.
 *
 * @private
 *
 */
export default Contena.Component.wrapComponentConfig({
    setup() {
        const parents = inject(parentsInjectionKey, null);
        const initialParents = parents?.value;
        const initialParent = initialParents?.pop();
        const parentIndex = initialParents ? initialParents.length : -1;
        // Reserve the stack slot once, then read the current VNode at that slot after reactive parent updates.
        const parent = computed(() => {
            if (parentIndex < 0 || !parents || parents.value === initialParents) {
                return initialParent;
            }

            return parents.value[parentIndex];
        });

        return {
            parent,
        };
    },
    render() {
        return h(() => this.parent);
    },
});
