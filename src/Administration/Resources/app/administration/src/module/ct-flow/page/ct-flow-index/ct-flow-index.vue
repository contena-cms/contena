<template>
    <ct-block name="ct_flow_index">
        <ct-page class="ct-flow-index">
            <template #search-bar>
                <mt-search :model-value="term" :placeholder="$t('ct-flow.list.search')" @change="onSearch" />
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_flow_index_header">
                    <h2>
                        {{ $t('ct-flow.list.title') }} <span v-if="!isLoading">({{ flows?.total ?? 0 }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_flow_index_actions">
                    <mt-button variant="primary" :disabled="!acl?.can('flow.creator') || undefined" @click="onCreate">
                        {{ $t('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_flow_index_content">
                    <ct-card-view>
                        <ct-block name="ct_flow_index_tabs">
                            <mt-tabs
                                class="ct-flow-index__tabs"
                                position-identifier="ct-flow-listing"
                                :items="tabItems"
                                :default-item="activeTab"
                                :small="true"
                                @new-item-active="onTabChange"
                            />
                        </ct-block>

                        <mt-data-table
                            v-if="activeTab === 'flows'"
                            layout="full"
                            :caption="$t('ct-flow.list.title')"
                            :data-source="flows"
                            :columns="flowColumns"
                            :is-loading="isLoading"
                            :pagination-total-items="flowTotal"
                            :current-page="page"
                            :pagination-limit="limit"
                            disable-search
                            disable-edit
                            :disable-delete="!acl?.can('flow.deleter')"
                            :additional-context-buttons="flowContextButtons"
                            @reload="load"
                            @pagination-current-page-change="onPageChange"
                            @pagination-limit-change="onLimitChange"
                            @item-delete="onDelete"
                            @context-select="onFlowContextSelect"
                        >
                            <template #column-name="{ data }">
                                <router-link :to="{ name: 'ct.flow.detail', params: { id: data.id } }">
                                    {{ data.name }}
                                </router-link>
                            </template>
                            <template #column-active="{ data }">
                                <mt-switch
                                    :model-value="data.active"
                                    :disabled="!acl?.can('flow.editor') || undefined"
                                    @update:model-value="(value) => onToggleActive(data, value)"
                                />
                            </template>
                            <template #column-createdAt="{ data }">
                                {{ formatDate(data.createdAt, { hour: '2-digit', minute: '2-digit' }) }}
                            </template>
                            <template #empty-state>
                                <mt-empty-state
                                    icon="regular-flow"
                                    :headline="$t('ct-flow.list.emptyTitle')"
                                    :description="$t('ct-flow.list.emptyDescription')"
                                />
                            </template>
                        </mt-data-table>

                        <mt-data-table
                            v-else
                            layout="full"
                            :caption="$t('ct-flow.list.templatesCard')"
                            :data-source="templates"
                            :columns="templateColumns"
                            :is-loading="isLoading"
                            :pagination-total-items="templateTotal"
                            :current-page="page"
                            :pagination-limit="limit"
                            disable-search
                            disable-edit
                            disable-delete
                            :additional-context-buttons="templateContextButtons"
                            @reload="load"
                            @pagination-current-page-change="onPageChange"
                            @pagination-limit-change="onLimitChange"
                            @context-select="onTemplateContextSelect"
                        />
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
defineProps({});

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const router = useRouter();
const flowRepository = computed(() => repositoryFactory?.create('flow'));
const sequenceRepository = computed(() => repositoryFactory?.create('flow_sequence'));
const templateRepository = computed(() => repositoryFactory?.create('flow_template'));
const flows = ref<EntityCollection<'flow'> | null>(null);
const templates = ref<EntityCollection<'flow_template'> | null>(null);
const term = ref('');
const page = ref(1);
const limit = ref(25);
const flowTotal = ref(0);
const templateTotal = ref(0);
const activeTab = ref<'flows' | 'templates'>('flows');
const isLoading = ref(false);
const tabItems = computed(() => [
    { label: Contena.Snippet.tc('ct-flow.list.flowsCard'), name: 'flows' },
    { label: Contena.Snippet.tc('ct-flow.list.templatesCard'), name: 'templates' },
]);
const flowColumns = computed(() => [
    { property: 'name', label: Contena.Snippet.tc('ct-flow.list.name'), position: 100, renderer: 'text' },
    { property: 'eventName', label: Contena.Snippet.tc('ct-flow.list.event'), position: 200, renderer: 'text' },
    { property: 'active', label: Contena.Snippet.tc('ct-flow.list.active'), position: 300, renderer: 'text', width: 100 },
    {
        property: 'priority',
        label: Contena.Snippet.tc('ct-flow.list.priority'),
        position: 400,
        renderer: 'text',
        width: 100,
    },
    {
        property: 'createdAt',
        label: Contena.Snippet.tc('ct-flow.list.createdAt'),
        position: 500,
        renderer: 'text',
        width: 190,
    },
]);
const templateColumns = computed(() => [
    { property: 'name', label: Contena.Snippet.tc('ct-flow.list.name'), position: 100, renderer: 'text' },
]);
const flowContextButtons = computed(() => {
    const buttons = [];
    if (acl?.can('flow.editor') || acl?.can('flow.viewer'))
        buttons.push({ key: 'edit', label: Contena.Snippet.tc('global.default.edit') });
    if (acl?.can('flow.creator')) buttons.push({ key: 'duplicate', label: Contena.Snippet.tc('global.default.duplicate') });
    return buttons;
});
const templateContextButtons = computed(() =>
    acl?.can('flow.creator') ? [{ key: 'create', label: Contena.Snippet.tc('ct-flow.list.createFromTemplate') }] : [],
);
const formatDate = Contena.Filter.getByName('date');
const load = async (): Promise<void> => {
    if (!flowRepository.value || !templateRepository.value) return;
    isLoading.value = true;
    try {
        const flowCriteria = new Criteria(page.value, limit.value);
        flowCriteria.setTerm(term.value);
        flowCriteria.addAssociation('sequences');
        flowCriteria.addSorting(Criteria.sort('priority', 'DESC'));
        const templateCriteria = new Criteria(page.value, limit.value);
        templateCriteria.addSorting(Criteria.sort('name', 'ASC'));
        [
            flows.value,
            templates.value,
        ] = await Promise.all([
            flowRepository.value.search(flowCriteria),
            templateRepository.value.search(templateCriteria),
        ]);
        flowTotal.value = flows.value?.total ?? flows.value?.length ?? 0;
        templateTotal.value = templates.value?.total ?? templates.value?.length ?? 0;
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
const onTabChange = (tab: string): void => {
    if (tab === 'flows' || tab === 'templates') {
        activeTab.value = tab;
    }
};
const onCreate = (): void => {
    void router.push({ name: 'ct.flow.create' });
};
const onCreateFromTemplate = (item: Entity<'flow_template'>): void => {
    void router.push({ name: 'ct.flow.create', params: { flowTemplateId: item.id } });
};
const onEdit = (item: Entity<'flow'>): void => {
    void router.push({ name: 'ct.flow.detail', params: { id: item.id } });
};
const onDelete = async (item: Entity<'flow'> | string): Promise<void> => {
    const id = typeof item === 'string' ? item : item.id;
    await flowRepository.value?.delete(id);
    await load();
};
const onFlowContextSelect = ({ key, data }): void => {
    if (key === 'edit') onEdit(data);
    if (key === 'duplicate') void onDuplicate(data);
};
const onTemplateContextSelect = ({ key, data }): void => {
    if (key === 'create') onCreateFromTemplate(data);
};
const onToggleActive = async (item: Entity<'flow'>, value: boolean): Promise<void> => {
    item.active = value;
    await flowRepository.value?.save(item);
};
const onDuplicate = async (source: Entity<'flow'>): Promise<void> => {
    if (!flowRepository.value || !sequenceRepository.value) return;
    isLoading.value = true;
    try {
        const duplicate = flowRepository.value.create();
        duplicate.name = `${source.name} (${String(Contena.Snippet.tc('global.default.duplicate')).toLowerCase()})`;
        duplicate.description = source.description;
        duplicate.eventName = source.eventName;
        duplicate.priority = source.priority;
        duplicate.active = false;
        await flowRepository.value.save(duplicate);
        const copies = new Map<string, Entity<'flow_sequence'>>();
        for (const sourceSequence of source.sequences ?? [])
            copies.set(sourceSequence.id, sequenceRepository.value.create());
        for (const sourceSequence of source.sequences ?? []) {
            const copy = copies.get(sourceSequence.id);
            if (!copy) continue;
            copy.flowId = duplicate.id;
            copy.parentId = sourceSequence.parentId ? (copies.get(sourceSequence.parentId)?.id ?? null) : null;
            copy.ruleId = sourceSequence.ruleId;
            copy.actionName = sourceSequence.actionName;
            copy.config = cloneDeep(sourceSequence.config);
            copy.position = sourceSequence.position;
            copy.displayGroup = sourceSequence.displayGroup;
            copy.trueCase = sourceSequence.trueCase;
            await sequenceRepository.value.save(copy);
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
    flowRepository,
    templateRepository,
    flows,
    templates,
    term,
    page,
    limit,
    flowTotal,
    templateTotal,
    activeTab,
    isLoading,
    tabItems,
    flowColumns,
    templateColumns,
    formatDate,
    load,
    onSearch,
    onTabChange,
    onCreate,
    onCreateFromTemplate,
    onEdit,
    onDelete,
    flowContextButtons,
    templateContextButtons,
    onPageChange,
    onLimitChange,
    onFlowContextSelect,
    onTemplateContextSelect,
    onDuplicate,
    onToggleActive,
});

defineExpose({
    acl,
    flowRepository,
    templateRepository,
    flows,
    templates,
    term,
    page,
    limit,
    flowTotal,
    templateTotal,
    activeTab,
    isLoading,
    tabItems,
    flowColumns,
    templateColumns,
    formatDate,
    onSearch,
    onTabChange,
    onCreate,
    onCreateFromTemplate,
    onEdit,
    onDelete,
    flowContextButtons,
    templateContextButtons,
    onPageChange,
    onLimitChange,
    onFlowContextSelect,
    onTemplateContextSelect,
    onDuplicate,
    onToggleActive,
});
</script>

<style scoped>
.ct-flow-index :deep(.ct-card-view__content) {
    width: 100%;
    max-width: 60rem;
    margin: 0 auto;
}

.ct-flow-index__tabs {
    margin-bottom: 20px;
}

.ct-flow-index__active-cell {
    display: flex;
    align-items: center;
    height: 100%;
}

.ct-flow-index__active-cell :deep(.mt-switch) {
    margin: 0;
    min-height: 0;
}
</style>
