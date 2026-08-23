import { inject, watch } from 'vue';
import { matchedRouteKey, onBeforeRouteLeave } from 'vue-router';

import type { RouteLocationNormalizedLoaded } from 'vue-router';

type DiscardableEntity = { discardChanges?: () => void } | null | undefined;

/**
 * Discards DAL drafts and API errors when a detail page changes identity or is left.
 *
 * @private
 */
export function useDiscardDetailPageChanges(
    route: RouteLocationNormalizedLoaded,
    entities: Record<string, () => DiscardableEntity>,
): { discardChanges: () => void } {
    function discardChanges(): void {
        Object.entries(entities).forEach(
            ([
                name,
                getEntity,
            ]) => {
                const entity = getEntity();
                if (typeof entity?.discardChanges === 'function') {
                    entity.discardChanges();
                    return;
                }

                Contena.Utils.debug.warn(
                    'Discard detail page changes',
                    `Could not discard changes for entity with name "${name}".`,
                );
            },
        );

        Contena.Store.get('error').resetApiErrors();
    }

    watch(() => route.params.id, discardChanges);
    if (inject(matchedRouteKey, null)) {
        onBeforeRouteLeave(() => {
            discardChanges();
        });
    }

    return { discardChanges };
}
