<template>
    <!-- eslint-disable vue/no-unused-vars -->
    <ct-block name="ct_channel_detail_domains">
        <mt-card class="ct-channel-detail-domains" :title="t('ct-channel.detail.titleDomains')" :is-loading="isLoading">
            <template #toolbar>
                <mt-button variant="secondary" size="small" :disabled="disabled || undefined" @click="openCreateModal">
                    <mt-icon name="regular-plus-circle" size="16px" />
                    {{ t('ct-channel.detail.buttonAddDomain') }}
                </mt-button>
            </template>

            <mt-data-table
                :data-source="domains"
                :columns="columns"
                :disable-search="true"
                :disable-delete="disabled"
                :disable-edit="true"
                :additional-context-buttons="additionalContextButtons"
                :current-page="1"
                :pagination-limit="25"
                :pagination-total-items="domains.length"
                :caption="t('ct-channel.detail.titleDomains')"
                @item-delete="onItemDelete"
                @context-select="onContextSelect"
            >
                <template #column-url="{ data }">
                    {{ data.url }}
                </template>
                <template #column-language="{ data }">
                    {{ data.language?.translated?.name || data.language?.name }}
                </template>
                <template #column-snippetSet="{ data }">
                    {{ data.snippetSet?.name }}
                </template>
            </mt-data-table>
        </mt-card>

        <mt-modal-root v-if="currentDomain" :is-open="true" @change="onDomainModalChange">
            <mt-modal :title="domainModalTitle" width="m">
                <div class="ct-channel-detail-domains__form">
                    <mt-banner v-if="duplicateUrl" variant="critical">
                        {{ t('ct-channel.detail.duplicateDomain') }}
                    </mt-banner>
                    <mt-url-field
                        v-model="currentDomain.url"
                        required
                        omit-url-hash
                        omit-url-search
                        :label="t('ct-channel.detail.labelDomainUrl')"
                        @update:model-value="duplicateUrl = false"
                    />
                    <mt-select
                        v-model="currentDomain.languageId"
                        required
                        :options="channel.languages || []"
                        label-property="name"
                        value-property="id"
                        :label="t('ct-channel.detail.labelDomainLanguage')"
                    />
                    <mt-entity-select
                        v-model="currentDomain.snippetSetId"
                        entity="snippet_set"
                        required
                        :label="t('ct-channel.detail.labelDomainSnippetSet')"
                        @item-add="onSnippetSetSelect"
                    />
                </div>
                <template #footer>
                    <mt-button variant="secondary" @click="closeDomainModal">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="primary" :disabled="domainSaveDisabled || undefined" @click="saveDomain">
                        {{ t('global.default.save') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>

        <mt-modal-root v-if="domainToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('ct-channel.detail.deleteDomainTitle')" width="s">
                {{ t('ct-channel.detail.deleteDomainText', { url: domainToDelete.url }) }}
                <template #footer>
                    <mt-button variant="secondary" @click="domainToDelete = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" @click="deleteDomain">
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import './ct-channel-detail-domains.scss';

type DomainBackup = Pick<
    Entity<'channel_domain'>,
    'url' | 'languageId' | 'snippetSetId' | 'language' | 'snippetSet' | 'hreflangUseOnlyLocale'
>;
type Column = {
    property: string;
    label: string;
    position: number;
    renderer: 'text';
    sortable?: boolean;
    width?: number;
};

const props = defineProps({
    channel: { type: Object as PropType<Entity<'channel'>>, required: true },
    disabled: { type: Boolean, default: false },
    isLoading: { type: Boolean, default: false },
});
const { t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) throw new Error('The repository factory is unavailable.');

const domains = computed(() => Array.from(props.channel.domains ?? []));
const columns: Column[] = [
    { property: 'url', label: t('ct-channel.detail.columnDomainUrl'), position: 100, renderer: 'text' },
    {
        property: 'language',
        label: t('ct-channel.detail.columnDomainLanguage'),
        position: 200,
        renderer: 'text',
        width: 180,
    },
    {
        property: 'snippetSet',
        label: t('ct-channel.detail.columnDomainSnippetSet'),
        position: 300,
        renderer: 'text',
        width: 180,
    },
];
const additionalContextButtons = computed(() => [
    { key: 'edit', label: t('ct-channel.detail.buttonEditDomain') },
]);
const currentDomain = ref<Entity<'channel_domain'> | null>(null);
const domainToDelete = ref<Entity<'channel_domain'> | null>(null);
const currentDomainBackup = ref<DomainBackup | null>(null);
const isEditing = ref(false);
const duplicateUrl = ref(false);
const domainRepository = computed(() => repositoryFactory.create('channel_domain', props.channel.domains?.source));
const domainModalTitle = computed(() =>
    t(isEditing.value ? 'ct-channel.detail.domainModalEditTitle' : 'ct-channel.detail.domainModalCreateTitle'),
);
const domainSaveDisabled = computed(
    () =>
        !currentDomain.value?.url || !currentDomain.value.languageId || !currentDomain.value.snippetSetId || props.disabled,
);
const backup = (domain: Entity<'channel_domain'>): DomainBackup => ({
    url: domain.url,
    languageId: domain.languageId,
    snippetSetId: domain.snippetSetId,
    language: domain.language,
    snippetSet: domain.snippetSet,
    hreflangUseOnlyLocale: domain.hreflangUseOnlyLocale,
});
const openCreateModal = (defaults: Partial<Entity<'channel_domain'>> = {}): void => {
    const domain = domainRepository.value.create(Contena.Context.api);
    domain.channelId = props.channel.id;
    domain.hreflangUseOnlyLocale = false;
    const languageId = defaults.languageId ?? props.channel.languageId;
    const defaultLanguage =
        props.channel.languages?.get(languageId) ?? (!defaults.languageId ? props.channel.languages?.first() : null);
    if (defaultLanguage) {
        domain.languageId = defaultLanguage.id;
        domain.language = defaultLanguage;
    } else if (languageId) {
        domain.languageId = languageId;
        domain.language = null;
    }
    Object.assign(domain, defaults);
    currentDomain.value = domain;
    currentDomainBackup.value = backup(domain);
    isEditing.value = false;
    duplicateUrl.value = false;
};
const openEditModal = (domain: Entity<'channel_domain'>): void => {
    currentDomain.value = domain;
    currentDomainBackup.value = backup(domain);
    isEditing.value = true;
    duplicateUrl.value = false;
};
const onItemDelete = (domain: Entity<'channel_domain'>): void => {
    if (!props.disabled) domainToDelete.value = domain;
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'channel_domain'> }): void => {
    if (!props.disabled && key === 'edit') openEditModal(data);
};
const closeDomainModal = (): void => {
    if (isEditing.value && currentDomain.value && currentDomainBackup.value) {
        Object.assign(currentDomain.value, currentDomainBackup.value);
    }
    currentDomain.value = null;
    currentDomainBackup.value = null;
    isEditing.value = false;
    duplicateUrl.value = false;
};
const onDomainModalChange = (open: boolean): void => {
    if (!open) closeDomainModal();
};
const domainExistsInDatabase = async (url: string, domainId: string): Promise<boolean> => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addFilter(Contena.Data.Criteria.equals('url', url));
    const result = await repositoryFactory.create('channel_domain').search(criteria, Contena.Context.api);
    return result.some((domain) => domain.id !== domainId && domain.channelId !== props.channel.id);
};
const saveDomain = async (): Promise<void> => {
    const domain = currentDomain.value;
    if (!domain || domainSaveDisabled.value) return;
    const localDuplicate = props.channel.domains?.some((item) => item.id !== domain.id && item.url === domain.url);
    duplicateUrl.value = Boolean(localDuplicate) || (await domainExistsInDatabase(domain.url, domain.id));
    if (duplicateUrl.value) return;

    domain.language = props.channel.languages?.get(domain.languageId) ?? domain.language;
    if (!isEditing.value && !props.channel.domains?.has(domain.id)) props.channel.domains?.add(domain);
    currentDomain.value = null;
    currentDomainBackup.value = null;
    isEditing.value = false;
};
const onSnippetSetSelect = (snippetSet: Entity<'snippet_set'>): void => {
    if (currentDomain.value) currentDomain.value.snippetSet = snippetSet;
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) domainToDelete.value = null;
};
const deleteDomain = (): void => {
    if (domainToDelete.value) props.channel.domains?.remove(domainToDelete.value.id);
    domainToDelete.value = null;
};

ctDefinePublic({
    domains,
    columns,
    currentDomain,
    domainToDelete,
    currentDomainBackup,
    isEditing,
    duplicateUrl,
    domainRepository,
    domainModalTitle,
    domainSaveDisabled,
    openCreateModal,
    openEditModal,
    closeDomainModal,
    onDomainModalChange,
    domainExistsInDatabase,
    saveDomain,
    onSnippetSetSelect,
    onDeleteModalChange,
    deleteDomain,
});

defineExpose({ currentDomain, domainToDelete, openCreateModal, openEditModal, saveDomain, deleteDomain });
</script>
