<template>
    <ct-block name="sw_blog_detail_layout">
        <mt-card
            class="ct-blog-detail-layout"
            position-identifier="ct-blog-detail-layout"
            :title="$t('ct-blog.layout.cardTitle')"
        >
            <ct-block name="sw_blog_detail_layout_header">
                <div class="ct-blog-detail-layout__header">
                    <p>{{ $t('ct-blog.layout.description') }}</p>

                    <mt-button variant="primary" :disabled="!allowCreate || undefined" @click="onCreateLayout">
                        {{ $t('ct-blog.layout.buttonCreate') }}
                    </mt-button>
                </div>
            </ct-block>

            <ct-block name="sw_blog_detail_layout_content">
                <mt-loader v-if="isLoading" />

                <div v-else-if="assignments?.length" class="ct-blog-detail-layout__assignments">
                    <div v-for="assignment in assignments" :key="assignment.id" class="ct-blog-detail-layout__assignment">
                        <div class="ct-blog-detail-layout__assignment-copy">
                            <strong>{{ assignment.contentLayout?.name }}</strong>
                            <span>{{
                                assignment.channel?.translated?.name ?? assignment.channel?.name ?? allChannelsLabel
                            }}</span>
                        </div>

                        <mt-button
                            variant="secondary"
                            :disabled="!assignment.contentLayoutId || undefined"
                            @click="onOpenLayout(assignment.contentLayoutId)"
                        >
                            {{ $t('ct-blog.layout.buttonOpen') }}
                        </mt-button>
                    </div>
                </div>

                <mt-empty-state v-else icon="regular-layout" :headline="$t('ct-blog.layout.emptyState')" />
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

import './ct-blog-detail-layout.scss';

defineProps({});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const assignments = ref<EntityCollection<'blog_content_layout'> | null>(null);
const isLoading = ref(false);
const assignmentRepository = computed(() => repositoryFactory.create('blog_content_layout'));
const blogId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(1, 25);
    query.setTotalCountMode(0);
    query.addFilter(Contena.Data.Criteria.equals('blogId', blogId.value));
    query.addAssociation('contentLayout');
    query.addAssociation('channel');
    query.addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));

    return query;
});
const allowCreate = computed(() => acl.can('experience_studio.creator'));
const allChannelsLabel = computed(() => t('ct-blog.layout.allChannels'));
const loadAssignments = async (): Promise<void> => {
    if (!blogId.value) {
        assignments.value = null;
        return;
    }

    isLoading.value = true;

    try {
        assignments.value = await assignmentRepository.value.search(criteria.value, Contena.Context.api);
    } catch {
        createNotificationError({ message: t('ct-blog.layout.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const onCreateLayout = (): void => {
    void router.push({
        name: 'ct.experience.studio.create',
        query: { rootSource: 'blog', entityId: blogId.value ?? undefined },
    });
};
const onOpenLayout = (contentLayoutId: string): void => {
    void router.push({ name: 'ct.experience.studio.detail', params: { id: contentLayoutId } });
};

watch(blogId, () => void loadAssignments(), { immediate: true });

swDefinePublic({
    assignments,
    isLoading,
    assignmentRepository,
    criteria,
    blogId,
    allowCreate,
    allChannelsLabel,
    loadAssignments,
    onCreateLayout,
    onOpenLayout,
});

defineExpose({
    assignments,
    isLoading,
    assignmentRepository,
    criteria,
    blogId,
    allowCreate,
    allChannelsLabel,
    loadAssignments,
    onCreateLayout,
    onOpenLayout,
});
</script>
