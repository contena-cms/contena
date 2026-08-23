import { watch, type WatchSource } from 'vue';

interface ApiErrorAttribute {
    selfLink?: string;
}

/**
 * Removes a field's previous API error as soon as its bound value changes.
 *
 * @private
 */
export function useRemoveApiError(source: WatchSource<unknown>, getError: () => ApiErrorAttribute | undefined): void {
    watch(source, () => {
        const selfLink = getError()?.selfLink;

        if (selfLink) {
            void Contena.Store.get('error').removeApiError(selfLink);
        }
    });
}
