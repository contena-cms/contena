<template>
    <ct-block name="ct_settings_rule_list">
        <ct-page class="ct-settings-rule-list">
            <template #search-bar>
                <ct-block name="ct_settings_rule_list_search">
                    <mt-search :model-value="term" :placeholder="$t('ct-settings-rule.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_settings_rule_list_header">
                    <h2>
                        {{ $t('ct-settings-rule.list.title') }} <span v-if="!isLoading">({{ rules?.total ?? 0 }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_rule_list_actions">
                    <mt-button variant="primary" :disabled="!acl?.can('rule.creator') || undefined" @click="onCreate">
                        {{ $t('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_rule_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="$t('ct-settings-rule.list.title')"
                        :data-source="rules ?? []"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        disable-search
                        :disable-edit="true"
                        :disable-delete="!acl?.can('rule.deleter')"
                        :additional-context-buttons="additionalContextButtons"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @sort-change="onSort"
                        @item-delete="onItemDelete"
                        @context-select="onContextSelect"
                    >
                        <template #column-name="{ data }">
                            <router-link :to="{ name: 'ct.settings.rule.detail', params: { id: data.id } }">
                                {{ data.name }}
                            </router-link>
                        </template>
                        <template #column-conditions="{ data }">
                            {{ data.conditions?.length ?? 0 }}
                        </template>
                        <template #column-createdAt="{ data }">
                            {{ formatDate(data.createdAt, { hour: '2-digit', minute: '2-digit' }) }}
                        </template>
                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-rule"
                                :headline="$t('ct-settings-rule.list.emptyTitle')"
                                :description="$t('ct-settings-rule.list.emptyDescription')"
                            />
                        </template>
                    </mt-data-table>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>

    <mt-modal-root v-if="ruleToDelete" :is-open="true" @change="ruleToDelete = null">
        <mt-modal :title="$t('global.default.warning')" width="s">
            <p>{{ $t('global.entity-components.deleteMessage') }}</p>
            <template #footer>
                <mt-button variant="secondary" @click="ruleToDelete = null">
                    {{ $t('global.default.cancel') }}
                </mt-button>
                <mt-button variant="critical" :is-loading="isDeleting" @click="confirmDelete">
                    {{ $t('global.default.delete') }}
                </mt-button>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
defineProps({});

const { t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const router = useRouter();
const ruleRepository = computed(() => repositoryFactory?.create('rule'));
const conditionRepository = computed(() => repositoryFactory?.create('rule_condition'));
const rules = ref<EntityCollection<'rule'> | null>(null);
const total = ref(0);
const term = ref('');
const page = ref(1);
const limit = ref(25);
const isLoading = ref(false);
const isDeleting = ref(false);
const ruleToDelete = ref<Entity<'rule'> | null>(null);
const sortBy = ref('createdAt');
const sortDirection = ref<'ASC' | 'DESC'>('DESC');
const columns = computed(() => [
    { property: 'name', label: t('ct-settings-rule.list.name'), position: 100, renderer: 'text', sortable: true },
    {
        property: 'description',
        label: t('ct-settings-rule.list.description'),
        position: 200,
        renderer: 'text',
        sortable: false,
    },
    {
        property: 'priority',
        label: t('ct-settings-rule.list.priority'),
        position: 300,
        renderer: 'text',
        sortable: true,
        align: 'right',
        width: 110,
    },
    {
        property: 'conditions',
        label: t('ct-settings-rule.list.conditions'),
        position: 400,
        renderer: 'text',
        sortable: false,
        align: 'right',
        width: 110,
    },
    {
        property: 'createdAt',
        label: t('ct-settings-rule.list.createdAt'),
        position: 500,
        renderer: 'text',
        sortable: true,
        width: 190,
    },
]);
const additionalContextButtons = computed(() => {
    const buttons = [];

    if (acl?.can('rule.editor')) {
        buttons.push({ key: 'edit', label: t('global.default.edit') });
    }

    if (acl?.can('rule.creator')) {
        buttons.push({ key: 'duplicate', label: t('global.default.duplicate') });
    }

    return buttons;
});
const formatDate = Contena.Filter.getByName('date');

const load = async (): Promise<void> => {
    if (!ruleRepository.value) return;
    isLoading.value = true;
    try {
        const criteria = new Criteria(page.value, limit.value);
        criteria.setTerm(term.value);
        criteria.addAssociation('conditions');
        criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));
        const result = await ruleRepository.value.search(criteria);
        rules.value = result;
        total.value = result.total ?? 0;
    } finally {
        isLoading.value = false;
    }
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void load();
};
const onPageChange = (nextPage: number): void => {
    page.value = nextPage;
    void load();
};
const onLimitChange = (nextLimit: number): void => {
    limit.value = nextLimit;
    page.value = 1;
    void load();
};
const onSort = (value: { sortBy: string; sortDirection: 'ASC' | 'DESC' }): void => {
    sortBy.value = value.sortBy;
    sortDirection.value = value.sortDirection;
    page.value = 1;
    void load();
};
const onCreate = (): void => {
    void router.push({ name: 'ct.settings.rule.create' });
};
const onEdit = (item: Entity<'rule'>): void => {
    void router.push({ name: 'ct.settings.rule.detail', params: { id: item.id } });
};
const onItemDelete = (item: Entity<'rule'>): void => {
    ruleToDelete.value = item;
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'rule'> }): void => {
    if (key === 'edit') onEdit(data);
    if (key === 'duplicate') void onDuplicate(data);
};
const onDelete = async (id: string): Promise<void> => {
    await ruleRepository.value?.delete(id);
    await load();
};
const confirmDelete = async (): Promise<void> => {
    if (!ruleToDelete.value) return;
    isDeleting.value = true;
    try {
        await onDelete(ruleToDelete.value.id);
        ruleToDelete.value = null;
    } finally {
        isDeleting.value = false;
    }
};
const onDuplicate = async (source: Entity<'rule'>): Promise<void> => {
    if (!ruleRepository.value || !conditionRepository.value) return;
    isLoading.value = true;
    try {
        const duplicate = ruleRepository.value.create();
        duplicate.name = `${source.name} (${String(Contena.Snippet.tc('global.default.duplicate')).toLowerCase()})`;
        duplicate.description = source.description;
        duplicate.priority = source.priority;
        await ruleRepository.value.save(duplicate);
        const idMap = new Map<string, string>();
        for (const condition of source.conditions ?? []) {
            const copy = conditionRepository.value.create();
            idMap.set(condition.id, copy.id);
        }
        for (const condition of source.conditions ?? []) {
            const copyId = idMap.get(condition.id);
            if (!copyId) continue;
            const copy = conditionRepository.value.create(undefined, copyId);
            copy.ruleId = duplicate.id;
            copy.type = condition.type;
            copy.value = cloneDeep(condition.value);
            copy.position = condition.position;
            copy.parentId = condition.parentId ? (idMap.get(condition.parentId) ?? null) : null;
            await conditionRepository.value.save(copy);
        }
        await load();
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    void load();
});

ctDefinePublic({
    acl,
    ruleRepository,
    rules,
    total,
    term,
    isLoading,
    isDeleting,
    ruleToDelete,
    sortBy,
    sortDirection,
    columns,
    additionalContextButtons,
    formatDate,
    load,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreate,
    onEdit,
    onItemDelete,
    onContextSelect,
    onDuplicate,
    onDelete,
    confirmDelete,
});

defineExpose({
    acl,
    ruleRepository,
    rules,
    term,
    isLoading,
    isDeleting,
    ruleToDelete,
    sortBy,
    sortDirection,
    columns,
    additionalContextButtons,
    formatDate,
    onSearch,
    onPageChange,
    onLimitChange,
    onSort,
    onCreate,
    onEdit,
    onItemDelete,
    onContextSelect,
    onDuplicate,
    onDelete,
    confirmDelete,
});
</script>
