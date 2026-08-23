<template>
    <ct-block name="sw_category_layout_assignment">
        <mt-card
            class="ct-category-layout-assignment"
            position-identifier="ct-category-layout-assignment"
            :title="$t(`${snippetPath}.cardTitle`)"
        >
            <ct-block name="sw_category_layout_assignment_header">
                <div class="ct-category-layout-assignment__header">
                    <p>{{ $t(`${snippetPath}.description`) }}</p>

                    <mt-button variant="primary" :disabled="!allowCreate || undefined" @click="onCreateLayout">
                        {{ $t(`${snippetPath}.buttonCreate`) }}
                    </mt-button>
                </div>
            </ct-block>

            <ct-block name="sw_category_layout_assignment_content">
                <mt-loader v-if="isLoading" />

                <div v-else-if="assignments?.length" class="ct-category-layout-assignment__assignments">
                    <div
                        v-for="assignment in assignments"
                        :key="assignment.id"
                        class="ct-category-layout-assignment__assignment"
                    >
                        <div class="ct-category-layout-assignment__assignment-copy">
                            <strong>{{
                                assignment.contentLayout?.translated?.name ?? assignment.contentLayout?.name
                            }}</strong>
                            <span>{{
                                assignment.channel?.translated?.name ?? assignment.channel?.name ?? allChannelsLabel
                            }}</span>
                        </div>

                        <mt-button
                            variant="secondary"
                            :disabled="!assignment.contentLayoutId || undefined"
                            @click="onOpenLayout(assignment.contentLayoutId)"
                        >
                            {{ $t(`${snippetPath}.buttonOpen`) }}
                        </mt-button>
                    </div>
                </div>

                <mt-empty-state v-else icon="regular-layout" :headline="$t(`${snippetPath}.emptyState`)" />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global EntityCollection */

import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-category-layout-assignment.scss';

type EntityType = 'category' | 'landing_page';
type AssignmentEntityName = 'category_content_layout' | 'landing_page_content_layout';

const props = defineProps({
    entityType: {
        type: String as () => EntityType,
        required: true,
    },
});

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const assignments = ref<EntityCollection<AssignmentEntityName> | null>(null);
const isLoading = ref(false);
const entityId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const repositoryName = computed<AssignmentEntityName>(() => `${props.entityType}_content_layout`);
const entityIdField = computed(() => (props.entityType === 'category' ? 'categoryId' : 'landingPageId'));
const assignmentRepository = computed(() => repositoryFactory.create(repositoryName.value));
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(1, 25);
    query.setTotalCountMode(0);
    query.addFilter(Contena.Data.Criteria.equals(entityIdField.value, entityId.value));
    query.addAssociation('contentLayout');
    query.addAssociation('channel');
    query.addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));

    return query;
});
const allowCreate = computed(() => acl.can('experience_studio.creator'));
const snippetPath = computed(() => `ct-${props.entityType === 'category' ? 'category' : 'landing-page'}.layout`);
const allChannelsLabel = computed(() => t(`${snippetPath.value}.allChannels`));
const loadAssignments = async (): Promise<void> => {
    if (!entityId.value) {
        assignments.value = null;
        return;
    }

    isLoading.value = true;

    try {
        assignments.value = await assignmentRepository.value.search(criteria.value, Contena.Context.api);
    } catch {
        createNotificationError({ message: t(`${snippetPath.value}.loadError`) });
    } finally {
        isLoading.value = false;
    }
};
const onCreateLayout = (): void => {
    void router.push({
        name: 'ct.experience.studio.create',
        query: { rootSource: props.entityType, entityId: entityId.value ?? undefined },
    });
};
const onOpenLayout = (contentLayoutId: string): void => {
    void router.push({ name: 'ct.experience.studio.detail', params: { id: contentLayoutId } });
};

watch(
    [
        entityId,
        () => props.entityType,
    ],
    () => void loadAssignments(),
    { immediate: true },
);

swDefinePublic({
    assignments,
    isLoading,
    assignmentRepository,
    criteria,
    entityId,
    allowCreate,
    allChannelsLabel,
    snippetPath,
    loadAssignments,
    onCreateLayout,
    onOpenLayout,
});

defineExpose({
    assignments,
    isLoading,
    assignmentRepository,
    criteria,
    entityId,
    allowCreate,
    allChannelsLabel,
    snippetPath,
    loadAssignments,
    onCreateLayout,
    onOpenLayout,
});
</script>
