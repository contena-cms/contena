<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="sw_settings_search_search_behaviour">
        <mt-card
            class="ct-settings-search-search-behaviour"
            position-identifier="ct-settings-search-search-behaviour"
            :title="t('ct-settings-search.generalTab.labelSearchBehaviour')"
            :is-loading="isLoading"
        >
            <ct-block name="sw_settings_search_search_behaviour_title">
                <p class="ct-settings-search-search-behaviour__title">
                    {{ t('ct-settings-search.generalTab.textTitleSearchBehaviour') }}
                </p>
            </ct-block>

            <ct-block name="sw_settings_search_search_behaviour_condition">
                <mt-radio-group-root
                    :model-value="searchBehaviourConfigs.andLogic"
                    class="ct-settings-search-search-behaviour__condition"
                    name="ct-field--searchBehaviourConfigs-andLogic"
                    :disabled="!acl.can('blog_search_config.editor')"
                    @update:model-value="updateAndLogic"
                >
                    <mt-radio-group-list>
                        <div
                            v-for="option in conditionsOptions"
                            :key="String(option.value)"
                            class="ct-settings-search-search-behaviour__option"
                        >
                            <mt-radio-group-item
                                :id="`search-behaviour-${String(option.value)}`"
                                :value="option.value"
                                :label="option.name"
                            />
                            <p>{{ option.description }}</p>
                        </div>
                    </mt-radio-group-list>
                </mt-radio-group-root>
            </ct-block>

            <ct-block name="sw_settings_search_search_behaviour_search_term_length">
                <mt-number-field
                    :model-value="searchBehaviourConfigs.minSearchLength"
                    class="ct-settings-search-search-behaviour__minimum-length"
                    name="ct-field--searchBehaviourConfigs-minSearchLength"
                    number-type="int"
                    :label="t('ct-settings-search.generalTab.labelMinimalSearchTerm')"
                    :disabled="!acl.can('blog_search_config.editor')"
                    :min="min"
                    :max="max"
                    @update:model-value="updateMinSearchLength"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* eslint-disable vue/no-mutating-props */
/* global Entity */
/* global Entity */
import { computed, inject, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';

import './ct-settings-search-search-behaviour.scss';

type SearchBehaviourConfig = Pick<Entity<'blog_search_config'>, 'andLogic' | 'minSearchLength'>;
type ConditionOption = { name: string; value: boolean; description: string };

const props = defineProps({
    searchBehaviourConfigs: {
        type: Object as PropType<SearchBehaviourConfig>,
        default: () => ({ andLogic: false, minSearchLength: 2 }),
    },
    isLoading: { type: Boolean, default: false },
});
const { t } = useI18n();
const acl = inject<AclService>('acl');
if (!acl) throw new Error('The ACL service is unavailable.');

const min = 1;
const max = 20;
const conditionsOptions = computed<ConditionOption[]>(() => [
    {
        name: t('ct-settings-search.generalTab.labelSearchOrCondition'),
        value: false,
        description: t('ct-settings-search.generalTab.textSearchOrConditionExplain'),
    },
    {
        name: t('ct-settings-search.generalTab.labelSearchAndCondition'),
        value: true,
        description: t('ct-settings-search.generalTab.textSearchAndConditionExplain'),
    },
]);
const updateAndLogic = (value: string | number | boolean | null): void => {
    if (typeof value === 'boolean') Object.assign(props.searchBehaviourConfigs, { andLogic: value });
};
const updateMinSearchLength = (value: number | null): void => {
    if (typeof value === 'number') Object.assign(props.searchBehaviourConfigs, { minSearchLength: value });
};

swDefinePublic({
    min,
    max,
    conditionsOptions,
    updateAndLogic,
    updateMinSearchLength,
});

defineExpose({ min, max, conditionsOptions, updateAndLogic, updateMinSearchLength });
</script>
