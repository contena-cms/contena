<template>
    <ct-block name="ct_select_result_list">
        <div ref="resultListRoot" class="ct-select-result-list">
            <mt-floating-ui
                :class="['ct-select-result-list-popover', popoverClass]"
                :is-opened="true"
                :match-reference-width="popoverResizeWidth"
            >
                <div
                    ref="popoverContent"
                    class="ct-select-result-list__content"
                    :class="{ 'ct-select-result-list__content_empty': isLoading && (!options || options.length <= 0) }"
                    @scroll="onScroll"
                >
                    <slot name="before-item-list"></slot>

                    <ul class="ct-select-result-list__item-list">
                        <template v-for="(item, index) in options">
                            <slot name="result-item" v-bind="{ item, index }"></slot>
                        </template>
                    </ul>

                    <slot name="after-item-list"></slot>

                    <div v-if="!isLoading && options && options.length < 1" class="ct-select-result-list__empty">
                        <ct-block name="ct_select_result_list_empty_icon">
                            <mt-icon
                                name="regular-search"
                                size="var(--scale-size-16)"
                                color="var(--color-icon-primary-default)"
                            />
                        </ct-block>

                        <ct-block name="ct_select_result_list_empty_text">
                            {{ emptyMessageText }}
                        </ct-block>
                    </div>
                </div>
            </mt-floating-ui>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-select-result-list.scss';

const props = defineProps({
    options: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },

    emptyMessage: {
        type: String,
        required: false,
        default: null,
    },

    focusEl: {
        type: [
            HTMLDocument,
            HTMLElement,
        ],
        required: false,
        default() {
            return document;
        },
    },

    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },

    popoverClasses: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },

    popoverResizeWidth: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits([
    'item-select',
    'active-item-change',
    'outside-click',
    'paginate',
    'item-select-by-keyboard',
]);

import { ref, computed, inject, provide, unref, nextTick, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const popoverContent = ref(null);
const resultListRoot = ref(null);
const selectBaseRoot = inject('selectBaseRoot', ref(null));

const feature = inject('feature');

const activeItemIndex = ref(-1);

const emptyMessageText = computed(() => {
    return props.emptyMessage || t('global.ct-select-result-list.messageNoResults');
});
const popoverClass = computed(() => {
    return [
        ...props.popoverClasses,
        'ct-select-result-list-popover-wrapper',
    ];
});

const createdComponent = () => {
    addEventListeners();
};
const beforeDestroyedComponent = () => {
    removeEventListeners();
};
const setActiveItemIndex = (index) => {
    activeItemIndex.value = index;
    emitActiveItemIndex();
};
function addEventListeners() {
    document.addEventListener('keydown', navigate);
    document.addEventListener('click', checkOutsideClick);
    Contena.Utils.EventBus.on('item-select', onItemSelect);
}
function removeEventListeners() {
    document.removeEventListener('keydown', navigate);
    document.removeEventListener('click', checkOutsideClick);
    Contena.Utils.EventBus.off('item-select', onItemSelect);
}
function onItemSelect(item) {
    emit('item-select', item);
}
function emitActiveItemIndex(shouldFocus = false) {
    emit('active-item-change', activeItemIndex.value, {
        shouldFocus,
    });
    Contena.Utils.EventBus.emit('active-item-change', activeItemIndex.value, {
        shouldFocus,
    });
}
function checkOutsideClick(event) {
    event.stopPropagation();
    const popoverContentClicked = popoverContent.value?.contains(event.target);
    const componentClicked = resultListRoot.value?.contains(event.target);
    const parentClicked = selectBaseRoot.value?.contains(event.target);
    if (popoverContentClicked || componentClicked || parentClicked) {
        return;
    }
    emit('outside-click');
}
function navigate({ key }) {
    key = key.toUpperCase();
    if (key === 'ARROWDOWN') {
        navigateNext();
        return;
    }
    if (key === 'ARROWUP') {
        navigatePrevious();
        return;
    }
    if (key === 'ENTER') {
        emitClicked();
    }
}
function navigateNext() {
    if (activeItemIndex.value >= props.options.length - 1) {
        emit('paginate');
        return;
    }
    activeItemIndex.value += 1;
    emitActiveItemIndex({
        shouldFocus: true,
    });
    updateScrollPosition();
}
function navigatePrevious() {
    if (activeItemIndex.value === -1) {
        // Set the active item to the last item in the list
        activeItemIndex.value = props.options.length - 1;
    } else if (activeItemIndex.value > 0) {
        activeItemIndex.value -= 1;
    }
    emitActiveItemIndex({
        shouldFocus: true,
    });
    updateScrollPosition();
}
function updateScrollPosition() {
    // wait until the new active item is rendered and has the active class
    void nextTick(() => {
        const resultContainer = document.querySelector('.ct-select-result-list__content');
        const activeItem = resultContainer.querySelector('.is--active');
        const itemHeight = activeItem.offsetHeight;
        const activeItemPosition = activeItem.offsetTop;
        const actualScrollTop = resultContainer.scrollTop;
        if (activeItemPosition === 0) {
            return;
        }

        // Check if we need to scroll down
        if (resultContainer.offsetHeight + actualScrollTop < activeItemPosition + itemHeight) {
            resultContainer.scrollTop += itemHeight;
        }

        // Check if we need to scroll up
        if (actualScrollTop !== 0 && activeItemPosition - actualScrollTop - itemHeight <= 0) {
            resultContainer.scrollTop -= itemHeight;
        }
    });
}
function emitClicked() {
    // This emit is subscribed in the ct-result component. They can for example be disabled and need
    // choose on their own if they are selected
    emit('item-select-by-keyboard', activeItemIndex.value);
    Contena.Utils.EventBus.emit('item-select-by-keyboard', activeItemIndex.value);
}
const onScroll = (event) => {
    if (Math.floor(getBottomDistance(event.target)) > 0) {
        return;
    }

    emit('paginate');
};
function getBottomDistance(element) {
    return element.scrollHeight - element.clientHeight - element.scrollTop;
}

createdComponent();

onBeforeUnmount(() => {
    beforeDestroyedComponent();
});

ctDefinePublic({
    feature,
    activeItemIndex,
    emptyMessageText,
    popoverClass,
    createdComponent,
    beforeDestroyedComponent,
    setActiveItemIndex,
    addEventListeners,
    removeEventListeners,
    onItemSelect,
    emitActiveItemIndex,
    checkOutsideClick,
    navigate,
    navigateNext,
    navigatePrevious,
    updateScrollPosition,
    emitClicked,
    onScroll,
    getBottomDistance,
});

provide('setActiveItemIndex', unref(setActiveItemIndex));

defineExpose({
    feature,
    activeItemIndex,
    emptyMessageText,
    popoverClass,
    createdComponent,
    beforeDestroyedComponent,
    setActiveItemIndex,
    addEventListeners,
    removeEventListeners,
    onItemSelect,
    emitActiveItemIndex,
    checkOutsideClick,
    navigate,
    navigateNext,
    navigatePrevious,
    updateScrollPosition,
    emitClicked,
    onScroll,
    getBottomDistance,
});
</script>
