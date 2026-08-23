import { computed, toValue, watchEffect, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

type PageTitlePart = MaybeRefOrGetter<string | null | undefined>;

/** @private */
export function usePageTitle(identifier: PageTitlePart = null, ...additionalParts: PageTitlePart[]): ComputedRef<string> {
    const route = useRoute();
    const { t } = useI18n();
    const title = computed(() => {
        const routeModule = route.meta?.$module as { title?: string } | undefined;

        if (!routeModule?.title) {
            return '';
        }

        const parts = [
            t('global.ct-admin-menu.textContenaAdmin'),
            t(routeModule.title),
            toValue(identifier),
            ...additionalParts.map((part) => toValue(part)),
        ].filter((part): part is string => typeof part === 'string' && part.trim() !== '');

        return parts.reverse().join(' | ');
    });

    watchEffect(() => {
        document.title = title.value;
    });

    return title;
}
