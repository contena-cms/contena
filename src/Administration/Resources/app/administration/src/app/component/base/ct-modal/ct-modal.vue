<template>
    <ct-block name="ct_modal">
        <ct-block name="ct_modal_element">
            <transition name="ct-modal-fade" v-bind="$attrs" appear>
                <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
                <div
                    ref="modalRoot"
                    class="ct-modal"
                    :class="modalClasses"
                    @mousedown="closeModalOnClickOutside"
                    @keyup.esc="closeModalOnEscapeKey"
                >
                    <ct-block name="ct_modal_dialog">
                        <div
                            ref="dialog"
                            class="ct-modal__dialog"
                            :class="modalDialogClasses"
                            :style="{ maxWidth: size }"
                            role="dialog"
                            aria-labelledby="modalTitleEl"
                            tabindex="-1"
                        >
                            <ct-block name="ct_modal_header">
                                <header v-if="showHeader" class="ct-modal__header">
                                    <slot name="modal-header">
                                        <ct-block name="ct_modal_slot_header">
                                            <div class="ct-modal__titles">
                                                <slot name="modal-title">
                                                    <ct-block name="ct_modal_close">
                                                        <h4 id="modalTitleEl" class="ct-modal__title">
                                                            {{ title }}
                                                        </h4>
                                                    </ct-block>
                                                </slot>

                                                <h5 v-if="subtitle" class="ct-modal__subtitle">
                                                    {{ subtitle }}
                                                </h5>
                                            </div>

                                            <ct-block name="ct_modal_close_button">
                                                <button
                                                    v-if="closable"
                                                    class="ct-modal__close"
                                                    :title="$t('global.default.close')"
                                                    :aria-label="$t('global.default.close')"
                                                    @click.prevent="closeModal"
                                                >
                                                    <ct-block name="ct_modal_close_icon">
                                                        <mt-icon name="regular-times-s" />
                                                    </ct-block>
                                                </button>
                                            </ct-block>
                                        </ct-block>
                                    </slot>
                                </header>
                            </ct-block>

                            <ct-block name="ct_modal_body">
                                <slot name="body">
                                    <div class="ct-modal__body" :class="modalBodyClasses">
                                        <ct-block name="ct_modal_loader">
                                            <slot name="modal-loader">
                                                <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                                                <mt-loader v-if="isLoading" />
                                            </slot>
                                        </ct-block>
                                        <slot>
                                            <ct-block name="ct_modal_slot_default"></ct-block>
                                        </slot>
                                    </div>
                                </slot>
                            </ct-block>

                            <ct-block name="ct_modal_footer">
                                <footer v-if="showFooter && hasFooterSlot" class="ct-modal__footer">
                                    <slot name="modal-footer">
                                        <ct-block name="ct_modal_slot_footer"></ct-block>
                                    </slot>
                                </footer>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </transition>
        </ct-block>
    </ct-block>
</template>

<script setup>
import './ct-modal.scss';
const utils = Contena.Utils;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    title: {
        type: String,
        default: '',
    },

    subtitle: {
        type: String,
        default: null,
        required: false,
    },

    size: {
        type: String,
        default: '',
    },

    variant: {
        type: String,
        required: false,
        default: 'default',
        validValues: [
            'default',
            'small',
            'large',
            'full',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'default',
                'small',
                'large',
                'full',
            ].includes(value);
        },
    },

    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },

    selector: {
        type: String,
        required: false,
        default: 'body',
    },

    showHeader: {
        type: Boolean,
        required: false,
        default: true,
    },

    showFooter: {
        type: Boolean,
        required: false,
        default: true,
    },

    closable: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits(['modal-close']);

import { ref, computed, useSlots, onMounted, onBeforeUnmount } from 'vue';

const slots = useSlots();

const dialog = ref(null);
const modalRoot = ref(null);

const id = ref(utils.createId());

const modalClasses = computed(() => {
    return {
        [`ct-modal--${props.variant}`]: props.variant && !props.size,
        'ct-modal--has-sidebar': showHelpSidebar.value,
    };
});
const modalDialogClasses = computed(() => {
    return [
        `ct-modal--${id.value}`,
        { 'has--header': props.showHeader },
    ];
});
const modalBodyClasses = computed(() => {
    return {
        'has--no-footer': !props.showFooter,
    };
});
const hasFooterSlot = computed(() => {
    return !!slots['modal-footer'];
});
const showHelpSidebar = computed(() => {
    return Contena.Store.get('adminHelpCenter').showHelpSidebar;
});

const mountedComponent = () => {
    const targetEl = document.querySelector(props.selector);
    targetEl.appendChild(modalRoot.value);

    setFocusToModal();
};
const beforeDestroyComponent = () => {
    const modalElement = modalRoot.value;

    window.setTimeout(() => {
        modalElement?.remove();
    }, 400); // use timeout to wait for modal leave transition
};
function setFocusToModal() {
    dialog.value?.focus();
}
const closeModalOnClickOutside = (domEvent) => {
    if (!props.closable) {
        return;
    }

    if (!dialog.value || !dialog.value.contains(domEvent.target)) {
        closeModal();
    }
};
function closeModal() {
    emit('modal-close');
}
const closeModalOnEscapeKey = (event) => {
    if (!event.target.classList.contains('ct-modal__dialog') || event.target !== document.activeElement) {
        return;
    }

    if (!props.closable) {
        return;
    }

    if (event.key === 'Escape' || event.keyCode === 27) {
        closeModal();
    }
};

onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeDestroyComponent();
});

ctDefinePublic({
    id,
    modalClasses,
    modalDialogClasses,
    modalBodyClasses,
    hasFooterSlot,
    showHelpSidebar,
    mountedComponent,
    beforeDestroyComponent,
    setFocusToModal,
    closeModalOnClickOutside,
    closeModal,
    closeModalOnEscapeKey,
});

defineExpose({
    id,
    modalClasses,
    modalDialogClasses,
    modalBodyClasses,
    hasFooterSlot,
    showHelpSidebar,
    mountedComponent,
    beforeDestroyComponent,
    setFocusToModal,
    closeModalOnClickOutside,
    closeModal,
    closeModalOnEscapeKey,
});
</script>
