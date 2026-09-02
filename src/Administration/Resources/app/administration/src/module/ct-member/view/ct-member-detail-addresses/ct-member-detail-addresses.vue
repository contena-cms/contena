<template>
    <ct-block name="ct_member_detail_addresses">
        <mt-card
            position-identifier="ct-member-detail-addresses"
            :title="t('ct-member.detailAddresses.title')"
            :is-loading="isLoading"
        >
            <template #toolbar>
                <mt-button
                    variant="secondary"
                    :disabled="!memberEditMode || !canEdit || undefined"
                    @click="onCreateNewAddress"
                >
                    {{ t('ct-member.detailAddresses.buttonAddAddress') }}
                </mt-button>
            </template>

            <ct-block name="ct_member_detail_addresses_grid">
                <mt-data-table
                    v-if="addresses.length > 0"
                    :data-source="addresses"
                    :columns="columns"
                    :is-loading="isLoading"
                    :disable-edit="true"
                    :disable-delete="!canDelete || !memberEditMode"
                    :additional-context-buttons="additionalContextButtons"
                    @item-delete="onItemDelete"
                    @context-select="onContextSelect"
                >
                    <template #column-name="{ data }">
                        <mt-link v-if="memberEditMode" as="button" @click="onEditAddress(data.id)">
                            {{ data.firstName }} {{ data.lastName }}
                        </mt-link>
                        <span v-else>{{ data.firstName }} {{ data.lastName }}</span>
                    </template>
                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #column-country="{ data }">
                        {{ data.country?.translated?.name || data.country?.name || '-' }}
                    </template>
                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                    <template #column-region="{ data }">
                        {{ data.region?.translated?.name || data.region?.name || '-' }}
                    </template>
                </mt-data-table>

                <mt-empty-state
                    v-else-if="!isLoading"
                    icon="regular-map"
                    :headline="t('ct-member.detailAddresses.emptyState')"
                />
            </ct-block>
        </mt-card>

        <mt-modal-root v-if="currentAddress" :is-open="true" @change="onAddressModalChange">
            <mt-modal :title="t('ct-member.detailAddresses.title')" width="l">
                <ct-member-address-form :member="member" :address="currentAddress" />
                <template #footer>
                    <mt-button variant="secondary" @click="currentAddress = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="primary" :is-loading="isSaving" @click="onSaveAddress">
                        {{ t('global.default.save') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>

        <mt-modal-root v-if="addressToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.warning')" width="s">
                {{ t('ct-member.detailAddresses.textDeleteAddressConfirm') }}
                <template #footer>
                    <mt-button variant="secondary" @click="addressToDelete = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" :is-loading="isSaving" @click="onConfirmDeleteAddress">
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, Repository */
/* global Entity, Repository */
import type { PropType } from 'vue';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-member-detail-addresses.scss';

type Column = { property: string; label: string; position: number; renderer: 'text'; sortable?: boolean; width?: number };
const props = defineProps({
    member: { type: Object as PropType<Entity<'member'>>, required: true },
    memberEditMode: { type: Boolean, required: true },
});
const { t } = useI18n();
const { createNotificationError } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) throw new Error('The Member address services are unavailable.');
const addressRepository: Repository<'member_address'> = repositoryFactory.create('member_address');
const addresses = ref<Entity<'member_address'>[]>([]);
const currentAddress = ref<Entity<'member_address'> | null>(null);
const addressToDelete = ref<Entity<'member_address'> | null>(null);
const isLoading = ref(false);
const isSaving = ref(false);
const canEdit = computed(() => acl.can('member.editor'));
const canDelete = computed(() => acl.can('member.editor'));
const columns: Column[] = [
    { property: 'name', label: t('ct-member.detailAddresses.columnName'), position: 100, renderer: 'text', width: 200 },
    {
        property: 'street',
        label: t('ct-member.detailAddresses.columnStreet'),
        position: 300,
        renderer: 'text',
        width: 220,
    },
    {
        property: 'zipcode',
        label: t('ct-member.detailAddresses.columnZipCode'),
        position: 400,
        renderer: 'text',
        width: 120,
    },
    { property: 'city', label: t('ct-member.detailAddresses.columnCity'), position: 500, renderer: 'text', width: 160 },
    {
        property: 'country',
        label: t('ct-member.detailAddresses.columnCountry'),
        position: 600,
        renderer: 'text',
        width: 160,
    },
    {
        property: 'region',
        label: t('ct-member.detailAddresses.columnRegion'),
        position: 700,
        renderer: 'text',
        width: 160,
    },
];
const additionalContextButtons = computed(() => {
    const buttons = [];
    if (canEdit.value) buttons.push({ key: 'edit', label: t('global.default.edit') });
    if (canEdit.value) buttons.push({ key: 'duplicate', label: t('global.default.duplicate') });
    return buttons;
});
const getCriteria = () => {
    const criteria = new Contena.Data.Criteria(1, 100);
    criteria.addFilter(Contena.Data.Criteria.equals('memberId', props.member.id));
    criteria.addAssociation('country');
    criteria.addAssociation('region.parent.parent');
    criteria.addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));
    return criteria;
};
const loadAddresses = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await addressRepository.search(getCriteria(), Contena.Context.api);
        addresses.value = Array.from(result);
    } finally {
        isLoading.value = false;
    }
};
const createAddress = (): Entity<'member_address'> => {
    const entity = addressRepository.create(Contena.Context.api);
    entity.memberId = props.member.id;
    return entity;
};
const onCreateNewAddress = (): void => {
    currentAddress.value = createAddress();
};
const onEditAddress = async (id: string): Promise<void> => {
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAssociation('country');
    criteria.addAssociation('region.parent.parent');
    currentAddress.value = await addressRepository.get(id, Contena.Context.api, criteria);
};
const onDuplicateAddress = (id: string): void => {
    const source = addresses.value.find((address) => address.id === id);
    if (!source) return;
    const duplicate = createAddress();
    const fields: Array<keyof Entity<'member_address'>> = [
        'countryId',
        'regionId',
        'firstName',
        'lastName',
        'zipcode',
        'city',
        'street',
        'title',
        'phoneNumber',
        'additionalAddressLine1',
        'additionalAddressLine2',
        'customFields',
    ];
    fields.forEach((field) => {
        duplicate[field] = source[field];
    });
    currentAddress.value = duplicate;
};
const onItemDelete = (address: Entity<'member_address'>): void => {
    if (props.memberEditMode && canDelete.value) addressToDelete.value = address;
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'member_address'> }): void => {
    if (!props.memberEditMode || !canEdit.value) return;
    if (key === 'edit') void onEditAddress(data.id);
    if (key === 'duplicate') onDuplicateAddress(data.id);
};
const validateAddress = (): boolean => {
    if (!currentAddress.value) return false;
    const required = [
        currentAddress.value.firstName,
        currentAddress.value.lastName,
        currentAddress.value.countryId,
        currentAddress.value.street,
        currentAddress.value.city,
    ];
    const valid = required.every((value) => typeof value === 'string' && value.trim() !== '');
    if (!valid) createNotificationError({ message: t('ct-member.detailAddresses.saveError') });
    return valid;
};
const onSaveAddress = async (): Promise<void> => {
    if (!currentAddress.value || !validateAddress()) return;
    isSaving.value = true;
    try {
        await addressRepository.save(currentAddress.value, Contena.Context.api);
        currentAddress.value = null;
        await loadAddresses();
    } catch {
        createNotificationError({ message: t('ct-member.detailAddresses.saveError') });
    } finally {
        isSaving.value = false;
    }
};
const onAddressModalChange = (open: boolean): void => {
    if (!open) currentAddress.value = null;
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) addressToDelete.value = null;
};
const onConfirmDeleteAddress = async (): Promise<void> => {
    if (!addressToDelete.value) return;
    isSaving.value = true;
    try {
        await addressRepository.delete(addressToDelete.value.id, Contena.Context.api);
        addressToDelete.value = null;
        await loadAddresses();
    } catch {
        createNotificationError({ message: t('ct-member.detailAddresses.deleteError') });
    } finally {
        isSaving.value = false;
    }
};

watch(
    () => props.member.id,
    () => void loadAddresses(),
    { immediate: true },
);

ctDefinePublic({
    addresses,
    currentAddress,
    addressToDelete,
    isLoading,
    isSaving,
    columns,
    additionalContextButtons,
    canEdit,
    canDelete,
    addressRepository,
    loadAddresses,
    createAddress,
    onCreateNewAddress,
    onEditAddress,
    onDuplicateAddress,
    onItemDelete,
    onContextSelect,
    validateAddress,
    onSaveAddress,
    onAddressModalChange,
    onDeleteModalChange,
    onConfirmDeleteAddress,
});

defineExpose({
    addresses,
    currentAddress,
    addressToDelete,
    isLoading,
    isSaving,
    columns,
    additionalContextButtons,
    canEdit,
    canDelete,
    addressRepository,
    loadAddresses,
    createAddress,
    onCreateNewAddress,
    onEditAddress,
    onDuplicateAddress,
    onItemDelete,
    onContextSelect,
    validateAddress,
    onSaveAddress,
    onAddressModalChange,
    onDeleteModalChange,
    onConfirmDeleteAddress,
});
</script>
