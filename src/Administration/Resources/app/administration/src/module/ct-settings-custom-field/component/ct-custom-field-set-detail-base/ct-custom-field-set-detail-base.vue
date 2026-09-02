<template>
    <ct-block name="ct_settings_custom_field_set_detail_base">
        <mt-card
            class="ct-settings-custom-field-set-detail-base"
            position-identifier="ct-custom-field-detail-base"
            :title="$t('ct-settings-custom-field.set.detail.titleCardInformation')"
        >
            <ct-block name="ct_settings_custom_field_set_detail_base_technical_name">
                <mt-text-field
                    v-model="set.name"
                    name="ct-field--set-name"
                    class="ct-settings-custom-field-set-detail-base__technical-name"
                    :label="$t('ct-settings-custom-field.set.detail.labelTechnicalName')"
                    :help-text="$t('ct-settings-custom-field.general.tooltipTechnicalName')"
                    :disabled="!set._isNew || !acl.can('custom_field.editor') || undefined"
                    :error="technicalNameError"
                    required
                    @update:model-value="onTechnicalNameChange"
                />
            </ct-block>

            <ct-block name="ct_settings_custom_field_set_detail_base_position">
                <mt-number-field
                    v-model="set.position"
                    name="ct-field--set-position"
                    class="ct-settings-custom-field-set-detail-base__base-postion"
                    number-type="int"
                    :disabled="!acl.can('custom_field.editor') || undefined"
                    :label="$t('ct-settings-custom-field.set.detail.labelPosition')"
                />
            </ct-block>

            <ct-block name="ct_settings_custom_field_set_detail_base_translated">
                <mt-switch
                    v-if="set.config"
                    v-model="set.config.translated"
                    name="ct-field--set-config-translated"
                    class="ct-settings-custom-field-set-detail-base__base-translation"
                    :disabled="!acl.can('custom_field.editor') || undefined"
                    :label="$t('ct-settings-custom-field.set.detail.labelCheckboxTranslated')"
                />
            </ct-block>

            <ct-block name="ct_settings_custom_field_set_detail_base_labels">
                <ct-custom-field-translated-labels
                    v-if="set.config"
                    v-model:config="set.config"
                    :disabled="!acl.can('custom_field.editor') || undefined"
                    :property-names="propertyNames"
                    :locales="locales"
                />
            </ct-block>

            <ct-block name="ct_settings_custom_field_set_detail_base_multi_select">
                <mt-select
                    id="entities"
                    class="ct-settings-custom-field-set-detail-base__label-entities"
                    :disabled="!acl.can('custom_field.editor') || undefined"
                    :label="$t('ct-settings-custom-field.set.detail.labelEntities')"
                    :options="relationEntityNames"
                    value-property="entityName"
                    label-property="entityName"
                    :model-value="selectedRelationEntityNames"
                    enable-multi-selection
                    :search-function="searchRelationEntityNames"
                    @item-add="onAddRelation"
                    @item-remove="onRemoveRelation"
                >
                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #result-label-property="slotProps">
                        {{ getRelationLabel(slotProps.item.entityName) }}
                    </template>

                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #selection-label-property="slotProps">
                        {{ getRelationLabel(slotProps.item.entityName) }}
                    </template>
                </mt-select>
            </ct-block>

            <ct-block name="ct_settings_custom_field_set_detail_base_entities" />
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref, toRef, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';

interface CustomFieldSetRelation {
    id: string;
    entityName: string;
    searchField: Record<string, string>;
}

interface CustomFieldSetRelations extends Array<CustomFieldSetRelation> {
    entity: string;
    source: string;

    remove(_id: string): void;
}

interface CustomFieldSet {
    _isNew?: boolean;
    config?: {
        translated?: boolean;
        [key: string]: unknown;
    };
    name?: string;
    position?: number;
    relations?: CustomFieldSetRelations;
}

interface CustomFieldDataProviderService {
    getEntityNames(): string[];
}

interface RelationRepository {
    create(): CustomFieldSetRelation;
}

interface RelationRepositoryFactory {
    create(entity: string, source: string): RelationRepository;
}

interface RelationSearch {
    options: CustomFieldSetRelation[];
    searchTerm: string;
}

const props = defineProps({
    set: {
        type: Object as PropType<CustomFieldSet>,
        required: true,
        default: () => ({}),
    },
    technicalNameError: {
        type: Object as PropType<Record<string, unknown> | null>,
        default: null,
    },
});
const emit = defineEmits<{
    'reset-errors': [];
}>();
const set = toRef(props, 'set');

const acl = inject<AclService>('acl');
const customFieldDataProviderService = inject<CustomFieldDataProviderService>('customFieldDataProviderService');
const i18n = useI18n();

if (!acl || !customFieldDataProviderService) {
    throw new Error('Custom Field set detail services are unavailable.');
}

const propertyNames = ref({
    label: i18n.t('ct-settings-custom-field.customField.detail.labelLabel'),
});
const locales = computed(() => {
    if (set.value.config?.translated === true) {
        const availableLocales = i18n.availableLocales.filter((locale) => locale.includes('-'));
        const fallbackLocale = Contena.Context.app.fallbackLocale ?? Contena.Store.get('session').currentLocale;

        if (fallbackLocale && availableLocales.includes(fallbackLocale)) {
            return [
                fallbackLocale,
                ...availableLocales.filter((locale) => locale !== fallbackLocale),
            ];
        }

        return availableLocales;
    }

    const fallbackLocale = Contena.Context.app.fallbackLocale ?? Contena.Store.get('session').currentLocale;

    return fallbackLocale ? [fallbackLocale] : [];
});
const customFieldSetRelationRepository = computed<RelationRepository | undefined>(() => {
    if (!set.value.relations) {
        return undefined;
    }

    const repositoryFactory = Contena.Service('repositoryFactory') as unknown as RelationRepositoryFactory;

    return repositoryFactory.create(set.value.relations.entity, set.value.relations.source);
});
const selectedRelationEntityNames = computed(() => set.value.relations?.map((relation) => relation.entityName) ?? []);
const relationEntityNames = computed(() => {
    const repository = customFieldSetRelationRepository.value;

    if (!repository) {
        return [];
    }

    return customFieldDataProviderService.getEntityNames().map((entityName) => {
        const relation = repository.create();
        relation.entityName = entityName;
        relation.searchField = {};

        i18n.availableLocales.forEach((locale) => {
            const snippet = `global.entities.${entityName}`;

            if (i18n.te(snippet, locale)) {
                relation.searchField[locale] = i18n.t(snippet, 2, { locale });
            }
        });

        return relation;
    });
});

function onAddRelation(relation: CustomFieldSetRelation): void {
    set.value.relations?.push(relation);
}

function onRemoveRelation(relationToRemove: CustomFieldSetRelation): void {
    const matchingRelation = set.value.relations?.find((relation) => relation.entityName === relationToRemove.entityName);

    if (matchingRelation) {
        set.value.relations?.remove(matchingRelation.id);
    }
}

function searchRelationEntityNames({ options, searchTerm }: RelationSearch): CustomFieldSetRelation[] {
    const lowerSearchTerm = searchTerm.toLowerCase();

    return options.filter((option) =>
        Object.values(option.searchField).some((label) => label.toLowerCase().includes(lowerSearchTerm)),
    );
}

function getRelationLabel(entityName: string): string {
    return i18n.t(`global.entities.${entityName}`, 2);
}

function onTechnicalNameChange(): void {
    emit('reset-errors');
}

ctDefinePublic({
    acl,
    propertyNames,
    locales,
    customFieldSetRelationRepository,
    selectedRelationEntityNames,
    relationEntityNames,
    onAddRelation,
    onRemoveRelation,
    searchRelationEntityNames,
    getRelationLabel,
    onTechnicalNameChange,
});

defineExpose({
    acl,
    propertyNames,
    locales,
    customFieldSetRelationRepository,
    selectedRelationEntityNames,
    relationEntityNames,
    onAddRelation,
    onRemoveRelation,
    searchRelationEntityNames,
    getRelationLabel,
    onTechnicalNameChange,
});
</script>
