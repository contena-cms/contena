<template>
    <ct-block name="sw_settings_custom_field_set_list">
        <ct-page class="ct-settings-custom-field-set-list">
            <template #search-bar>
                <ct-block name="sw_settings_custom_field_set_list_search_bar">
                    <mt-search
                        :model-value="term"
                        :placeholder="translate('ct-settings-custom-field.general.placeholderSearchBar')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_settings_custom_field_set_list_header">
                    <h2>
                        {{ translate('ct-settings.index.title') }}
                        <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ translate('ct-settings-custom-field.set.list.textHeadline') }}

                        <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_custom_field_set_list_actions">
                    <ct-block name="sw_settings_custom_field_set_list_actions_add">
                        <mt-button
                            v-tooltip.bottom="{
                                message: translate('ct-privileges.tooltip.warning'),
                                disabled: acl.can('custom_field.creator'),
                                showOnDisabledElements: true,
                            }"
                            class="ct-settings-custom-field-set-list__button-create"
                            :disabled="!acl.can('custom_field.creator') || undefined"
                            variant="primary"
                            size="default"
                            @click="$router.push({ name: 'ct.settings.custom.field.create' })"
                        >
                            {{ translate('global.default.add') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_custom_field_set_list_content">
                    <ct-card-view>
                        <ct-block name="sw_settings_custom_field_set_list_content_inner">
                            <mt-card
                                class="ct-settings-custom-field-set-list__card"
                                position-identifier="ct-settings-custom-field-set-list"
                            >
                                <template #grid>
                                    <ct-block name="sw_settings_custom_field_set_list_grid">
                                        <ct-grid
                                            v-show="items.length > 0"
                                            class="ct-settings-custom-field-set-list-grid"
                                            :selectable="false"
                                            :items="items"
                                            table
                                        >
                                            <template #columns="{ item }">
                                                <ct-block name="sw_settings_custom_field_set_list_grid_columns">
                                                    <ct-block name="sw_settings_custom_field_set_list_grid_column_label">
                                                        <ct-grid-column
                                                            flex="minmax(180px, 2fr)"
                                                            :label="
                                                                translate('ct-settings-custom-field.set.list.columnName')
                                                            "
                                                        >
                                                            <template v-if="item.global">
                                                                {{ getInlineSnippet(item.config.label) || item.name }}
                                                                <ct-help-text
                                                                    class="ct-settings-custom-field-set-list__help-text-global-set"
                                                                    :text="
                                                                        translate(
                                                                            'ct-settings-custom-field.set.list.helpTextGlobalSet',
                                                                        )
                                                                    "
                                                                />
                                                            </template>
                                                            <template v-else>
                                                                <router-link
                                                                    :title="translate('global.default.edit')"
                                                                    class="ct-custom-field-set-list__column-name"
                                                                    :to="{
                                                                        name: 'ct.settings.custom.field.detail',
                                                                        params: { id: item.id },
                                                                    }"
                                                                >
                                                                    {{ getInlineSnippet(item.config.label) || item.name }}
                                                                </router-link>
                                                            </template>
                                                        </ct-grid-column>
                                                    </ct-block>

                                                    <ct-block name="sw_settings_custom_field_set_list_grid_column_actions">
                                                        <ct-grid-column flex="minmax(70px, 70px)" align="center" label="">
                                                            <ct-context-button>
                                                                <ct-context-menu-item
                                                                    v-tooltip="{
                                                                        message: translate(
                                                                            'ct-settings-custom-field.set.list.helpTextGlobalSet',
                                                                        ),
                                                                        showOnDisabledElements: true,
                                                                        disabled: !item.global,
                                                                    }"
                                                                    :disabled="
                                                                        item.global ||
                                                                        !acl.can('custom_field.editor') ||
                                                                        undefined
                                                                    "
                                                                    class="ct-custom-field-set-list__edit-action"
                                                                    :router-link="{
                                                                        name: 'ct.settings.custom.field.detail',
                                                                        params: { id: item.id },
                                                                    }"
                                                                >
                                                                    {{ translate('global.default.edit') }}
                                                                </ct-context-menu-item>

                                                                <template v-if="!item.global">
                                                                    <ct-context-menu-item
                                                                        class="ct-settings-custom-field-set-list__delete-action"
                                                                        :disabled="
                                                                            !acl.can('custom_field.deleter') || undefined
                                                                        "
                                                                        variant="danger"
                                                                        @click="onDelete(item.id)"
                                                                    >
                                                                        {{ translate('global.default.delete') }}
                                                                    </ct-context-menu-item>
                                                                </template>
                                                            </ct-context-button>
                                                        </ct-grid-column>
                                                    </ct-block>

                                                    <ct-block name="sw_settings_custom_field_set_list_grid_delete_modal">
                                                        <ct-modal
                                                            v-if="showDeleteModal === item.id"
                                                            :title="translate('global.default.warning')"
                                                            variant="small"
                                                            @modal-close="onCloseDeleteModal"
                                                        >
                                                            <ct-block
                                                                name="sw_settings_custom_field_set_list_grid_delete_modal_text"
                                                            >
                                                                <p>
                                                                    {{
                                                                        translate(
                                                                            'ct-settings-custom-field.set.list.textDeleteConfirm',
                                                                            {
                                                                                name:
                                                                                    getInlineSnippet(item.config.label) ||
                                                                                    item.name,
                                                                            },
                                                                            0,
                                                                        )
                                                                    }}
                                                                </p>
                                                            </ct-block>

                                                            <template #modal-footer>
                                                                <ct-block
                                                                    name="sw_settings_custom_field_set_list_grid_delete_modal_footer"
                                                                >
                                                                    <ct-block
                                                                        name="sw_settings_custom_field_set_list_grid_delete_modal_cancel"
                                                                    >
                                                                        <mt-button
                                                                            size="small"
                                                                            variant="secondary"
                                                                            @click="onCloseDeleteModal"
                                                                        >
                                                                            {{ translate('global.default.cancel') }}
                                                                        </mt-button>
                                                                    </ct-block>

                                                                    <ct-block
                                                                        name="sw_settings_custom_field_set_list_grid_delete_modal_delete"
                                                                    >
                                                                        <mt-button
                                                                            variant="critical"
                                                                            size="small"
                                                                            @click="onConfirmDelete(item.id)"
                                                                        >
                                                                            {{ translate('global.default.delete') }}
                                                                        </mt-button>
                                                                    </ct-block>
                                                                </ct-block>
                                                            </template>
                                                        </ct-modal>
                                                    </ct-block>
                                                </ct-block>
                                            </template>

                                            <template #pagination>
                                                <ct-block
                                                    name="sw_settings_custom_field_set_list_content_columns_pagination"
                                                >
                                                    <ct-pagination
                                                        :page="page"
                                                        :limit="limit"
                                                        :total="total"
                                                        :total-visible="7"
                                                        @page-change="onPageChange"
                                                    />
                                                </ct-block>
                                            </template>
                                        </ct-grid>
                                    </ct-block>
                                </template>
                                <ct-block name="sw_settings_custom_fields_set_list_empty_message">
                                    <mt-empty-state
                                        v-if="!isLoading && items.length <= 0"
                                        :icon="$route.meta.$module.icon"
                                        :headline="translate('ct-settings-custom-field.set.list.messageEmpty')"
                                    />
                                </ct-block>
                            </mt-card>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-custom-field-set-list.scss';
const {
    Locale,
    Data: { Criteria },
} = Contena;

defineProps({});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { useSettingsListing } from 'src/app/composables/use-settings-listing';

const { t } = useI18n();
const { getInlineSnippet } = useInlineSnippet();
const {
    page,
    limit,
    total,
    term: term2,
    items,
    isLoading,
    showDeleteModal,
    deleteEntity,
    getMainListingParams,
    onPageChange,
    onSearch,
    initializeSettingsListing,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
} = useSettingsListing();

const translate = t;

usePageTitle();

const term = term2;

const acl = inject('acl');
const feature = inject('feature');

const entityName = ref('custom_field_set');
const sortBy = ref('config.name');
const datetime = ref('');
const showModal = ref(false);

const titleSaveSuccess = computed(() => {
    return t('global.default.success');
});
const messageSaveSuccess = computed(() => {
    if (deleteEntity.value) {
        return t(
            'ct-settings-custom-field.set.list.messageDeleteSuccess',
            {
                name: getInlineSnippet(deleteEntity.value.config.label) || deleteEntity.value.name,
            },
            0,
        );
    }
    return '';
});
const listingCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    const params = getMainListingParams();

    criteria.addFilter(
        Criteria.multi('OR', [
            ...getLocaleCriterias(params.term),
            ...getTermCriteria(params.term),
        ]),
    );

    criteria.addFilter(Criteria.equals('appId', null));

    return criteria;
});

const getLocaleCriterias = (term) => {
    if (!term) {
        return [];
    }

    const criteria = [];
    const locales = Locale.getLocaleRegistry();

    locales.forEach((value, key) => {
        criteria.push(Criteria.contains(`config.label.${key}`, term));
    });

    return criteria;
};
const getTermCriteria = (term) => {
    const criteria = [];

    if (term) {
        criteria.push(Criteria.contains('name', term));
    }

    return criteria;
};

initializeSettingsListing({
    sortBy,
    entityName,
});

swDefinePublic({
    acl,
    feature,
    entityName,
    sortBy,
    datetime,
    showModal,
    titleSaveSuccess,
    messageSaveSuccess,
    listingCriteria,
    getLocaleCriterias,
    getTermCriteria,
});

defineExpose({
    acl,
    feature,
    entityName,
    sortBy,
    datetime,
    showModal,
    titleSaveSuccess,
    messageSaveSuccess,
    listingCriteria,
    getLocaleCriterias,
    getTermCriteria,
});
</script>
