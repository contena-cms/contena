/**
 *
 * @private
 * @description Wrapper which removes class inheritance in Vue. You need to remove the class
 * in the parent component like this: "this.$refs.swIgnoreClass.$el.className = '';". Additionally
 * you need to get the old class values before:
 * ```
 * const classes = {}; // contains the classes from the ct-ignore-class commponent
 * const staticClasses = (this.$vnode.data.staticClass ?? '').split(' ');
 *
 * // add attrs classes to main card
 * staticClasses.forEach((className) => {
 *     classes[className] = true;
 * });
 * ```
 *
 * You can add these classes to the main, child component. A full example can be found in
 * the `ct-card` component.
 *
 * @status ready
 * @example-type static
 * @component-example
 * <ct-ignore-class ref="swIgnoreClass">
 *     Your normal content
 * </ct-ignore-class>
 */
export default {
    template: '<div><slot></slot></div>',
};
