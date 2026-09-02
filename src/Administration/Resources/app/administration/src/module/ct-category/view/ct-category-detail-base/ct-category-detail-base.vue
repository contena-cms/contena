<template>
    <ct-block name="ct_category_detail_base">
        <div class="ct-category-detail-base">
            <ct-block name="ct_category_detail_information">
                <mt-card
                    position-identifier="ct-category-detail-base"
                    :title="$t('ct-category.base.general.headlineInformationCard')"
                    :is-loading="isLoading"
                >
                    <ct-container columns="repeat(auto-fit, minmax(150px, 1fr))" gap="0px 30px">
                        <ct-block name="ct_category_detail_information_name">
                            <mt-text-field
                                v-model="category.name"
                                required
                                name="categoryName"
                                validation="required"
                                :disabled="!acl.can('category.editor')"
                                :label="$t('ct-category.base.general.categoryNameLabel')"
                                :placeholder="placeholder(category, 'name')"
                                :error="categoryNameError"
                            />
                        </ct-block>

                        <ct-block name="ct_category_detail_information_active">
                            <mt-switch
                                v-model="category.active"
                                :disabled="!acl.can('category.editor')"
                                name="categoryActive"
                                class="ct-category-detail-base__active"
                                :label="$t('ct-category.base.general.isCategoryActiveLabel')"
                                bordered
                            />
                        </ct-block>
                    </ct-container>

                    <ct-block name="ct_category_detail_information_tags">
                        <ct-entity-tag-select
                            v-if="category && !isLoading"
                            v-model:entity-collection="category.tags"
                            class="ct-category-detail-base__tags"
                            :label="$t('ct-category.base.general.labelCategoryTags')"
                            :placeholder="$t('ct-category.base.general.labelCategoryTagsPlaceholder')"
                            :disabled="!acl.can('category.editor')"
                        />
                    </ct-block>

                    <ct-block name="ct_category_detail_information_type">
                        <div class="ct-category-detail-base__type-container">
                            <ct-block name="ct_category_detail_information_type_select">
                                <ct-single-select
                                    v-model:value="category.type"
                                    class="ct-category-detail-base__type-selection"
                                    :help-text="categoryTypeHelpText"
                                    :label="$t('ct-category.base.general.types.title')"
                                    :disabled="!acl.can('category.editor')"
                                    :error="categoryTypeError"
                                    :options="categoryTypes"
                                    show-clearable-button
                                />
                            </ct-block>
                        </div>
                    </ct-block>
                </mt-card>
            </ct-block>

            <ct-block name="ct_category_detail_entry_point">
                <ct-category-entry-point-card
                    v-if="(category.type === 'folder' || category.type === 'page') && !isCategoryColumn"
                    v-bind="{ category, isLoading }"
                />
            </ct-block>

            <ct-block name="ct_category_detail_link">
                <ct-category-link-settings v-if="category.type === 'link'" v-bind="{ category, isLoading }" />
            </ct-block>

            <template v-if="category.type !== 'link'">
                <ct-block name="ct_category_detail_menu">
                    <ct-category-detail-menu v-bind="{ category, isLoading }" />
                </ct-block>
            </template>

            <ct-block name="ct_category_detail_attribute_sets">
                <mt-card
                    v-if="customFieldSetsArray.length > 0"
                    position-identifier="ct-category-detail-attribute-sets"
                    :title="$t('ct-settings-custom-field.general.mainMenuItemGeneral')"
                    :is-loading="isLoading"
                >
                    <ct-custom-field-set-renderer
                        :entity="category"
                        :sets="customFieldSetsArray"
                        :disabled="!acl.can('category.editor')"
                    />
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-category-detail-base.scss';
Contena.Component.getComponentHelper();

defineProps({
    isLoading: {
        type: Boolean,
        required: true,
    },
});

import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

const { t } = useI18n();
const { placeholder } = usePlaceholder();

const $t = t;

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const customFieldSetsArray = computed(() => {
    return Contena.Store.get('ctCategoryDetail').customFieldSets ?? [];
});
const categoryNameError = computed(() => {
    const entity = category.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const categoryTypeError = computed(() => {
    const entity = category.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'type');
});
const categoryTypes = computed(() => {
    return [
        {
            value: 'page',
            label: t('ct-category.base.general.types.page'),
        },
        {
            value: 'folder',
            label: t('ct-category.base.general.types.folder'),
        },
        {
            value: 'link',
            label: typeLinkLabel.value,
            disabled: isChannelEntryPoint.value,
        },
    ];
});
const typeLinkLabel = computed(() => {
    if (isChannelEntryPoint.value) {
        return t('ct-category.base.general.types.linkUnavailable');
    }

    return t('ct-category.base.general.types.link');
});
const categoryTypeHelpText = computed(() => {
    if (
        [
            'page',
            'folder',
            'link',
        ].includes(category.value.type)
    ) {
        return t(`ct-category.base.general.types.helpText.${category.value.type}`);
    }

    return null;
});
const isChannelEntryPoint = computed(() => {
    return (
        category.value.navigationChannels.length > 0 ||
        category.value.serviceChannels.length > 0 ||
        category.value.footerChannels.length > 0
    );
});
const category = computed(() => {
    return Contena.Store.get('ctCategoryDetail').category;
});
const isCategoryColumn = computed(() => {
    return Contena.Store.get('ctCategoryDetail').isCategoryColumn;
});

ctDefinePublic({
    repositoryFactory,
    acl,
    customFieldSetsArray,
    categoryNameError,
    categoryTypeError,
    categoryTypes,
    typeLinkLabel,
    categoryTypeHelpText,
    isChannelEntryPoint,
    category,
    isCategoryColumn,
});

defineExpose({
    repositoryFactory,
    acl,
    customFieldSetsArray,
    categoryNameError,
    categoryTypeError,
    categoryTypes,
    typeLinkLabel,
    categoryTypeHelpText,
    isChannelEntryPoint,
    category,
    isCategoryColumn,
});
</script>
