<template>
    <ct-block name="ct_bulk_edit_modal">
        <ct-modal
            class="ct-bulk-edit-modal"
            :title="$t('global.ct-bulk-edit-modal.bulkEditModalTitle', { count: itemCount }, itemCount)"
            variant="full"
            @modal-close="$emit('modal-close')"
            @edit-items="$emit('edit-items')"
        >
            <ct-block name="ct_bulk_edit_modal_grid">
                <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                <ct-data-grid
                    ref="bulkEditGrid"
                    :identifier="identifier"
                    :data-source="paginateRecords"
                    :columns="bulkGridEditColumns"
                    :pre-selection="selection"
                    :show-selection="true"
                    :show-actions="false"
                    :skeleton-item-amount="limit"
                    @selection-change="updateBulkEditSelection"
                >
                    <template v-for="(_, slot) in getSlots" #[slot]="slotProps" :key="slot">
                        <ct-block name="ct_bulk_edit_modal_grid_custom_slot">
                            <slot :name="slot" v-bind="slotProps"></slot>
                        </ct-block>
                    </template>

                    <template #pagination>
                        <ct-block name="ct_bulk_edit_modal_list_pagination">
                            <ct-pagination
                                v-bind="{ page, limit, steps }"
                                :total="records.length"
                                :auto-hide="false"
                                :total-visible="7"
                                @page-change="paginate"
                            />
                        </ct-block>
                    </template>
                </ct-data-grid>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_bulk_edit_modal_grid_footer">
                    <slot name="ct-bulk-edit-modal-cancel">
                        <mt-button size="small" variant="secondary" @click="$emit('modal-close')">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </slot>

                    <slot name="ct-bulk-edit-modal-confirm">
                        <mt-button variant="primary" size="small" @click="editItems">
                            {{ $t('global.ct-bulk-edit-modal.startBulkEdit') }}
                        </mt-button>
                    </slot>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-bulk-edit-modal.scss';

const props = defineProps({
    selection: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },

    steps: {
        type: Array,
        required: false,
        default() {
            return [
                200,
                300,
                400,
                500,
            ];
        },
    },

    bulkGridEditColumns: {
        type: Array,
        required: true,
    },
});
const emit = defineEmits([
    'modal-close',
    'edit-items',
]);

import { ref, computed, useSlots } from 'vue';

const slots = useSlots();

const records = ref([]);
const bulkEditSelection = ref(props.selection);
const limit = ref(200);
const page = ref(1);
const identifier = ref('ct-bulk-edit-grid');

const itemCount = computed(() => {
    return Object.keys(bulkEditSelection.value).length;
});
const paginateRecords = computed(() => {
    return records.value.slice((page.value - 1) * limit.value, page.value * limit.value);
});
const getSlots = computed(() => {
    return slots;
});

const createdComponent = () => {
    const selectedRecords = Object.values(props.selection);

    if (selectedRecords.length > 0) {
        records.value = selectedRecords;
    }
};
const paginate = ({ page: selectedPage = 1, limit: selectedLimit = 10 }) => {
    page.value = selectedPage;
    limit.value = selectedLimit;
};
const updateBulkEditSelection = (selections) => {
    bulkEditSelection.value = selections;
};
const editItems = () => {
    emit('modal-close');

    if (itemCount.value > 0) {
        Contena.Store.get('ctBulkEdit').selectedIds = Object.keys(bulkEditSelection.value);
        emit('edit-items');
    }
};

createdComponent();

ctDefinePublic({
    records,
    bulkEditSelection,
    limit,
    page,
    identifier,
    itemCount,
    paginateRecords,
    getSlots,
    createdComponent,
    paginate,
    updateBulkEditSelection,
    editItems,
});

defineExpose({
    records,
    bulkEditSelection,
    limit,
    page,
    identifier,
    itemCount,
    paginateRecords,
    getSlots,
    createdComponent,
    paginate,
    updateBulkEditSelection,
    editItems,
});
</script>
