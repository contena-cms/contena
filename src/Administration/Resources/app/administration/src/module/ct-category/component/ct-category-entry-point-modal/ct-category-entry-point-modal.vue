<template>
    <ct-block name="sw_category_entry_point_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal
                class="ct-category-entry-point-modal"
                :title="$t('ct-category.base.entry-point-modal.modalTitle')"
                width="l"
            >
                <ct-block name="sw_category_entry_point_modal_channel_selection">
                    <ct-single-select
                        v-model:value="selectedChannelId"
                        class="ct-category-entry-point-modal__channel-selection"
                        :label="$t('ct-category.base.entry-point-modal.channelSelection')"
                        :options="channelOptions"
                    />
                </ct-block>

                <ct-block name="sw_category_entry_point_modal_selected_channel">
                    <template v-if="selectedChannel">
                        <ct-block name="sw_category_entry_point_modal_selected_channel_show_in_main_navigation">
                            <mt-switch
                                v-model="selectedChannel.homeEnabled"
                                class="ct-category-entry-point-modal__show-in-main-navigation"
                                :label="$t('ct-category.base.entry-point-modal.showInMainNavigation')"
                                :disabled="!canEdit || undefined"
                            />
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_selected_channel_name_in_main_navigation">
                            <mt-text-field
                                v-model="selectedChannel.homeName"
                                class="ct-category-entry-point-modal__name-in-main-navigation"
                                :label="$t('ct-category.base.entry-point-modal.mainNavigationName')"
                                :help-text="$t('ct-category.base.entry-point-modal.mainNavigationNameHelpText')"
                                :placeholder="
                                    selectedChannel.translated?.homeName ||
                                    $t('ct-category.base.entry-point-modal.mainNavigationNamePlaceholder')
                                "
                                :disabled="!selectedChannel.homeEnabled || !canEdit || undefined"
                            />
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_selected_channel_layout_headline">
                            <h3 class="ct-category-entry-point-modal__layout-headline">
                                {{ $t('ct-category.base.entry-point-modal.layoutHeadline') }}
                            </h3>
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_layout_selection">
                            <div class="ct-category-entry-point-modal__base-layout">
                                <ct-block name="sw_category_entry_point_modal_layout_preview">
                                    <button
                                        type="button"
                                        class="ct-category-entry-point-modal__preview"
                                        :class="{ 'is--empty': !selectedContentLayoutId }"
                                        :disabled="!selectedContentLayoutId"
                                        @click="openInExperienceStudio"
                                    >
                                        <mt-icon name="regular-layout" size="24px" />
                                        <span>{{ currentLayoutName }}</span>
                                    </button>
                                </ct-block>

                                <ct-block name="sw_category_entry_point_modal_layout_desc">
                                    <div class="ct-category-entry-point-modal__desc">
                                        <ct-block name="sw_category_entry_point_modal_layout_desc_info">
                                            <div class="ct-category-entry-point-modal__desc-info">
                                                <ct-block name="sw_category_entry_point_modal_layout_desc_info_headline">
                                                    <div
                                                        class="ct-category-entry-point-modal__desc-headline"
                                                        :class="{ 'is--empty': !selectedContentLayoutId }"
                                                    >
                                                        {{ currentLayoutName }}
                                                    </div>
                                                </ct-block>

                                                <ct-block name="sw_category_entry_point_modal_layout_desc_info_subheadline">
                                                    <div
                                                        class="ct-category-entry-point-modal__desc-subheadline"
                                                        :class="{ 'is--empty': !selectedContentLayoutId }"
                                                    >
                                                        {{ $t('ct-category.base.entry-point-modal.layoutType') }}
                                                    </div>
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="sw_category_entry_point_modal_layout_modal">
                                            <ct-entity-single-select
                                                v-model:value="selectedContentLayoutId"
                                                class="ct-category-entry-point-modal__layout-select"
                                                entity="content_layout"
                                                :criteria="contentLayoutCriteria"
                                                :label="$t('ct-category.base.entry-point-modal.layoutSelection')"
                                                :placeholder="
                                                    $t('ct-category.base.entry-point-modal.layoutSelectionPlaceholder')
                                                "
                                                :disabled="!canEdit || undefined"
                                                show-clearable-button
                                            />
                                        </ct-block>

                                        <ct-block name="sw_category_entry_point_modal_layout_desc_actions">
                                            <div class="ct-category-entry-point-modal__desc-actions">
                                                <ct-block name="sw_category_entry_point_modal_layout_desc_actions_layout">
                                                    <mt-button
                                                        variant="secondary"
                                                        size="small"
                                                        @click="createInExperienceStudio"
                                                    >
                                                        {{ $t('ct-category.base.entry-point-modal.createLayout') }}
                                                    </mt-button>
                                                </ct-block>

                                                <ct-block name="sw_category_entry_point_modal_layout_desc_actions_designer">
                                                    <mt-button
                                                        v-if="selectedContentLayoutId"
                                                        variant="secondary"
                                                        size="small"
                                                        @click="openInExperienceStudio"
                                                    >
                                                        {{ $t('ct-category.base.entry-point-modal.openLayout') }}
                                                    </mt-button>
                                                </ct-block>

                                                <ct-block name="sw_category_entry_point_modal_layout_desc_actions_remove">
                                                    <mt-button
                                                        v-if="selectedContentLayoutId"
                                                        class="ct-category-entry-point-modal__layout-reset"
                                                        variant="secondary"
                                                        size="small"
                                                        square
                                                        :disabled="!canEdit || undefined"
                                                        @click="onLayoutReset"
                                                    >
                                                        <mt-icon name="regular-trash" size="16px" />
                                                    </mt-button>
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_seo_headline">
                            <h2 class="ct-category-entry-point-modal__seo-headline">
                                {{ $t('ct-category.base.entry-point-modal.seoHeadline') }}
                            </h2>
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_meta_title">
                            <mt-text-field
                                v-model="selectedChannel.homeMetaTitle"
                                class="ct-category-entry-point-modal__meta-title"
                                :label="$t('ct-category.base.entry-point-modal.metaTitle')"
                                :placeholder="
                                    selectedChannel.translated?.homeMetaTitle ||
                                    $t('ct-category.base.entry-point-modal.metaTitlePlaceholder')
                                "
                                :disabled="!canEdit || undefined"
                            />
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_meta_description">
                            <mt-textarea
                                v-model="selectedChannel.homeMetaDescription"
                                class="ct-category-entry-point-modal__meta-description"
                                :label="$t('ct-category.base.entry-point-modal.metaDescription')"
                                :placeholder="
                                    selectedChannel.translated?.homeMetaDescription ||
                                    $t('ct-category.base.entry-point-modal.metaDescriptionPlaceholder')
                                "
                                :disabled="!canEdit || undefined"
                            />
                        </ct-block>

                        <ct-block name="sw_category_entry_point_modal_seo_keywords">
                            <mt-text-field
                                v-model="selectedChannel.homeKeywords"
                                class="ct-category-entry-point-modal__seo-keywords"
                                :label="$t('ct-category.base.entry-point-modal.seoKeywords')"
                                :placeholder="
                                    selectedChannel.translated?.homeKeywords ||
                                    $t('ct-category.base.entry-point-modal.seoKeywordsPlaceholder')
                                "
                                :disabled="!canEdit || undefined"
                            />
                        </ct-block>
                    </template>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_category_entry_point_modal_footer">
                        <div class="ct-category-entry-point-modal__footer">
                            <ct-block name="sw_category_entry_point_modal_footer_cancel_button">
                                <mt-button variant="secondary" @click="closeModal">
                                    {{ $t('global.default.cancel') }}
                                </mt-button>
                            </ct-block>
                            <ct-block name="sw_category_entry_point_modal_footer_apply_button">
                                <mt-button
                                    variant="primary"
                                    :disabled="!canEdit || undefined"
                                    :is-loading="isSaving"
                                    @click="applyChanges"
                                >
                                    {{ $t('global.default.apply') }}
                                </mt-button>
                            </ct-block>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>

        <ct-block name="sw_category_entry_point_modal_discard_changes_modal">
            <ct-discard-changes-modal
                v-if="isDisplayingLeavePageWarning"
                @keep-editing="onLeaveModalClose"
                @discard-changes="onLeaveModalConfirm"
            />
        </ct-block>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter, type RouteLocationRaw } from 'vue-router';

import { useNotification } from 'src/app/composables/use-notification';
import type AclService from 'src/app/service/acl.service';

import type RepositoryFactory from 'src/core/data/repository-factory.data';
import './ct-category-entry-point-modal.scss';

type TemporaryChannel = {
    id: string;
    name: string | null;
    homeEnabled: boolean;
    homeName: string | null;
    homeMetaTitle: string | null;
    homeMetaDescription: string | null;
    homeKeywords: string | null;
    translated: Record<string, string | null> | null;
};

const props = defineProps({
    categoryId: {
        type: String,
        required: true,
    },
    channelCollection: {
        type: Object as PropType<EntityCollection<'channel'>>,
        required: true,
    },
});
const emit = defineEmits<{ 'modal-close': [] }>();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const temporaryCollection = ref<TemporaryChannel[]>(
    props.channelCollection.map((channel) => ({
        id: channel.id,
        name: channel.name ?? null,
        homeEnabled: channel.homeEnabled ?? true,
        homeName: channel.homeName ?? null,
        homeMetaTitle: channel.homeMetaTitle ?? null,
        homeMetaDescription: channel.homeMetaDescription ?? null,
        homeKeywords: channel.homeKeywords ?? null,
        translated: channel.translated ? { ...channel.translated } : null,
    })),
);
const channelOptions = computed(() =>
    temporaryCollection.value.map((channel) => ({
        value: channel.id,
        label: channel.translated?.name ?? channel.name ?? '',
    })),
);
const selectedChannelId = ref(channelOptions.value[0]?.value ?? '');
const selectedChannel = computed(
    () => temporaryCollection.value.find((channel) => channel.id === selectedChannelId.value) ?? null,
);
const assignmentRepository = computed(() => repositoryFactory.create('category_content_layout'));
const contentLayoutCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.setTotalCountMode(0);
    criteria.addFilter(Contena.Data.Criteria.equals('rootSource', 'category'));

    return criteria;
});
const assignmentByChannel = ref(new Map<string, Entity<'category_content_layout'>>());
const selectedLayoutIds = ref<Record<string, string | null>>({});
const selectedContentLayoutId = computed<string | null>({
    get: () => selectedLayoutIds.value[selectedChannelId.value] ?? null,
    set: (contentLayoutId) => {
        selectedLayoutIds.value = {
            ...selectedLayoutIds.value,
            [selectedChannelId.value]: contentLayoutId,
        };
    },
});
const currentLayoutName = computed(() => {
    const assignment = assignmentByChannel.value.get(selectedChannelId.value);
    if (assignment?.contentLayoutId === selectedContentLayoutId.value) {
        return assignment.contentLayout?.name ?? t('ct-category.base.entry-point-modal.defaultLayout');
    }

    if (selectedContentLayoutId.value) {
        return t('ct-category.base.entry-point-modal.selectedLayout');
    }

    return t('ct-category.base.entry-point-modal.noLayoutAssigned');
});
const canEdit = computed(() => acl.can('category.editor') && acl.can('experience_studio.editor'));
const isSaving = ref(false);
const isDisplayingLeavePageWarning = ref(false);
const nextRoute = ref<RouteLocationRaw | null>(null);

const loadLayoutAssignments = async () => {
    if (temporaryCollection.value.length === 0) {
        return;
    }

    const criteria = new Contena.Data.Criteria(1, temporaryCollection.value.length);
    criteria.setTotalCountMode(0);
    criteria.addFilter(Contena.Data.Criteria.equals('categoryId', props.categoryId));
    criteria.addFilter(
        Contena.Data.Criteria.equalsAny(
            'channelId',
            temporaryCollection.value.map((channel) => channel.id),
        ),
    );
    criteria.addAssociation('contentLayout');

    const assignments = await assignmentRepository.value.search(criteria, Contena.Context.api);
    const nextAssignments = new Map<string, Entity<'category_content_layout'>>();
    const nextLayoutIds: Record<string, string | null> = {};

    temporaryCollection.value.forEach((channel) => {
        nextLayoutIds[channel.id] = null;
    });
    assignments.forEach((assignment) => {
        if (assignment.channelId) {
            nextAssignments.set(assignment.channelId, assignment);
            nextLayoutIds[assignment.channelId] = assignment.contentLayoutId;
        }
    });

    assignmentByChannel.value = nextAssignments;
    selectedLayoutIds.value = nextLayoutIds;
};
const closeModal = () => emit('modal-close');
const onModalChange = (isOpen: boolean) => {
    if (!isOpen) {
        closeModal();
    }
};
const persistLayoutAssignments = async () => {
    await Promise.all(
        temporaryCollection.value.map(async (channel) => {
            const contentLayoutId = selectedLayoutIds.value[channel.id] ?? null;
            const assignment = assignmentByChannel.value.get(channel.id);

            if (!contentLayoutId && assignment) {
                await assignmentRepository.value.delete(assignment.id, Contena.Context.api);
                return;
            }

            if (!contentLayoutId) {
                return;
            }

            const entity = assignment ?? assignmentRepository.value.create(Contena.Context.api);
            entity.categoryId = props.categoryId;
            entity.channelId = channel.id;
            entity.contentLayoutId = contentLayoutId;
            await assignmentRepository.value.save(entity, Contena.Context.api);
        }),
    );
};
const applyChanges = async () => {
    isSaving.value = true;

    try {
        temporaryCollection.value.forEach((temporaryChannel) => {
            const channel = props.channelCollection.get(temporaryChannel.id);
            if (!channel) {
                return;
            }

            channel.homeEnabled = temporaryChannel.homeEnabled;
            channel.homeName = temporaryChannel.homeName;
            channel.homeMetaTitle = temporaryChannel.homeMetaTitle;
            channel.homeMetaDescription = temporaryChannel.homeMetaDescription;
            channel.homeKeywords = temporaryChannel.homeKeywords;
        });
        await persistLayoutAssignments();
        closeModal();
    } catch {
        createNotificationError({ message: t('ct-category.base.entry-point-modal.saveError') });
    } finally {
        isSaving.value = false;
    }
};
const normalize = (value: unknown) => value ?? '';
const hasNotAppliedChanges = () => {
    const channelChanged = temporaryCollection.value.some((temporaryChannel) => {
        const channel = props.channelCollection.get(temporaryChannel.id);
        if (!channel) {
            return false;
        }

        return (
            normalize(temporaryChannel.homeEnabled) !== normalize(channel.homeEnabled) ||
            normalize(temporaryChannel.homeName) !== normalize(channel.homeName) ||
            normalize(temporaryChannel.homeMetaTitle) !== normalize(channel.homeMetaTitle) ||
            normalize(temporaryChannel.homeMetaDescription) !== normalize(channel.homeMetaDescription) ||
            normalize(temporaryChannel.homeKeywords) !== normalize(channel.homeKeywords)
        );
    });
    const layoutChanged = temporaryCollection.value.some((channel) => {
        const originalLayoutId = assignmentByChannel.value.get(channel.id)?.contentLayoutId ?? null;
        return originalLayoutId !== (selectedLayoutIds.value[channel.id] ?? null);
    });

    return channelChanged || layoutChanged;
};
const navigateTo = (route: RouteLocationRaw) => {
    if (hasNotAppliedChanges()) {
        nextRoute.value = route;
        isDisplayingLeavePageWarning.value = true;
        return;
    }

    closeModal();
    void router.push(route);
};
const onLayoutReset = () => {
    selectedContentLayoutId.value = null;
};
const openInExperienceStudio = () => {
    if (!selectedContentLayoutId.value) {
        return;
    }

    navigateTo({ name: 'ct.experience.studio.detail', params: { id: selectedContentLayoutId.value } });
};
const createInExperienceStudio = () => {
    navigateTo({
        name: 'ct.experience.studio.create',
        query: {
            rootSource: 'category',
            entityId: props.categoryId,
            channelId: selectedChannelId.value,
        },
    });
};
const onLeaveModalClose = () => {
    nextRoute.value = null;
    isDisplayingLeavePageWarning.value = false;
};
const onLeaveModalConfirm = () => {
    const route = nextRoute.value;
    onLeaveModalClose();
    closeModal();
    if (route) {
        void router.push(route);
    }
};

onMounted(() => {
    void loadLayoutAssignments().catch(() => {
        createNotificationError({ message: t('ct-category.base.entry-point-modal.loadError') });
    });
});

swDefinePublic({
    temporaryCollection,
    channelOptions,
    selectedChannelId,
    selectedChannel,
    assignmentRepository,
    contentLayoutCriteria,
    assignmentByChannel,
    selectedLayoutIds,
    selectedContentLayoutId,
    currentLayoutName,
    canEdit,
    isSaving,
    isDisplayingLeavePageWarning,
    nextRoute,
    loadLayoutAssignments,
    closeModal,
    onModalChange,
    applyChanges,
    hasNotAppliedChanges,
    onLayoutReset,
    openInExperienceStudio,
    createInExperienceStudio,
    onLeaveModalClose,
    onLeaveModalConfirm,
});

defineExpose({
    temporaryCollection,
    channelOptions,
    selectedChannelId,
    selectedChannel,
    assignmentRepository,
    contentLayoutCriteria,
    assignmentByChannel,
    selectedLayoutIds,
    selectedContentLayoutId,
    currentLayoutName,
    canEdit,
    isSaving,
    isDisplayingLeavePageWarning,
    nextRoute,
    loadLayoutAssignments,
    closeModal,
    onModalChange,
    applyChanges,
    hasNotAppliedChanges,
    onLayoutReset,
    openInExperienceStudio,
    createInExperienceStudio,
    onLeaveModalClose,
    onLeaveModalConfirm,
});
</script>
