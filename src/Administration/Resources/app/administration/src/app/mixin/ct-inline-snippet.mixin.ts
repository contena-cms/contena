import { defineComponent } from 'vue';

/**
 * @private
 */
export default Contena.Mixin.register(
    'ct-inline-snippet',
    defineComponent({
        computed: {
            ctInlineSnippetLocale(): string {
                return Contena.Store.get('session').currentLocale as unknown as string;
            },

            ctInlineSnippetFallbackLocale(): string {
                return Contena.Context.app.fallbackLocale as unknown as string;
            },
        },

        methods: {
            getInlineSnippet(value: { [key: string]: string }) {
                if (Contena.Utils.types.isEmpty(value)) {
                    return '';
                }
                if (value[this.ctInlineSnippetLocale]) {
                    return value[this.ctInlineSnippetLocale];
                }
                if (value[this.ctInlineSnippetFallbackLocale]) {
                    return value[this.ctInlineSnippetFallbackLocale];
                }
                if (Contena.Utils.types.isObject(value)) {
                    const locale = Object.keys(value).find((key) => {
                        return value[key] !== '';
                    });

                    if (locale !== undefined) {
                        return value[locale];
                    }
                }

                return value;
            },
        },
    }),
);
