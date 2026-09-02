<template>
    <ct-block name="ct_data_grid_skeleton">
        <tbody class="ct-data-grid-skeleton" aria-busy="true">
            <ct-block name="ct_data_grid_skeleton_row">
                <tr
                    v-for="(item, itemIndex) in itemAmount"
                    :key="`grid-skeleton-itme-${itemIndex}`"
                    class="ct-data-grid__row"
                >
                    <ct-block name="ct_data_grid_skeleton_cell_selection">
                        <td v-if="showSelection" class="ct-data-grid__cell">
                            <ct-block name="ct_data_grid_skeleton_cell_selection_content">
                                <div class="ct-grid__cell-content"></div>
                            </ct-block>
                        </td>
                    </ct-block>

                    <ct-block name="ct_data_grid_skeleton_columns">
                        <td
                            v-for="(column, columnIndex) in currentColumns"
                            v-show="column.visible"
                            :key="`grid-skeleton-columns-${itemIndex}-${columnIndex}`"
                            class="ct-data-grid__cell"
                        >
                            <ct-block name="ct_data_grid_skeleton_columns_content">
                                <div
                                    class="ct-data-grid__cell-content"
                                    :style="{ width: getRandomLength() + '%', 'min-width': '100px' }"
                                >
                                    <ct-block name="ct_data_grid_skeleton_element">
                                        <ct-skeleton variant="listing" />
                                    </ct-block>
                                </div>
                            </ct-block>
                        </td>
                    </ct-block>

                    <ct-block name="ct_data_grid_skeleton_spacer">
                        <td v-if="hasResizeColumns" class="ct-data-grid__cell" aria-hidden="true">
                            <ct-block name="ct_data_grid_skeleton_spacer_content">
                                <div class="ct-grid__cell-content"></div>
                            </ct-block>
                        </td>
                    </ct-block>

                    <ct-block name="ct_data_grid_skeleton_cell_actions">
                        <td v-if="showActions" class="ct-data-grid__cell">
                            <ct-block name="ct_data_grid_skeleton_cell_actions_content">
                                <div class="ct-grid__cell-content"></div>
                            </ct-block>
                        </td>
                    </ct-block>
                </tr>
            </ct-block>
        </tbody>
    </ct-block>
</template>

<script setup>
import './ct-data-grid-skeleton.scss';

defineProps({
    currentColumns: {
        type: Array,
        required: true,
        default() {
            return [];
        },
    },
    itemAmount: {
        type: Number,
        required: false,
        default: 7,
    },
    showSelection: {
        type: Boolean,
        required: false,
        default: true,
    },
    showActions: {
        type: Boolean,
        required: false,
        default: true,
    },
    hasResizeColumns: {
        type: Boolean,
        required: true,
        default: false,
    },
});

const getRandomLength = () => {
    const max = 100;
    const min = 50;

    return Math.floor(Math.random() * (max - min + 1)) + min;
};

ctDefinePublic({
    getRandomLength,
});

defineExpose({
    getRandomLength,
});
</script>
