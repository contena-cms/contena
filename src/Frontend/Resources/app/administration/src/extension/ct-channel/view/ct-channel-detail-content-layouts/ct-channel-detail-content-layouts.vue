<template>
    <ct-block name="ct_channel_detail_content_layouts">
        <div class="ct-channel-detail-content-layouts">
            <ct-block name="ct_channel_detail_content_layouts_header">
                <mt-card :title="t('ct-frontend-content-layout.header.title')" :is-loading="loadingSections.header">
                    <div class="ct-channel-detail-content-layouts__card-header">
                        <p>
                            {{ t('ct-frontend-content-layout.header.description') }}
                        </p>
                        <mt-button
                            variant="secondary"
                            size="small"
                            :disabled="!allowCreateLayout || undefined"
                            @click="onCreateLayout('header')"
                        >
                            {{ t('ct-frontend-content-layout.assignment.create') }}
                        </mt-button>
                    </div>

                    <div class="ct-channel-detail-content-layouts__assignments">
                        <div
                            v-for="target in assignmentTargets"
                            :key="`header-${target.id}`"
                            class="ct-channel-detail-content-layouts__assignment"
                        >
                            <div class="ct-channel-detail-content-layouts__target">
                                <strong>{{ target.label }}</strong>
                                <span>{{ target.description }}</span>
                            </div>
                            <mt-entity-select
                                entity="content_layout"
                                small
                                :model-value="getContentLayoutId('header', target.domainId)"
                                :criteria="getContentLayoutCriteria('header')"
                                :placeholder="t('ct-frontend-content-layout.assignment.placeholder')"
                                :disabled="!allowEdit || isAssignmentSaving('header', target.domainId) || undefined"
                                @update:model-value="onContentLayoutChange('header', target.domainId, $event)"
                            />
                            <mt-button
                                v-if="getContentLayoutId('header', target.domainId)"
                                v-tooltip="{
                                    message: t('ct-frontend-content-layout.assignment.open'),
                                }"
                                variant="secondary"
                                size="small"
                                square
                                :aria-label="t('ct-frontend-content-layout.assignment.open')"
                                @click="onOpenLayout(getContentLayoutId('header', target.domainId)!)"
                            >
                                <mt-icon name="regular-pencil" size="16px" />
                            </mt-button>
                        </div>
                    </div>
                </mt-card>
            </ct-block>

            <ct-block name="ct_channel_detail_content_layouts_footer">
                <mt-card :title="t('ct-frontend-content-layout.footer.title')" :is-loading="loadingSections.footer">
                    <div class="ct-channel-detail-content-layouts__card-header">
                        <p>
                            {{ t('ct-frontend-content-layout.footer.description') }}
                        </p>
                        <mt-button
                            variant="secondary"
                            size="small"
                            :disabled="!allowCreateLayout || undefined"
                            @click="onCreateLayout('footer')"
                        >
                            {{ t('ct-frontend-content-layout.assignment.create') }}
                        </mt-button>
                    </div>

                    <div class="ct-channel-detail-content-layouts__assignments">
                        <div
                            v-for="target in assignmentTargets"
                            :key="`footer-${target.id}`"
                            class="ct-channel-detail-content-layouts__assignment"
                        >
                            <div class="ct-channel-detail-content-layouts__target">
                                <strong>{{ target.label }}</strong>
                                <span>{{ target.description }}</span>
                            </div>
                            <mt-entity-select
                                entity="content_layout"
                                small
                                :model-value="getContentLayoutId('footer', target.domainId)"
                                :criteria="getContentLayoutCriteria('footer')"
                                :placeholder="t('ct-frontend-content-layout.assignment.placeholder')"
                                :disabled="!allowEdit || isAssignmentSaving('footer', target.domainId) || undefined"
                                @update:model-value="onContentLayoutChange('footer', target.domainId, $event)"
                            />
                            <mt-button
                                v-if="getContentLayoutId('footer', target.domainId)"
                                v-tooltip="{
                                    message: t('ct-frontend-content-layout.assignment.open'),
                                }"
                                variant="secondary"
                                size="small"
                                square
                                :aria-label="t('ct-frontend-content-layout.assignment.open')"
                                @click="onOpenLayout(getContentLayoutId('footer', target.domainId)!)"
                            >
                                <mt-icon name="regular-pencil" size="16px" />
                            </mt-button>
                        </div>
                    </div>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed, inject, onMounted, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-channel-detail-content-layouts.scss';

type ContentSection = 'header' | 'footer';
type ContentLayoutAssignment = (
    | Omit<Entity<'header_content_layout'>, 'domainId'>
    | Omit<Entity<'footer_content_layout'>, 'domainId'>
) & {
    domainId?: string | null;
};
type AssignmentTarget = {
    id: string;
    domainId: string | null;
    label: string;
    description: string;
};
type ChannelDomain = Pick<Entity<'channel_domain'>, 'id' | 'url'>;
type Channel = Pick<Entity<'channel'>, 'id' | 'name' | 'translated'> & {
    domains?: ChannelDomain[] | EntityCollection<'channel_domain'>;
};
type AssignmentRepository = {
    search: (criteria: CriteriaType, context: typeof Contena.Context.api) => Promise<unknown>;
    create: (context: typeof Contena.Context.api) => ContentLayoutAssignment;
    save: (assignment: ContentLayoutAssignment, context: typeof Contena.Context.api) => Promise<void>;
    delete: (id: string, context: typeof Contena.Context.api) => Promise<void>;
};

const props = defineProps({
    channel: {
        type: Object as PropType<Channel>,
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) {
    throw new Error('The repositoryFactory and acl services are required.');
}

const assignments = ref<Record<ContentSection, ContentLayoutAssignment[]>>({
    header: [],
    footer: [],
});
const loadingSections = ref<Record<ContentSection, boolean>>({
    header: false,
    footer: false,
});
const savingAssignments = ref<Record<string, boolean>>({});
const assignmentTargets = computed<AssignmentTarget[]>(() => {
    const domains = getCollectionEntries<ChannelDomain>(props.channel.domains);

    return [
        {
            id: 'channel',
            domainId: null,
            label: t('ct-frontend-content-layout.assignment.channelDefault'),
            description: props.channel.translated?.name ?? props.channel.name ?? '',
        },
        ...domains.map((domain) => ({
            id: domain.id,
            domainId: domain.id,
            label: domain.url,
            description: t('ct-frontend-content-layout.assignment.domainOverride'),
        })),
    ];
});
const allowEdit = computed(() => acl.can('channel.editor'));
const allowCreateLayout = computed(() => acl.can('experience_studio.creator'));

function getCollectionEntries<T>(collection: T[] | { getElements?: () => T[] } | null | undefined): T[] {
    if (Array.isArray(collection)) {
        return collection;
    }

    return collection?.getElements?.() ?? [];
}
const getAssignmentRepository = (section: ContentSection): AssignmentRepository => {
    return repositoryFactory.create(`${section}_content_layout`) as unknown as AssignmentRepository;
};
const getContentLayoutCriteria = (section: ContentSection): CriteriaType => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.setTotalCountMode(0);
    criteria.addFilter(Contena.Data.Criteria.equals('rootSource', section));
    criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));

    return criteria;
};
const getAssignment = (section: ContentSection, domainId: string | null): ContentLayoutAssignment | null => {
    return assignments.value[section].find((assignment) => (assignment.domainId ?? null) === domainId) ?? null;
};
const getContentLayoutId = (section: ContentSection, domainId: string | null): string | null => {
    return getAssignment(section, domainId)?.contentLayoutId ?? null;
};
const getAssignmentKey = (section: ContentSection, domainId: string | null): string => {
    return `${section}:${domainId ?? 'channel'}`;
};
const isAssignmentSaving = (section: ContentSection, domainId: string | null): boolean => {
    return Boolean(savingAssignments.value[getAssignmentKey(section, domainId)]);
};
const loadAssignments = async (): Promise<void> => {
    await Promise.all([
        loadSectionAssignments('header'),
        loadSectionAssignments('footer'),
    ]);
};
const loadSectionAssignments = async (section: ContentSection): Promise<void> => {
    loadingSections.value[section] = true;
    const criteria = new Contena.Data.Criteria(1, 100);
    criteria.setTotalCountMode(0);
    criteria.addFilter(Contena.Data.Criteria.equals('channelId', props.channel.id));
    criteria.addAssociation('contentLayout');
    criteria.addAssociation('domain');
    criteria.addSorting(Contena.Data.Criteria.sort('createdAt', 'ASC'));

    try {
        const result = await getAssignmentRepository(section).search(criteria, Contena.Context.api);
        assignments.value[section] = getCollectionEntries<ContentLayoutAssignment>(
            result as ContentLayoutAssignment[] | { getElements?: () => ContentLayoutAssignment[] },
        );
    } catch {
        createNotificationError({
            message: t('ct-frontend-content-layout.assignment.loadError'),
        });
    } finally {
        loadingSections.value[section] = false;
    }
};
const onContentLayoutChange = async (
    section: ContentSection,
    domainId: string | null,
    contentLayoutId: string | null,
): Promise<void> => {
    if (!allowEdit.value) {
        return;
    }

    const assignmentKey = getAssignmentKey(section, domainId);
    const repository = getAssignmentRepository(section);
    const existingAssignment = getAssignment(section, domainId);
    savingAssignments.value[assignmentKey] = true;

    try {
        if (!contentLayoutId) {
            if (existingAssignment) {
                await repository.delete(existingAssignment.id, Contena.Context.api);
            }
        } else {
            const assignment = existingAssignment ?? repository.create(Contena.Context.api);
            assignment.channelId = props.channel.id;
            assignment.domainId = domainId;
            assignment.contentLayoutId = contentLayoutId;
            await repository.save(assignment, Contena.Context.api);
        }

        await loadSectionAssignments(section);
        createNotificationSuccess({
            message: t('ct-frontend-content-layout.assignment.saveSuccess'),
        });
    } catch {
        createNotificationError({
            message: t('ct-frontend-content-layout.assignment.saveError'),
        });
    } finally {
        savingAssignments.value[assignmentKey] = false;
    }
};
const onCreateLayout = (section: ContentSection): void => {
    void router.push({
        name: 'ct.experience.studio.create',
        query: {
            rootSource: section,
            channelId: props.channel.id,
        },
    });
};
const onOpenLayout = (contentLayoutId: string): void => {
    void router.push({
        name: 'ct.experience.studio.detail',
        params: { id: contentLayoutId },
    });
};

onMounted(() => {
    void loadAssignments();
});

ctDefinePublic({
    assignments,
    loadingSections,
    savingAssignments,
    assignmentTargets,
    allowEdit,
    allowCreateLayout,
    getCollectionEntries,
    getAssignmentRepository,
    getContentLayoutCriteria,
    getAssignment,
    getContentLayoutId,
    getAssignmentKey,
    isAssignmentSaving,
    loadAssignments,
    loadSectionAssignments,
    onContentLayoutChange,
    onCreateLayout,
    onOpenLayout,
});

defineExpose({
    assignments,
    loadingSections,
    savingAssignments,
    assignmentTargets,
    allowEdit,
    allowCreateLayout,
    getCollectionEntries,
    getAssignmentRepository,
    getContentLayoutCriteria,
    getAssignment,
    getContentLayoutId,
    getAssignmentKey,
    isAssignmentSaving,
    loadAssignments,
    loadSectionAssignments,
    onContentLayoutChange,
    onCreateLayout,
    onOpenLayout,
});
</script>
