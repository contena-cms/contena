<template>
    <ct-block name="ct_member_detail_base">
        <div class="ct-member-detail-base">
            <ct-member-card
                :title="t('ct-member.detailBase.labelAccountCard')"
                :member="member"
                :edit-mode="memberEditMode"
                :is-loading="isLoading"
            >
                <ct-member-base-info :member="member" :member-edit-mode="memberEditMode" :is-loading="isLoading" />
            </ct-member-card>

            <ct-block name="ct_member_detail_base_custom_fields">
                <mt-card
                    v-if="customFieldSets.length > 0"
                    position-identifier="ct-member-detail-custom-fields"
                    :title="t('ct-settings-custom-field.general.mainMenuItemGeneral')"
                    :is-loading="isLoading"
                >
                    <ct-custom-field-set-renderer :entity="member" :disabled="!memberEditMode" :sets="customFieldSets" />
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import type { PropType } from 'vue';
import { inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import './ct-member-detail-base.scss';

const props = defineProps({
    member: { type: Object as PropType<Entity<'member'>>, required: true },
    memberEditMode: { type: Boolean, required: true },
    isLoading: { type: Boolean, default: false },
});
const { t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) throw new Error('The repository factory is unavailable.');
const customFieldSets = ref<Entity<'custom_field_set'>[]>([]);
const loadCustomFieldSets = async (): Promise<void> => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addFilter(Contena.Data.Criteria.equals('relations.entityName', 'member'));
    criteria.getAssociation('customFields').addSorting(Contena.Data.Criteria.naturalSorting('config.customFieldPosition'));
    const result = await repositoryFactory.create('custom_field_set').search(criteria, Contena.Context.api);
    customFieldSets.value = Array.from(result);
};
watch(
    () => props.member.id,
    () => void loadCustomFieldSets(),
    { immediate: true },
);

ctDefinePublic({
    customFieldSets,
    loadCustomFieldSets,
});

defineExpose({ customFieldSets, loadCustomFieldSets });
</script>
