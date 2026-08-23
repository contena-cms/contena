<template>
    <ct-block name="sw_organization_form">
        <div class="mt-organization-form">
            <ct-block name="sw_organization_form_fields">
                <div class="mt-organization-form__grid">
                    <mt-text-field
                        :model-value="organization.name"
                        name="ct-field--organization-name"
                        required
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelName')"
                        :placeholder="placeholder(organization, 'name', '')"
                        @update:model-value="onUpdateOrganization('name', $event)"
                    />

                    <mt-text-field
                        :model-value="organization.shortName"
                        name="ct-field--organization-short-name"
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelShortName')"
                        @update:model-value="onUpdateOrganization('shortName', $event)"
                    />

                    <mt-text-field
                        :model-value="organization.code"
                        name="ct-field--organization-code"
                        required
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelCode')"
                        @update:model-value="onUpdateOrganization('code', $event)"
                    />

                    <mt-entity-select
                        :model-value="organization.organizationUnitId"
                        entity="organization_unit"
                        label-property="name"
                        name="ct-field--organization-unit"
                        required
                        :criteria="organizationUnitCriteria"
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelOrganizationUnit')"
                        :placeholder="translate('ct-settings-organization.detail.placeholderOrganizationUnit')"
                        @update:model-value="onUpdateOrganization('organizationUnitId', $event)"
                    />

                    <mt-number-field
                        :model-value="organization.position"
                        name="ct-field--organization-position"
                        number-type="int"
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelPosition')"
                        @update:model-value="onUpdateOrganization('position', $event)"
                    />

                    <mt-switch
                        :model-value="organization.active"
                        name="ct-field--organization-active"
                        :disabled="disabled || undefined"
                        :label="translate('ct-settings-organization.detail.labelActive')"
                        @update:model-value="onUpdateOrganization('active', $event)"
                    />
                </div>
            </ct-block>

            <ct-block name="sw_organization_form_custom_fields">
                <div v-if="customFieldSets.length > 0" class="mt-organization-form__custom-fields">
                    <h3>{{ translate('ct-settings-organization.detail.customFieldsTitle') }}</h3>
                    <ct-custom-field-set-renderer :entity="organization" :sets="customFieldSets" :disabled="disabled" />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import { usePlaceholder } from 'src/app/composables/use-placeholder';

type Organization = Entity<'organization'>;

const { Criteria } = Contena.Data;
defineProps({
    organization: {
        type: Object as PropType<Organization>,
        required: true,
    },
    customFieldSets: {
        type: Array as PropType<unknown[]>,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits<{
    'update:organization': [path: string, value: unknown];
}>();
const { t } = useI18n();
const translate = t;

const { placeholder } = usePlaceholder();
const organizationUnitCriteria = new Criteria(1, 100);
organizationUnitCriteria.addFilter(Criteria.equals('active', true));
organizationUnitCriteria.addSorting(Criteria.sort('position', 'ASC'));

const onUpdateOrganization = (path: string, value: unknown): void => {
    emit('update:organization', path, value);
};

swDefinePublic({
    organizationUnitCriteria,
    placeholder,
    onUpdateOrganization,
});

defineExpose({ organizationUnitCriteria, placeholder, onUpdateOrganization });
</script>

<style scoped lang="scss">
.mt-organization-form {
    &__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--scale-size-16);
    }

    &__custom-fields {
        margin-top: var(--scale-size-24);

        h3 {
            margin-bottom: var(--scale-size-16);
        }
    }

    @media screen and (max-width: 760px) {
        &__grid {
            grid-template-columns: 1fr;
        }
    }
}
</style>
