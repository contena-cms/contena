import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, reactive } from 'vue';

import type { RouteLocationNormalizedLoaded } from 'vue-router';

import { useDiscardDetailPageChanges } from './use-discard-detail-page-changes';

describe('useDiscardDetailPageChanges', () => {
    it('discards the entity when the route id changes', async () => {
        const discardChanges = jest.fn();
        const resetApiErrors = jest.spyOn(Contena.Store.get('error'), 'resetApiErrors');
        const route = reactive({ params: { id: 'one' } }) as unknown as RouteLocationNormalizedLoaded;

        mount(
            defineComponent({
                setup() {
                    useDiscardDetailPageChanges(route, { entity: () => ({ discardChanges }) });
                    return {};
                },
                template: '<div />',
            }),
        );

        route.params.id = 'two';
        await nextTick();

        expect(discardChanges).toHaveBeenCalledTimes(1);
        expect(resetApiErrors).toHaveBeenCalledTimes(1);
    });
});
