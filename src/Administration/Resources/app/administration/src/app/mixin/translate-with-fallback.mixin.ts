import { defineComponent } from 'vue';

/**
 * @private
 *
 * Resolves a snippet key with the current locale first and falls back to
 * `Contena.Context.app.fallbackLocale` when the key is missing.
 *
 * Vue-i18n v10's `$te` only checks the active locale, so translations that
 * live only in the fallback locale (typically `en-GB`) would otherwise leak
 * the raw key to the UI.
 */
export default Contena.Mixin.register(
    'translate-with-fallback',
    defineComponent({
        methods: {
            tWithFallback(key: string): string {
                if (!key) {
                    return '';
                }

                if (this.$te(key)) {
                    return this.$t(key);
                }

                const fallbackLocale = Contena.Context.app.fallbackLocale;
                if (fallbackLocale && this.$te(key, fallbackLocale)) {
                    return this.$t(key, fallbackLocale);
                }

                return key;
            },
        },
    }),
);
