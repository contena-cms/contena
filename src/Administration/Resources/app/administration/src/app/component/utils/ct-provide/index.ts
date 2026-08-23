import { computed, provide } from 'vue';

/**
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    template: '<slot />',
    inheritAttrs: false,
    setup(_props, { attrs }) {
        Object.keys(attrs).forEach((key) =>
            provide(
                Contena.Utils.string.camelCase(key),
                computed(() => attrs[key]),
            ),
        );
        return {};
    },
});
