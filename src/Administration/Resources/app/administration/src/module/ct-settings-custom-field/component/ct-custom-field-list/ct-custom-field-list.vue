<template>
    <ct-block name="sw_custom_field_list">
        <mt-card class="ct-custom-field-list" position-identifier="ct-custom-field-list">
            <ct-block name="sw_custom_field_list_toolbar">
                <div class="ct-custom-field-list__toolbar">
                    <ct-container columns="1fr 32px minmax(100px, 200px)" gap="0 10px">
                        <ct-block name="sw_custom_field_list_toolbar_searchfield">
                            <ct-simple-search-field
                                v-model:value="term"
                                size="small"
                                variant="form"
                                :delay="500"
                                @search-term-change="onSearchTermChange"
                            />
                        </ct-block>

                        <ct-block name="sw_custom_field_list_toolbar_delete">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('custom_field.editor'),
                                    showOnDisabledElements: true,
                                }"
                                :disabled="deleteButtonDisabled || !acl.can('custom_field.editor') || undefined"
                                square
                                size="small"
                                class="ct-custom-field-list__delete-button"
                                variant="secondary"
                                @click="onDeleteCustomFields"
                            >
                                <mt-icon name="regular-trash" size="16px" />
                            </mt-button>
                        </ct-block>

                        <ct-block name="sw_custom_field_list_toolbar_add">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('custom_field.editor'),
                                    showOnDisabledElements: true,
                                }"
                                :disabled="set.isLoading || !acl.can('custom_field.editor') || undefined"
                                size="small"
                                class="ct-custom-field-list__add-button"
                                variant="secondary"
                                @click="onAddCustomField(set)"
                            >
                                {{ $t('ct-settings-custom-field.set.detail.addCustomField') }}
                            </mt-button>
                        </ct-block>
                    </ct-container>
                </div>
            </ct-block>

            <ct-block name="sw_custom_field_list_grid">
                <ct-grid
                    v-if="(customFields && customFields.length > 0) || term"
                    ref="grid"
                    class="ct-custom-field-list__grid"
                    :items="customFields"
                    :is-fullpage="false"
                    table
                    :selectable="acl.can('custom_field.editor')"
                    @inline-edit-finish="onInlineEditFinish"
                    @inline-edit-cancel="onInlineEditCancel"
                    @ct-grid-select-item="selectionChanged"
                    @ct-grid-select-all="selectionChanged"
                >
                    <template #columns="{ item }">
                        <ct-block name="sw_custom_field_list_grid_column_label">
                            <ct-grid-column
                                data-index="label"
                                truncate
                                flex="minmax(150px, 1fr)"
                                :label="$t('ct-settings-custom-field.customField.list.labelCustomFieldLabel')"
                            >
                                <span
                                    class="ct-custom-field-list__custom-field-label"
                                    role="textbox"
                                    tabindex="0"
                                    @click="onCustomFieldEdit(item)"
                                    @keydown.enter="onCustomFieldEdit(item)"
                                >
                                    {{ getInlineSnippet(item.config.label) || item.name }}
                                </span>

                                <template #inline-edit>
                                    <mt-text-field
                                        :model-value="getInlineSnippet(item.config.label) || item.name"
                                        disabled
                                    />
                                </template>
                            </ct-grid-column>
                        </ct-block>

                        <ct-block name="sw_custom_field_list_grid_column_type">
                            <ct-grid-column
                                data-index="type"
                                truncate
                                flex="minmax(150px, 200px)"
                                :label="$t('ct-settings-custom-field.customField.list.labelCustomFieldType')"
                            >
                                {{ $t(`ct-settings-custom-field.types.${item.config.customFieldType || item.type}`) }}

                                <template #inline-edit>
                                    <mt-text-field
                                        :model-value="
                                            $t(`ct-settings-custom-field.types.${item.config.customFieldType || item.type}`)
                                        "
                                        disabled
                                    />
                                </template>
                            </ct-grid-column>
                        </ct-block>

                        <ct-block name="sw_custom_field_list_grid_column_custom_field_position">
                            <ct-grid-column
                                data-index="position"
                                flex="minmax(50px, 100px)"
                                :editable="acl.can('custom_field.editor')"
                                :label="$t('ct-settings-custom-field.customField.list.labelCustomFieldPosition')"
                            >
                                {{ item.config.customFieldPosition }}

                                <template #inline-edit>
                                    <ct-block name="sw_custom_field_list_grid_column_custom_field_position_edit">
                                        <mt-number-field v-model="item.config.customFieldPosition" number-type="int" />
                                    </ct-block>
                                </template>
                            </ct-grid-column>
                        </ct-block>

                        <ct-block name="sw_custom_field_list_grid_column_actions">
                            <ct-grid-column flex="minmax(100px, 100px)" align="center" label="&nbsp;">
                                <ct-context-button>
                                    <ct-block name="sw_custom_field_list_grid_column_actions_edit">
                                        <ct-context-menu-item
                                            class="ct-custom-field-list__edit-action"
                                            :disabled="item.isDeleted || !acl.can('custom_field.editor') || undefined"
                                            @click="onCustomFieldEdit(item)"
                                        >
                                            {{ $t('global.default.edit') }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_custom_field_list_grid_column_actions_reset_delete">
                                        <ct-context-menu-item
                                            v-if="item.isDeleted"
                                            variant="success"
                                            :disabled="!acl.can('custom_field.editor') || undefined"
                                        >
                                            {{
                                                $t(
                                                    'ct-settings-custom-field.customField.list.contextMenuCustomFieldResetDelete',
                                                )
                                            }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="sw_custom_field_list_grid_column_actions_delete">
                                        <template v-if="item.isDeleted"
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <ct-context-menu-item
                                            v-else
                                            variant="danger"
                                            class="ct-custom-field-list__delete-action"
                                            :disabled="!acl.can('custom_field.editor')"
                                            @click="onCustomFieldDelete(item)"
                                        >
                                            {{ $t('global.default.delete') }}
                                        </ct-context-menu-item>
                                    </ct-block>
                                </ct-context-button>
                            </ct-grid-column>
                        </ct-block>
                    </template>

                    <template #pagination>
                        <ct-block name="sw_custom_field_list_grid_pagination">
                            <ct-pagination
                                :limit="limit"
                                :page="page"
                                :auto-hide="false"
                                :total="customFields.total"
                                :steps="[limit]"
                                @page-change="onPageChange"
                            />
                        </ct-block>
                    </template>
                </ct-grid>
            </ct-block>

            <ct-block name="sw_custom_field_list_empty_state">
                <template v-if="(customFields && customFields.length > 0) || term"
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <mt-empty-state
                    v-else-if="!set.isLoading"
                    :icon="$route.meta.$module.icon"
                    :headline="$t('ct-settings-custom-field.set.detail.messageCustomFieldsEmpty')"
                />
            </ct-block>

            <ct-block name="sw_custom_field_list_custom_field_detail">
                <ct-custom-field-detail
                    v-if="currentCustomField"
                    :set="set"
                    :current-custom-field="currentCustomField"
                    @custom-field-edit-save="onSaveCustomField"
                    @custom-field-edit-cancel="onCancelCustomField"
                />
            </ct-block>

            <ct-block name="sw_custom_field_list_custom_field_delete">
                <ct-modal
                    v-if="deleteCustomField"
                    :title="$t('ct-settings-custom-field.customField.list.titleDeleteAction', {}, deleteCustomField.length)"
                    variant="small"
                    @modal-close="onCancelDeleteCustomField"
                >
                    <ct-block name="sw_custom_field_list_custom_field_delete_text">
                        <p class="ct-custom-field-delete__description">
                            {{
                                $t(
                                    'ct-settings-custom-field.customField.list.textDeleteActionConfirmation',
                                    { count: deleteCustomField.length },
                                    deleteCustomField.length,
                                )
                            }}
                        </p>
                    </ct-block>

                    <template #modal-footer>
                        <ct-block name="sw_custom_field_list_custom_field_delete_actions">
                            <ct-block name="sw_custom_field_list_custom_field_delete_action_cancel">
                                <mt-button size="small" variant="secondary" @click="onCancelDeleteCustomField">
                                    {{ $t('global.default.cancel') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="sw_custom_field_list_custom_field_delete_action_confirm">
                                <mt-button variant="critical" size="small" @click="onDeleteCustomField">
                                    {{ $t('global.default.delete') }}
                                </mt-button>
                            </ct-block>
                        </ct-block>
                    </template>
                </ct-modal>
            </ct-block>

            <ct-block name="sw_custom_field_list_loader">
                <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                <mt-loader v-if="isLoading" />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-custom-field-list.scss';
const { Criteria } = Contena.Data;
const { ContenaError } = Contena.Classes;
const types = Contena.Utils.types;

const props = defineProps({
    set: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['loading-changed']);

import { ref, computed, inject, watch, nextTick, provide } from 'vue';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { useNotification } from 'src/app/composables/use-notification';

const { getInlineSnippet } = useInlineSnippet();
const { createNotificationError } = useNotification();

const grid = ref(null);

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const term = ref('');
const isLoading = ref(false);
const currentCustomField = ref(null);
const deleteButtonDisabled = ref(true);
const disableRouteParams = ref(true);
const deleteCustomField = ref(null);
const customFields = ref(null);
const page = ref(1);
const total = ref(0);
const limit = ref(10);

const customFieldRepository = computed(() => {
    return repositoryFactory.create(props.set.customFields.entity, props.set.customFields.source);
});
const globalCustomFieldRepository = computed(() => {
    return repositoryFactory.create('custom_field');
});

const onSearchTermChange = () => {
    loadCustomFields();
};
const createdComponent = () => {
    loadCustomFields();
};
const loadCustomFields = () => {
    isLoading.value = true;

    const criteria = new Criteria(page.value, limit.value);

    criteria.addFilter(Criteria.equals('customFieldSetId', props.set.id));
    criteria.addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

    if (term.value) {
        criteria.setTerm(term.value);
    }

    return customFieldRepository.value
        .search(criteria)
        .then((response) => {
            customFields.value = response;
            total.value = response.total;

            return response;
        })
        .finally(() => {
            isLoading.value = false;
        });
};
const selectionChanged = (selection) => {
    deleteButtonDisabled.value = Object.keys(selection).length <= 0;
};
const onCustomFieldDelete = (customField) => {
    deleteCustomField.value = customField;
};
const onDeleteCustomFields = () => {
    deleteCustomField.value = Array.from(Object.values(grid.value.getSelection()));
};
const onAddCustomField = () => {
    const customField = customFieldRepository.value.create();
    customField.storeApiAware = true;

    onCustomFieldEdit(customField);
};
const onCancelCustomField = () => {
    customFieldRepository.value.discard(currentCustomField.value);
    currentCustomField.value = null;
};
const onInlineEditFinish = (item) => {
    onSaveCustomField(item);
};
const onSaveCustomField = (field = currentCustomField.value) => {
    removeEmptyProperties(field.config);

    return customFieldRepository.value
        .save(field)
        .then(() => {
            currentCustomField.value = null;
            loadCustomFields();
        })
        .catch((error) => {
            const [{ detail: message = 'Error', code = 'UNKNOWN_ERROR' } = {}] = error?.response?.data?.errors ?? [];

            Contena.Store.get('error').addApiError({
                expression: `custom_field.${field.id}.name.error`,
                error: new ContenaError({ code, detail: message }),
            });

            createNotificationError({ message });
        });
};
const onInlineEditCancel = (customField) => {
    customFieldRepository.value.discard(customField);
};
const onCustomFieldEdit = (customField) => {
    currentCustomField.value = customField;
};
const removeEmptyProperties = (config) => {
    Object.keys(config).forEach((property) => {
        if (
            [
                'number',
                'boolean',
            ].includes(typeof config[property])
        ) {
            return;
        }

        if (types.isObject(config[property]) || types.isArray(config[property])) {
            removeEmptyProperties(config[property]);
        }

        if ((types.isEmpty(config[property]) || config[property] === undefined) && config[property !== null]) {
            delete config[property];
        }
    });
};
const isCustomFieldNameUnique = (customField) => {
    // Search the server for the customField name
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('name', customField.name));
    return globalCustomFieldRepository.value.search(criteria).then((res) => {
        return res.length === 0;
    });
};
provide('SwCustomFieldListIsCustomFieldNameUnique', isCustomFieldNameUnique);
const onPageChange = (event) => {
    page.value = event.page;

    loadCustomFields();
};
const onCancelDeleteCustomField = () => {
    deleteCustomField.value = null;
};
const onDeleteCustomField = () => {
    // contains an array with custom field id's
    const toBeDeletedCustomFields = [];
    const isArray = Array.isArray(deleteCustomField.value);

    if (isArray) {
        deleteCustomField.value.forEach((customField) => toBeDeletedCustomFields.push(customField.id));
    } else {
        toBeDeletedCustomFields.push(deleteCustomField.value.id);
    }

    return globalCustomFieldRepository.value.syncDeleted(toBeDeletedCustomFields, Contena.Context.api).then(() => {
        deleteButtonDisabled.value = true;
        deleteCustomField.value = null;

        // Wait for modal to be closed
        void nextTick(() => {
            loadCustomFields();
        });
    });
};

watch(
    () => isLoading.value,
    (value) => {
        emit('loading-changed', value);
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    acl,
    term,
    isLoading,
    currentCustomField,
    deleteButtonDisabled,
    disableRouteParams,
    deleteCustomField,
    customFields,
    page,
    total,
    limit,
    customFieldRepository,
    globalCustomFieldRepository,
    onSearchTermChange,
    createdComponent,
    loadCustomFields,
    selectionChanged,
    onCustomFieldDelete,
    onDeleteCustomFields,
    onAddCustomField,
    onCancelCustomField,
    onInlineEditFinish,
    onSaveCustomField,
    onInlineEditCancel,
    onCustomFieldEdit,
    removeEmptyProperties,
    isCustomFieldNameUnique,
    onPageChange,
    onCancelDeleteCustomField,
    onDeleteCustomField,
});

defineExpose({
    repositoryFactory,
    acl,
    term,
    isLoading,
    currentCustomField,
    deleteButtonDisabled,
    disableRouteParams,
    deleteCustomField,
    customFields,
    page,
    total,
    limit,
    customFieldRepository,
    globalCustomFieldRepository,
    onSearchTermChange,
    createdComponent,
    loadCustomFields,
    selectionChanged,
    onCustomFieldDelete,
    onDeleteCustomFields,
    onAddCustomField,
    onCancelCustomField,
    onInlineEditFinish,
    onSaveCustomField,
    onInlineEditCancel,
    onCustomFieldEdit,
    removeEmptyProperties,
    isCustomFieldNameUnique,
    onPageChange,
    onCancelDeleteCustomField,
    onDeleteCustomField,
});
</script>
