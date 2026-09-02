import type { SetupContext } from 'vue';

declare global {
    /**
     * Contena setup compile-time macro for base components.
     *
     * Use this in `<script setup>` to declare which setup
     * bindings are public and may be replaced by component overrides. The macro
     * is removed by the Contena setup transform and is never called at runtime.
     *
     * This macro is rejected in override components. Overrides must use
     * `ctDefineOverride()` to declare replacement bindings instead.
     */
    function ctDefinePublic<TPublic extends Record<PropertyKey, unknown>>(bindings: TPublic): void;

    /**
     * Contena setup compile-time macro for override components.
     *
     * Use this in `<script setup>` to declare which public base
     * component bindings are replaced by this override. The macro is removed by
     * the Contena setup transform and is never called at runtime.
     *
     * This macro is rejected in base components. Base components must use
     * `ctDefinePublic()` to expose overrideable setup bindings instead.
     */
    function ctDefineOverride<TOverride extends Record<PropertyKey, unknown>>(bindings: TOverride): void;

    /**
     * Contena setup helper for override components.
     *
     * Returns the previous public setup state passed to the generated
     * `overrideComponentSetup()` callback. This helper is injected by the
     * transform and is only valid in override components.
     */
    function useCtPreviousState<
        TPreviousState extends Record<PropertyKey, any> = Record<PropertyKey, any>,
    >(): TPreviousState;

    /**
     * Contena setup helper for the current component props.
     *
     * Prefer Vue's `defineProps()` in new base components when possible. This
     * helper remains available for existing Contena setup code and is replaced
     * by the transform with the generated setup props object.
     */
    function useCtProps<TProps extends Record<PropertyKey, any> = Record<PropertyKey, any>>(): TProps;

    /**
     * Contena setup helper for the current Vue setup context.
     *
     * The helper is injected by the transform and resolves to the generated
     * setup context object.
     */
    function useCtContext<TContext = SetupContext>(): TContext;
}

export {};
