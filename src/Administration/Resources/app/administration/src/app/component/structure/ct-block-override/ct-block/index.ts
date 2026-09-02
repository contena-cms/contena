/**
 *
 */
import {
    cloneVNode,
    computed,
    getCurrentInstance,
    onBeforeUnmount,
    provide,
    ref,
    type ComponentInternalInstance,
    type Slot,
} from 'vue';
import parentsInjectionKey from './parents-injection-key';
import useBlockContext from '../../../../composables/use-block-context';

/**
 * @private
 *
 * @component ct-block
 * @description
 * The `ct-block` component is designed to create an extension point where its content can be overridden or
 * extended. It will render the provided content based on the provided block name, using a context-aware approach
 * to retrieve and  apply the appropriate blocks.
 *
 * To make the `ct-block` component to override or extend content of a specific block it is necessary to provide the
 * block name to override and the `extends` attribute. The `ct-block-parent` component is used to render the parent
 * block default content.
 *
 * The prop `data` is used to pass data to the block content. The `$dataScope` is used to pass the entire component
 * scoped data to the block content.
 *
 * @example override
 * <ct-block name="ct_block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </ct-block-extension>
 *
 * <ct-block extends="ct_block-name">
 *     <div>Block content override</div>
 * </ct-block>
 *
 * @example extend
 * <ct-block name="ct_block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </ct-block>
 *
 * <ct-block extends="ct_block-name">
 *     <ct-block-parent>
 *     <div>Block content extension</div>
 * </ct-block>
 *
 * @example extend with multiple blocks
 * <ct-block name="ct_block-name" :data="$dataScope">
 *     <div>Default content</div>
 * </ct-block>
 *
 * <ct-block extends="ct_block-name">
 *     <ct-block-parent>
 *     <div>Block content extension</div>
 * </ct-block>
 *
 * <ct-block extends="ct_block-name">
 *     <ct-block-parent>
 *     <div>Another block content extension</div>
 * </ct-block>
 */
export default Contena.Component.wrapComponentConfig({
    inheritAttrs: false,

    props: {
        name: {
            type: String,
        },
        extends: {
            type: String,
        },
        data: {
            type: Object as PropType<ComponentInternalInstance['proxy']>,
            default: null,
        },
    },
    setup(props, { slots }) {
        const { addBlock, removeBlock, getBlocks } = useBlockContext();
        const instance = getCurrentInstance();

        if (props.extends) {
            // addBlock is a no-op for undefined, so an explicit guard is not needed.
            addBlock(props.extends, slots.default);

            onBeforeUnmount(() => {
                if (props.extends) {
                    removeBlock(props.extends, slots.default);
                }
            });

            return { template: null };
        }

        const providedParents = ref<ReturnType<Slot>[]>([]);
        provide(parentsInjectionKey, providedParents);

        const template = computed(() => {
            if (!props.name) {
                throw new Error('[ct-block] The "name" prop is required when "extends" is not set.');
            }

            const nativeBlocks = getBlocks(props.name);
            const blocksAndParent = [
                slots.default ?? (() => []),
                ...nativeBlocks,
            ];
            const blockDataScope = props.data ?? instance?.parent?.proxy ?? {};
            const blocksNodes = blocksAndParent.map((block) => block?.(blockDataScope));

            const lastNode = blocksNodes.pop();
            // Each <ct-block-parent /> calls .pop() exactly once in its own setup()
            // to claim its parent slot. The array must be reset to the current render's
            // ordered list so that each parent instance pops the correct slot — not a
            // stale or accumulated list from a previous render cycle.
            providedParents.value = blocksNodes;
            return lastNode;
        });

        return {
            template,
        };
    },
    render() {
        if (!Array.isArray(this.template) || this.template.length !== 1 || Object.keys(this.$attrs).length === 0) {
            return this.template;
        }

        return cloneVNode(this.template[0], this.$attrs);
    },
});
