<template>
    <!-- DAL entities are intentionally edited in place before the repository save. -->
    <!-- eslint-disable vue/no-mutating-props -->
    <ct-block name="ct_member_address_form">
        <div class="ct-member-address-form">
            <ct-block name="ct_member_address_form_contact">
                <div class="ct-member-address-form__grid">
                    <mt-text-field
                        v-model="address.title"
                        :label="t('ct-member.addressForm.labelTitle')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.firstName"
                        required
                        :label="t('ct-member.addressForm.labelFirstName')"
                        :error="getApiError('firstName')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.lastName"
                        required
                        :label="t('ct-member.addressForm.labelLastName')"
                        :error="getApiError('lastName')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.phoneNumber"
                        :label="t('ct-member.addressForm.labelPhoneNumber')"
                        :disabled="disabled || undefined"
                    />
                </div>
            </ct-block>

            <ct-block name="ct_member_address_form_location">
                <div class="ct-member-address-form__grid">
                    <mt-entity-select
                        v-model="address.countryId"
                        entity="country"
                        required
                        :label="t('ct-member.addressForm.labelCountry')"
                        :error="getApiError('countryId')"
                        :disabled="disabled || undefined"
                        @update:model-value="onCountryChange"
                    />
                    <mt-entity-select
                        v-model="provinceId"
                        entity="region"
                        label-property="name"
                        :label="t('ct-member.addressForm.labelProvince')"
                        :criteria="provinceCriteria"
                        :disabled="!address.countryId || disabled || undefined"
                        @update:model-value="onProvinceChange"
                    />
                    <mt-entity-select
                        v-model="cityRegionId"
                        entity="region"
                        label-property="name"
                        :label="t('ct-member.addressForm.labelCityRegion')"
                        :criteria="cityRegionCriteria"
                        :disabled="!provinceId || disabled || undefined"
                        @update:model-value="onCityRegionChange"
                    />
                    <mt-entity-select
                        v-model="districtId"
                        entity="region"
                        label-property="name"
                        :label="t('ct-member.addressForm.labelDistrict')"
                        :criteria="districtCriteria"
                        :disabled="!cityRegionId || disabled || undefined"
                        @update:model-value="onDistrictChange"
                    />
                    <mt-text-field
                        v-model="address.street"
                        required
                        :label="t('ct-member.addressForm.labelStreet')"
                        :error="getApiError('street')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.zipcode"
                        :label="t('ct-member.addressForm.labelZipCode')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.city"
                        required
                        :label="t('ct-member.addressForm.labelCity')"
                        :error="getApiError('city')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.additionalAddressLine1"
                        :label="t('ct-member.addressForm.labelAdditionalAddressLine1')"
                        :disabled="disabled || undefined"
                    />
                    <mt-text-field
                        v-model="address.additionalAddressLine2"
                        :label="t('ct-member.addressForm.labelAdditionalAddressLine2')"
                        :disabled="disabled || undefined"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, Repository */
/* global Entity, Repository */
import type { PropType } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import './ct-member-address-form.scss';

const props = defineProps({
    member: { type: Object as PropType<Entity<'member'>>, required: true },
    address: { type: Object as PropType<Entity<'member_address'>>, required: true },
    disabled: { type: Boolean, default: false },
});
const { t } = useI18n();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) throw new Error('The repository factory is unavailable.');
const regionRepository: Repository<'region'> = repositoryFactory.create('region');
const provinceId = ref<string | null>(null);
const cityRegionId = ref<string | null>(null);
const districtId = ref<string | null>(null);

const regionCriteria = (parentId: string | null, countryId = props.address.countryId) => {
    const criteria = new Contena.Data.Criteria(1, 100);
    criteria.addFilter(Contena.Data.Criteria.equals('countryId', countryId));
    criteria.addFilter(Contena.Data.Criteria.equals('parentId', parentId));
    criteria.addFilter(Contena.Data.Criteria.equals('active', true));
    criteria.addSorting(Contena.Data.Criteria.sort('position', 'ASC', true));
    criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
    return criteria;
};
const provinceCriteria = computed(() => regionCriteria(null));
const cityRegionCriteria = computed(() => regionCriteria(provinceId.value));
const districtCriteria = computed(() => regionCriteria(cityRegionId.value));
const getApiError = (property: string): unknown => Contena.Store.get('error').getApiError(props.address, property);
const updateAddressRegion = (): void => {
    // eslint-disable-next-line vue/no-mutating-props
    props.address.regionId = districtId.value ?? cityRegionId.value ?? provinceId.value ?? undefined;
};
const loadRegionPath = async (): Promise<void> => {
    if (!props.address.regionId) {
        provinceId.value = null;
        cityRegionId.value = null;
        districtId.value = null;
        return;
    }

    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAssociation('parent.parent');
    const region = await regionRepository.get(props.address.regionId, Contena.Context.api, criteria);
    const path = [
        region.parent?.parent,
        region.parent,
        region,
    ].filter(Boolean) as Entity<'region'>[];
    provinceId.value = path[0]?.id ?? null;
    cityRegionId.value = path[1]?.id ?? null;
    districtId.value = path[2]?.id ?? null;
};
const onCountryChange = (): void => {
    provinceId.value = null;
    cityRegionId.value = null;
    districtId.value = null;
    // eslint-disable-next-line vue/no-mutating-props
    props.address.regionId = undefined;
};
const onProvinceChange = (): void => {
    cityRegionId.value = null;
    districtId.value = null;
    updateAddressRegion();
};
const onCityRegionChange = (): void => {
    districtId.value = null;
    updateAddressRegion();
};
const onDistrictChange = (): void => updateAddressRegion();

watch(
    () => props.address.id,
    () => void loadRegionPath(),
    { immediate: true },
);

ctDefinePublic({
    provinceId,
    cityRegionId,
    districtId,
    provinceCriteria,
    cityRegionCriteria,
    districtCriteria,
    getApiError,
    loadRegionPath,
    onCountryChange,
    onProvinceChange,
    onCityRegionChange,
    onDistrictChange,
});

defineExpose({
    provinceId,
    cityRegionId,
    districtId,
    provinceCriteria,
    cityRegionCriteria,
    districtCriteria,
    getApiError,
    loadRegionPath,
    onCountryChange,
    onProvinceChange,
    onCityRegionChange,
    onDistrictChange,
});
</script>
