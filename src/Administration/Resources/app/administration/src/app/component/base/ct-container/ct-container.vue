<template>
    <ct-block name="ct_container">
        <div class="ct-container" :style="currentCssGrid">
            <slot>
                <ct-block name="ct_container_slot_default"></ct-block>
            </slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-container.scss';
const { warn } = Contena.Utils.debug;

const props = defineProps({
    columns: {
        type: String,
        default: '',
        required: false,
    },
    rows: {
        type: String,
        default: '',
        required: false,
    },
    gap: {
        type: String,
        default: '',
        required: false,
    },
    justify: {
        type: String,
        required: false,
        default: 'stretch',
        validValues: [
            'start',
            'end',
            'center',
            'stretch',
            'left',
            'right',
        ],
        validator(value) {
            return [
                'start',
                'end',
                'center',
                'stretch',
                'left',
                'right',
            ].includes(value);
        },
    },
    align: {
        type: String,
        required: false,
        default: 'stretch',
        validValues: [
            'start',
            'end',
            'center',
            'stretch',
        ],
        validator(value) {
            return [
                'start',
                'end',
                'center',
                'stretch',
            ].includes(value);
        },
    },
    breakpoints: {
        type: Object,
        default() {
            return {};
        },
        required: false,
    },
});

import { getCurrentInstance } from 'src/app/adapter/composition-extension-system';
import { ref } from 'vue';

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const currentCssGrid = ref({});

const createdComponent = () => {
    registerResizeListener();
    updateCssGrid();
};
function registerResizeListener() {
    device?.onResize({
        listener: updateCssGrid,
        component: instance?.proxy,
    });
}
function updateCssGrid() {
    currentCssGrid.value = buildCssGrid();
}
function buildCssGrid() {
    let cssGrid = buildCssGridProps();
    Object.keys(props.breakpoints).find((breakpoint) => {
        const currentBreakpointWidth = Number.parseInt(breakpoint, 10);
        const currentBreakpoint = props.breakpoints[breakpoint];
        if (Number.isNaN(currentBreakpointWidth)) {
            warn(
                'ct-container',
                `Unable to register breakpoint "${breakpoint}". The breakpoint key has to be a number.`,
                currentBreakpoint,
            );
        }
        if (currentBreakpointWidth > (device?.getViewportWidth() ?? Number.POSITIVE_INFINITY)) {
            cssGrid = buildCssGridProps(currentBreakpoint);
            return true;
        }
        return false;
    });
    return cssGrid;
}
const cssGridDefaults = () => {
    return {
        columns: props.columns,
        rows: props.rows,
        gap: props.gap,
        justify: props.justify,
        align: props.align,
    };
};
function buildCssGridProps(currentBreakpoint = {}) {
    const grid = Object.assign(cssGridDefaults(), currentBreakpoint);
    return {
        'grid-template-columns': grid.columns,
        'grid-template-rows': grid.rows,
        'grid-gap': grid.gap,
        'justify-items': grid.justify,
        'align-items': grid.align,
    };
}

createdComponent();

ctDefinePublic({
    currentCssGrid,
    createdComponent,
    registerResizeListener,
    updateCssGrid,
    buildCssGrid,
    cssGridDefaults,
    buildCssGridProps,
});

defineExpose({
    currentCssGrid,
    createdComponent,
    registerResizeListener,
    updateCssGrid,
    buildCssGrid,
    cssGridDefaults,
    buildCssGridProps,
});
</script>
