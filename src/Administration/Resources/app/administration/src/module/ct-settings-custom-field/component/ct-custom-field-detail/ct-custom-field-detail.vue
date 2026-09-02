<template>
    <ct-block name="ct_custom_field_detail">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal class="ct-custom-field-detail" :title="modalTitle">
                <ct-block name="ct_custom_field_detail_modal">
                    <div class="ct-custom-field-detail__grid">
                        <ct-block name="ct_custom_field_detail_modal_type">
                            <mt-select
                                v-model="currentCustomField.config.customFieldType"
                                :label="$t('ct-settings-custom-field.customField.detail.labelCustomFieldType')"
                                :placeholder="$t('ct-settings-custom-field.customField.detail.placeholderCustomFieldType')"
                                class="ct-custom-field-detail__modal-type"
                                type="select"
                                :help-text="$t('ct-settings-custom-field.general.tooltipType')"
                                :disabled="!currentCustomField._isNew || !acl.can('custom_field.editor') || undefined"
                                :options="customFieldTypeOptions"
                            />
                        </ct-block>

                        <ct-block name="ct_custom_field_detail_modal_technical_name">
                            <mt-text-field
                                v-model="currentCustomField.name"
                                class="ct-custom-field-detail__technical-name"
                                :label="$t('ct-settings-custom-field.customField.detail.labelTechnicalName')"
                                :help-text="$t('ct-settings-custom-field.general.tooltipTechnicalName')"
                                :disabled="!currentCustomField._isNew || !acl.can('custom_field.editor') || undefined"
                                :error="currentCustomFieldNameError?.error"
                            />
                        </ct-block>

                        <ct-block name="ct_custom_field_detail_modal_position">
                            <mt-number-field
                                v-model="currentCustomField.config.customFieldPosition"
                                class="ct-custom-field-detail__modal-position"
                                :help-text="$t('ct-settings-custom-field.customField.detail.tooltipCustomFieldPosition')"
                                number-type="int"
                                :label="$t('ct-settings-custom-field.customField.detail.labelCustomFieldPosition')"
                                :disabled="!acl.can('custom_field.editor') || undefined"
                            />
                        </ct-block>

                        <ct-block name="ct_custom_field_detail_modal_allow_searchable">
                            <mt-switch
                                v-model="currentCustomField.includeInSearch"
                                bordered
                                :help-text="$t('ct-settings-custom-field.customField.detail.tooltipAllowSearchable')"
                                class="ct-custom-field-detail__allow-searchable"
                                :label="$t('ct-settings-custom-field.customField.detail.labelAllowSearchable')"
                                :disabled="!acl.can('custom_field.editor') || undefined"
                            />
                        </ct-block>
                    </div>

                    <ct-block name="ct_custom_field_detail_modal_render_component">
                        <div v-if="currentCustomField.config.customFieldType?.length > 0">
                            <component :is="renderComponentName" :current-custom-field="currentCustomField" :set="set" />
                        </div>
                    </ct-block>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_custom_field_detail_modal_footer">
                        <ct-block name="ct_custom_field_detail_modal_footer_cancel">
                            <mt-button size="small" variant="secondary" @click="onCancel">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="ct_custom_field_detail_modal_footer_save">
                            <mt-button
                                class="ct-custom-field-detail__footer-save"
                                variant="primary"
                                size="small"
                                :disabled="!canSave || !acl.can('custom_field.editor') || undefined"
                                @click="onSave"
                            >
                                {{ labelSaveButton }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref, toRef, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-custom-field-detail.scss';

interface CustomFieldConfig extends Record<string, unknown> {
    customFieldPosition?: number;
    customFieldType: string;
    entity?: string;
}

interface CustomField {
    _isNew?: boolean;
    config: CustomFieldConfig;
    includeInSearch?: boolean;
    name?: string;
    type?: string;
    getEntityName?: () => string;
}

interface CustomFieldSet {
    config?: {
        translated?: boolean;
    };
    name?: string;
}

interface CustomFieldTypeDefinition {
    config?: Record<string, unknown>;
    configRenderComponent?: string;
    type?: string;
}

interface CustomFieldDataProviderService {
    getTypes(): Record<string, CustomFieldTypeDefinition>;
}

// The argument name documents the injected validation contract.

type CustomFieldNameUnique = (customField: CustomField) => Promise<boolean>;

const props = defineProps({
    currentCustomField: {
        type: Object as PropType<CustomField>,
        required: true,
    },
    set: {
        type: Object as PropType<CustomFieldSet>,
        required: true,
    },
});

const emit = defineEmits<{
    'custom-field-edit-cancel': [customField: CustomField];
    'custom-field-edit-save': [customField: CustomField];
}>();
const currentCustomField = toRef(props, 'currentCustomField');
const set = toRef(props, 'set');
const { createNotificationError } = useNotification();

const customFieldDataProviderService = inject<CustomFieldDataProviderService>('customFieldDataProviderService');
const CtCustomFieldListIsCustomFieldNameUnique = inject<CustomFieldNameUnique>('CtCustomFieldListIsCustomFieldNameUnique');
const acl = inject<AclService>('acl');
const i18n = useI18n();

if (!customFieldDataProviderService || !CtCustomFieldListIsCustomFieldNameUnique || !acl) {
    throw new Error('Custom Field detail services are unavailable.');
}

const fieldTypes = ref<Record<string, CustomFieldTypeDefinition>>({});
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
const canSave = computed(() => currentCustomField.value.config.customFieldType);
const renderComponentName = computed(
    () => fieldTypes.value[currentCustomField.value.config.customFieldType]?.configRenderComponent,
);
const modalTitle = computed(() => {
    if (currentCustomField.value._isNew) {
        return i18n.t('ct-settings-custom-field.customField.detail.titleNewCustomField');
    }

    return i18n.t('ct-settings-custom-field.customField.detail.titleEditCustomField');
});
const labelSaveButton = computed(() => {
    if (currentCustomField.value._isNew) {
        return i18n.t('global.default.add');
    }

    return i18n.t('ct-settings-custom-field.customField.detail.buttonEditApply');
});
const customFieldTypeOptions = computed(() => {
    return Object.keys(fieldTypes.value).map((key) => ({
        id: key,
        value: key,
        label: i18n.t(`ct-settings-custom-field.types.${key}`),
    }));
});
const currentCustomFieldNameError = computed(() => {
    const entity = currentCustomField.value;

    if (typeof entity.getEntityName !== 'function') {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});

function createdComponent(): void {
    fieldTypes.value = customFieldDataProviderService.getTypes();

    if (!currentCustomField.value.config) {
        currentCustomField.value.config = { customFieldType: '' };
    }

    if (!Object.hasOwn(currentCustomField.value.config, 'customFieldType')) {
        currentCustomField.value.config.customFieldType = '';
    }

    if (!currentCustomField.value.name) {
        currentCustomField.value.name = `${set.value.name?.toLowerCase() ?? ''}_`;
    }

    if (!Object.hasOwn(currentCustomField.value.config, 'customFieldPosition')) {
        currentCustomField.value.config.customFieldPosition = 1;
    }

    if (!currentCustomField.value.includeInSearch) {
        currentCustomField.value.includeInSearch = false;
    }
}

function onModalChange(isOpen: boolean): void {
    if (!isOpen) {
        onCancel();
    }
}

function onCancel(): void {
    emit('custom-field-edit-cancel', currentCustomField.value);
}

function onSave(): void {
    applyTypeConfiguration();

    if (!currentCustomField.value._isNew) {
        emit('custom-field-edit-save', currentCustomField.value);

        return;
    }

    if (currentCustomField.value.config.customFieldType === 'entity' && !currentCustomField.value.config.entity) {
        createEntityTypeRequiredNotification();

        return;
    }

    void CtCustomFieldListIsCustomFieldNameUnique(currentCustomField.value).then((isUnique) => {
        if (isUnique) {
            emit('custom-field-edit-save', currentCustomField.value);

            return;
        }

        createNameNotUniqueNotification();
    });
}

function createNameNotUniqueNotification(): void {
    createNotificationError({
        title: i18n.t('global.default.error'),
        message: i18n.t('ct-settings-custom-field.set.detail.messageNameNotUnique'),
    });
}

function createEntityTypeRequiredNotification(): void {
    createNotificationError({
        title: i18n.t('global.default.error'),
        message: i18n.t('ct-settings-custom-field.set.detail.entityTypeRequired'),
    });
}

function applyTypeConfiguration(): void {
    const customFieldType = currentCustomField.value.config.customFieldType;
    const fieldType = fieldTypes.value[customFieldType];

    if (!fieldType) {
        return;
    }

    if (!currentCustomField.value.type) {
        currentCustomField.value.type = fieldType.type ?? customFieldType;
    }

    currentCustomField.value.config = {
        customFieldType,
        ...fieldType.config,
        ...currentCustomField.value.config,
    };
}

createdComponent();

ctDefinePublic({
    customFieldDataProviderService,
    CtCustomFieldListIsCustomFieldNameUnique,
    acl,
    fieldTypes,
    locales,
    canSave,
    renderComponentName,
    modalTitle,
    labelSaveButton,
    customFieldTypeOptions,
    currentCustomFieldNameError,
    createdComponent,
    onModalChange,
    onCancel,
    onSave,
    createNameNotUniqueNotification,
    createEntityTypeRequiredNotification,
    applyTypeConfiguration,
});

defineExpose({
    customFieldDataProviderService,
    CtCustomFieldListIsCustomFieldNameUnique,
    acl,
    fieldTypes,
    locales,
    canSave,
    renderComponentName,
    modalTitle,
    labelSaveButton,
    customFieldTypeOptions,
    currentCustomFieldNameError,
    createdComponent,
    onModalChange,
    onCancel,
    onSave,
    createNameNotUniqueNotification,
    createEntityTypeRequiredNotification,
    applyTypeConfiguration,
});
</script>
