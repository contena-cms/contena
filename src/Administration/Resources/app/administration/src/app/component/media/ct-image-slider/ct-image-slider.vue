<template>
    <ct-block name="sw_image_slider">
        <div v-if="images.length > 0" class="ct-image-slider" :style="wrapperStyles">
            <ct-block name="sw_image_slider_image_container">
                <div class="ct-image-slider__image-container" :style="containerStyles">
                    <ct-block name="sw_image_slider_image_container_scrollable">
                        <div class="ct-image-slider__image-scrollable" :style="scrollableContainerStyles">
                            <ct-block name="sw_image_slider_images">
                                <div
                                    v-for="(image, index) in images"
                                    :key="index"
                                    class="ct-image-slider__element-wrapper"
                                    :aria-hidden="isHiddenItem(index) || undefined"
                                    :style="componentStyles"
                                >
                                    <ct-block name="sw_image_slider_image_images_container">
                                        <div
                                            class="ct-image-slider__element-container"
                                            :class="elementClasses(index)"
                                            :style="elementStyles(image, index)"
                                            role="button"
                                            tabindex="0"
                                            @click="onSetCurrentItem(index)"
                                            @keydown.enter="onSetCurrentItem(index)"
                                        >
                                            <ct-block name="sw_image_slider_image">
                                                <img
                                                    class="ct-image-slider__element-image"
                                                    :class="imageClasses(index)"
                                                    :style="[borderStyles(image), imageStyles]"
                                                    :src="getImage(image)"
                                                    :alt="imageAlt(index)"
                                                />
                                            </ct-block>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_image_slider_element_description">
                                        <div
                                            v-if="hasValidDescription(image)"
                                            class="ct-image-slider__element-description"
                                            :style="componentStyles"
                                        >
                                            {{ image.description }}
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_image_slider_image_container_arrows">
                        <template v-if="showArrows">
                            <ct-block name="sw_image_slider_image_container_arrow_left">
                                <div
                                    class="ct-image-slider__arrow arrow-left"
                                    role="button"
                                    tabindex="0"
                                    @click="goToPreviousImage"
                                    @keydown.enter="goToPreviousImage"
                                >
                                    <mt-icon name="regular-chevron-left" />
                                </div>
                            </ct-block>

                            <ct-block name="sw_image_slider_image_container_arrow_right">
                                <div
                                    class="ct-image-slider__arrow arrow-right"
                                    role="button"
                                    tabindex="0"
                                    @click="goToNextImage"
                                    @keydown.enter="goToNextImage"
                                >
                                    <mt-icon name="regular-chevron-right" />
                                </div>
                            </ct-block>
                        </template>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_image_slider_button_container">
                <div v-if="showButtons" class="ct-image-slider__buttons" :class="buttonClasses">
                    <ct-block name="sw_image_slider_buttons">
                        <button
                            v-for="(item, index) in buttonList"
                            :key="index"
                            class="ct-image-slider__buttons-element"
                            :class="{ 'is--active': index === currentPageNumber }"
                            @click="setCurrentPageNumber(index)"
                        >
                            {{ imageAlt(index) }}
                        </button>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-image-slider.scss';
const { Filter } = Contena;

const props = defineProps({
    images: {
        type: Array,
        required: true,
    },

    canvasWidth: {
        type: Number,
        required: false,
        default: 0,
        validator(value) {
            return value >= 0;
        },
    },

    canvasHeight: {
        type: Number,
        required: false,
        default: 0,
        validator(value) {
            return value >= 0;
        },
    },

    gap: {
        type: Number,
        required: false,
        default: 20,
        validator(value) {
            return value >= 0;
        },
    },

    elementPadding: {
        type: Number,
        required: false,
        default: 0,
        validator(value) {
            return value >= 0;
        },
    },

    navigationType: {
        type: String,
        required: false,
        default: 'arrow',
        validator(value) {
            return [
                'arrow',
                'button',
                'all',
            ].includes(value);
        },
    },

    enableDescriptions: {
        type: Boolean,
        required: false,
        default: false,
    },

    overflow: {
        type: String,
        required: false,
        default: 'hidden',
        validator(value) {
            return [
                'hidden',
                'visible',
            ].includes(value);
        },
    },

    rewind: {
        type: Boolean,
        required: false,
        default: false,
    },

    bordered: {
        type: Boolean,
        required: false,
        default: true,
    },

    rounded: {
        type: Boolean,
        required: false,
        default: true,
    },

    autoWidth: {
        type: Boolean,
        required: false,
        default: false,
    },

    itemPerPage: {
        type: Number,
        required: false,
        default: 1,
    },

    initialIndex: {
        type: Number,
        required: false,
        default: 0,
    },

    arrowStyle: {
        type: String,
        required: false,
        default: 'inside',
        validator(value) {
            return [
                'inside',
                'outside',
                'none',
            ].includes(value);
        },
    },

    buttonStyle: {
        type: String,
        required: false,
        default: 'outside',
        validator(value) {
            return [
                'inside',
                'outside',
                'none',
            ].includes(value);
        },
    },

    displayMode: {
        type: String,
        required: false,
        default: 'cover',
        validator(value) {
            return [
                'contain',
                'cover',
                'none',
            ].includes(value);
        },
    },
});
const emit = defineEmits(['image-change']);

import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const currentPageNumber = ref(0);
const currentItemIndex = ref(0);

const totalPage = computed(() => {
    return Math.ceil(props.images.length / props.itemPerPage);
});
const remainder = computed(() => {
    return props.images.length % props.itemPerPage;
});
const buttonList = computed(() => {
    if (props.itemPerPage === 1) {
        return props.images;
    }

    return props.images.filter((image, index) => {
        return index % props.itemPerPage === 0;
    });
});
const wrapperStyles = computed(() => {
    return {
        width: props.canvasWidth ? `${props.canvasWidth}px` : '100%',
    };
});
const componentStyles = computed(() => {
    return {
        width: props.autoWidth ? 'auto' : `${100 / props.images.length}%`,
    };
});
const containerStyles = computed(() => {
    const offset = props.arrowStyle === 'outside' ? 112 : 0;
    const width = props.canvasWidth ? `${props.canvasWidth - offset}px` : `calc(100% - ${offset}px)`;

    return {
        width,
        overflowX: props.overflow,
        margin: props.arrowStyle === 'outside' ? '0 56px' : 0,
    };
});
const scrollableContainerStyles = computed(() => {
    if (props.itemPerPage === 1 || remainder.value === 0 || props.images.length <= props.itemPerPage) {
        return {
            width: `${totalPage.value * 100}%`,
            gap: `${props.gap}px`,
            transform: `translateX(-${(currentPageNumber.value / totalPage.value) * 100}%)`,
        };
    }

    const itemWidth = 100 / props.images.length;
    const translateAmount =
        currentPageNumber.value === totalPage.value - 1
            ? ((currentPageNumber.value - 1) * props.itemPerPage + remainder.value) * itemWidth
            : currentPageNumber.value * props.itemPerPage * itemWidth;

    return {
        width: `${(totalPage.value - 1 + remainder.value / props.itemPerPage) * 100}%`,
        gap: `${props.gap}px`,
        transform: `translateX(-${translateAmount}%)`,
    };
});
const imageStyles = computed(() => {
    return {
        objectFit: props.displayMode,
    };
});
const buttonClasses = computed(() => {
    return { 'is--button-inside': props.buttonStyle === 'inside' };
});
const showButtons = computed(() => {
    return (
        props.images.length >= 2 &&
        props.images.length > props.itemPerPage &&
        [
            'button',
            'all',
        ].includes(props.navigationType)
    );
});
const showArrows = computed(() => {
    return (
        props.images.length > props.itemPerPage &&
        [
            'arrow',
            'all',
        ].includes(props.navigationType)
    );
});

const setCurrentPageNumber = (pageNumber) => {
    currentPageNumber.value = pageNumber;
};
const isImageObject = (image) => {
    return typeof image === 'object';
};
const hasValidDescription = (image) => {
    return (
        props.enableDescriptions &&
        isImageObject(image) &&
        image.hasOwnProperty('description') &&
        image.description.length >= 1
    );
};
const getImage = (image) => {
    const link = isImageObject(image) ? image.src : image;

    try {
        new URL(link);
    } catch (_e) {
        return Filter.getByName('asset')(link);
    }

    return link;
};
const imageAlt = (index) => {
    return t(
        'ct-image-slider.imageAlt',
        {
            index: index + 1,
            total: props.images.length,
        },
        0,
    );
};
const goToPreviousImage = () => {
    currentPageNumber.value =
        props.rewind && currentPageNumber.value === 0 ? totalPage.value - 1 : Math.max(currentPageNumber.value - 1, 0);

    if (props.itemPerPage === 1) {
        currentItemIndex.value = currentPageNumber.value;
        emit('image-change', currentPageNumber.value);
    }
};
const goToNextImage = () => {
    currentPageNumber.value =
        props.rewind && currentPageNumber.value === totalPage.value - 1
            ? 0
            : Math.min(currentPageNumber.value + 1, totalPage.value - 1);

    if (props.itemPerPage === 1) {
        currentItemIndex.value = currentPageNumber.value;
        emit('image-change', currentPageNumber.value);
    }
};
const elementClasses = (index) => {
    return [
        {
            'is--active': index === currentItemIndex.value && props.itemPerPage > 1,
        },
        { 'is--bordered': props.bordered },
        { 'is--rounded': props.rounded },
    ];
};
const elementStyles = (image, index) => {
    return {
        cursor: index === currentItemIndex.value ? 'default' : 'pointer',
        height: props.canvasHeight ? `${props.canvasHeight}px` : '100%',
        padding: props.elementPadding ? `${props.elementPadding}px` : 0,
        ...borderStyles(image),
    };
};
const imageClasses = (index) => {
    return {
        'is--active': index === currentItemIndex.value,
        'is--auto-width': props.autoWidth,
    };
};
function borderStyles(image) {
    if (!hasValidDescription(image)) {
        return {};
    }
    return {
        borderBottomLeftRadius: 0,
        borderBottomRightRadius: 0,
    };
}
const onSetCurrentItem = (index) => {
    if (index === currentItemIndex.value) {
        return;
    }

    currentPageNumber.value = Math.floor(index / props.itemPerPage);
    currentItemIndex.value = index;
    emit('image-change', index);
};
const isHiddenItem = (index) => {
    if (props.itemPerPage === 1) {
        return index !== currentItemIndex.value;
    }

    if (currentPageNumber.value === totalPage.value - 1) {
        return index < props.images.length - props.itemPerPage;
    }

    return currentPageNumber.value * props.itemPerPage > index || index >= (currentPageNumber.value + 1) * props.itemPerPage;
};

watch(
    () => props.initialIndex,
    (value) => {
        onSetCurrentItem(value);
    },
    { immediate: true },
);

swDefinePublic({
    currentPageNumber,
    currentItemIndex,
    totalPage,
    remainder,
    buttonList,
    wrapperStyles,
    componentStyles,
    containerStyles,
    scrollableContainerStyles,
    imageStyles,
    buttonClasses,
    showButtons,
    showArrows,
    setCurrentPageNumber,
    isImageObject,
    hasValidDescription,
    getImage,
    imageAlt,
    goToPreviousImage,
    goToNextImage,
    elementClasses,
    elementStyles,
    imageClasses,
    borderStyles,
    onSetCurrentItem,
    isHiddenItem,
});

defineExpose({
    currentPageNumber,
    currentItemIndex,
    totalPage,
    remainder,
    buttonList,
    wrapperStyles,
    componentStyles,
    containerStyles,
    scrollableContainerStyles,
    imageStyles,
    buttonClasses,
    showButtons,
    showArrows,
    setCurrentPageNumber,
    isImageObject,
    hasValidDescription,
    getImage,
    imageAlt,
    goToPreviousImage,
    goToNextImage,
    elementClasses,
    elementStyles,
    imageClasses,
    borderStyles,
    onSetCurrentItem,
    isHiddenItem,
});
</script>
