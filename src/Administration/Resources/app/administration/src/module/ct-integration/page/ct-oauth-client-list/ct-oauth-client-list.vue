<template>
    <ct-block name="ct_oauth_client_list">
        <ct-page class="ct-oauth-client-list">
            <template #smart-bar-header>
                <h2>
                    <span>{{ t('ct-settings.index.title') }}</span>
                    <mt-icon name="regular-chevron-right-xs" size="12px" />
                    <span>{{ t('ct-integration.general.headlineIntegrations') }}</span>
                    <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                </h2>
            </template>

            <template #smart-bar-actions>
                <mt-button variant="primary" :disabled="!canCreate || isLoading || isSaving || undefined" @click="onCreate">
                    {{ t('ct-oauth-client.create') }}
                </mt-button>
            </template>

            <template #content>
                <ct-block name="ct_oauth_client_list_content">
                    <mt-banner variant="info" class="ct-oauth-client-list__notice">
                        {{ t('ct-oauth-client.permissionsHint') }}
                    </mt-banner>

                    <mt-data-table
                        :caption="t('ct-oauth-client.title')"
                        :data-source="clients"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        disable-search
                        disable-edit
                        :disable-delete="!canDelete"
                        :additional-context-buttons="contextButtons"
                        @reload="getList"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @sort-change="onSort"
                        @context-select="onContextSelect"
                    >
                        <template #column-name="{ data }">
                            <button class="ct-oauth-client-list__link" type="button" @click="onEdit(data)">
                                {{ data.name }}
                            </button>
                        </template>
                        <template #column-id="{ data }">
                            <span class="ct-oauth-client-list__client-id">oauth-{{ data.id }}</span>
                        </template>
                        <template #column-active="{ data }">
                            <mt-badge :variant="data.active ? 'positive' : 'neutral'">
                                {{ t(data.active ? 'ct-oauth-client.active' : 'ct-oauth-client.inactive') }}
                            </mt-badge>
                        </template>
                    </mt-data-table>

                    <mt-empty-state
                        v-if="!isLoading && clients.length === 0"
                        icon="regular-key"
                        :headline="t('ct-oauth-client.emptyTitle')"
                        :description="t('ct-oauth-client.emptyDescription')"
                    />
                </ct-block>
            </template>
        </ct-page>

        <mt-modal-root v-if="currentClient" :is-open="true" @change="onModalChange">
            <mt-modal :title="isNew ? t('ct-oauth-client.create') : currentClient.name" width="m">
                <mt-text-field
                    v-model="currentClient.name"
                    :label="t('ct-oauth-client.name')"
                    :disabled="!canSave || isSaving || undefined"
                    required
                />
                <mt-text-field
                    :model-value="`oauth-${currentClient.id}`"
                    :label="t('ct-oauth-client.clientId')"
                    :help-text="t('ct-oauth-client.clientIdHelp')"
                    disabled
                    copyable
                />
                <mt-textarea
                    v-model="redirectUrisText"
                    class="ct-oauth-client-list__redirects"
                    :label="t('ct-oauth-client.redirectUris')"
                    :help-text="t('ct-oauth-client.redirectHelp')"
                    :disabled="!canSave || isSaving || undefined"
                    placeholder="https://example.com/callback"
                    required
                />
                <mt-switch
                    v-model="currentClient.active"
                    :label="t('ct-oauth-client.active')"
                    :help-text="t('ct-oauth-client.activeHelp')"
                    :disabled="!canSave || isSaving || undefined"
                />
                <mt-banner variant="attention">
                    {{ t('ct-oauth-client.permissionsHint') }}
                </mt-banner>

                <template #footer>
                    <mt-button variant="secondary" :disabled="isSaving || undefined" @click="onClose">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        variant="primary"
                        :disabled="!canSave || isSaving || undefined"
                        :is-loading="isSaving"
                        @click="onSave"
                    >
                        {{ t('global.default.save') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>

        <mt-modal-root v-if="deleteClient" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.delete')" width="s">
                {{ t('ct-oauth-client.deleteConfirm', { name: deleteClient.name }) }}
                <template #footer>
                    <mt-button variant="secondary" :disabled="isSaving || undefined" @click="deleteClient = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        variant="critical"
                        :disabled="!canDelete || isSaving || undefined"
                        :is-loading="isSaving"
                        @click="onDelete"
                    >
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed, inject, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { useNotification } from 'src/app/composables/use-notification';
import './ct-oauth-client-list.scss';

type SortDirection = 'ASC' | 'DESC';
type Column = { property: string; label: string; position: number; renderer: 'text'; sortable?: boolean; width?: number };
type ContextButton = { action: string; label: string; variant?: 'critical' };

const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) {
    throw new Error('The OAuth client list services are unavailable.');
}

const repository = repositoryFactory.create('oauth_client');
const clients = ref<Entity<'oauth_client'>[]>([]);
const currentClient = ref<Entity<'oauth_client'> | null>(null);
const deleteClient = ref<Entity<'oauth_client'> | null>(null);
const redirectUrisText = ref('');
const isNew = ref(false);
const isLoading = ref(false);
const isSaving = ref(false);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const sortBy = ref('name');
const sortDirection = ref<SortDirection>('ASC');

const canCreate = computed(() => acl.can('oauth_client.creator'));
const canDelete = computed(() => acl.can('oauth_client.deleter'));
const canSave = computed(() => acl.can(isNew.value ? 'oauth_client.creator' : 'oauth_client.editor'));
const columns: Column[] = [
    { property: 'name', label: t('ct-oauth-client.name'), position: 100, renderer: 'text', sortable: true, width: 360 },
    { property: 'id', label: t('ct-oauth-client.clientId'), position: 200, renderer: 'text', width: 360 },
    { property: 'active', label: t('ct-oauth-client.active'), position: 300, renderer: 'text', width: 160 },
];
const contextButtons: ContextButton[] = [
    { action: 'edit', label: t('ct-oauth-client.details') },
    { action: 'delete', label: t('global.default.delete'), variant: 'critical' },
];

async function getList(): Promise<void> {
    isLoading.value = true;
    try {
        const criteria = new Contena.Data.Criteria(page.value, limit.value);
        criteria.addSorting(Contena.Data.Criteria.sort('name', sortDirection.value));
        const result = await repository.search(criteria, Contena.Context.api);
        clients.value = [...result];
        total.value = result.total ?? 0;
    } catch {
        createNotificationError({ message: t('ct-oauth-client.loadError') });
    } finally {
        isLoading.value = false;
    }
}

function onPageChange(value: number): void {
    page.value = value;
    void getList();
}

function onLimitChange(value: number): void {
    limit.value = value;
    page.value = 1;
    void getList();
}

function onSort({ sortBy: property, sortDirection: direction }: { sortBy: string; sortDirection: SortDirection }): void {
    sortBy.value = property;
    sortDirection.value = direction;
    void getList();
}

function onContextSelect({ action, item }: { action: string; item: Entity<'oauth_client'> }): void {
    if (action === 'edit') {
        void onEdit(item);
    }
    if (action === 'delete' && canDelete.value) {
        deleteClient.value = item;
    }
}

function onCreate(): void {
    if (!canCreate.value || isLoading.value || isSaving.value) return;
    currentClient.value = repository.create(Contena.Context.api);
    currentClient.value.active = true;
    currentClient.value.redirectUris = [];
    redirectUrisText.value = '';
    isNew.value = true;
}

async function onEdit(client: Entity<'oauth_client'>): Promise<void> {
    if (!acl.can('oauth_client.viewer') || isLoading.value) return;
    isLoading.value = true;
    try {
        currentClient.value = await repository.get(client.id, Contena.Context.api);
        redirectUrisText.value = currentClient.value?.redirectUris.join('\n') ?? '';
        isNew.value = false;
    } catch {
        createNotificationError({ message: t('ct-oauth-client.loadError') });
    } finally {
        isLoading.value = false;
    }
}

function onClose(): void {
    if (!isSaving.value) currentClient.value = null;
}

function onModalChange(open: boolean): void {
    if (!open) onClose();
}

async function onSave(): Promise<void> {
    if (!currentClient.value || !canSave.value || isSaving.value) return;
    isSaving.value = true;
    currentClient.value.redirectUris = redirectUrisText.value.split(/\r?\n/).filter((uri) => uri !== '');
    try {
        await repository.save(currentClient.value, Contena.Context.api);
        createNotificationSuccess({ message: t('ct-oauth-client.saved') });
        currentClient.value = null;
        await getList();
    } catch {
        createNotificationError({ message: t('ct-oauth-client.saveError') });
    } finally {
        isSaving.value = false;
    }
}

async function onDelete(): Promise<void> {
    if (!deleteClient.value || !canDelete.value || isSaving.value) return;
    isSaving.value = true;
    try {
        await repository.delete(deleteClient.value.id, Contena.Context.api);
        deleteClient.value = null;
        await getList();
    } catch {
        createNotificationError({ message: t('ct-oauth-client.deleteError') });
    } finally {
        isSaving.value = false;
    }
}

function onDeleteModalChange(open: boolean): void {
    if (!open && !isSaving.value) deleteClient.value = null;
}

onMounted(() => void getList());

ctDefinePublic({});
</script>
