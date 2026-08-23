<template>
    <ct-block name="sw_experience_studio_detail">
        <ct-page class="ct-experience-studio-detail" :show-search-bar="false" :show-smart-bar="false">
            <template #content>
                <ct-block name="sw_experience_studio_detail_content">
                    <div class="ct-experience-studio-detail__content">
                        <ct-experience-studio-create-wizard
                            v-if="showCreateWizard"
                            :name="createWizardName"
                            :selected-type="createWizardSelectedType"
                            :type-options="layoutTypeOptions"
                            :is-loading-types="isLoadingLayoutTypes"
                            :type-load-error="layoutTypeLoadError"
                            @update:name="onCreateWizardNameChange"
                            @update:selected-type="onCreateWizardTypeChange"
                            @complete="onCreateWizardComplete"
                            @cancel="onCreateWizardCancel"
                        />

                        <template v-else>
                            <ct-block name="sw_experience_studio_detail_toolbar">
                                <ct-experience-studio-toolbar
                                    :layout="layout"
                                    :is-loading="isLoading"
                                    :current-viewport="currentViewport"
                                    :allow-save="allowSave"
                                    :preview-channel-id="previewChannelId"
                                    :preview-entity-type="previewEntityType"
                                    :preview-entity-id="previewEntityId"
                                    :show-preview-entity-select="showPreviewEntitySelect"
                                    :can-undo="canUndo"
                                    :can-redo="canRedo"
                                    :allow-assign="allowAssign"
                                    :is-assigned="isCurrentPreviewAssigned"
                                    :is-assignment-loading="isAssignmentLoading"
                                    @back="onClickBack"
                                    @viewport-change="onViewportChange"
                                    @preview-channel-change="onPreviewChannelChange"
                                    @preview-entity-id-change="onPreviewEntityIdChange"
                                    @save="onSave"
                                    @undo="onUndo"
                                    @redo="onRedo"
                                    @assign="onAssignLayout"
                                    @unassign="onUnassignLayout"
                                />
                            </ct-block>

                            <ct-block name="sw_experience_studio_detail_workspace">
                                <div class="ct-experience-studio-detail__workspace">
                                    <ct-block name="sw_experience_studio_detail_sidebar_tree">
                                        <ct-experience-studio-sidebar-tree
                                            class="ct-experience-studio-detail__sidebar-tree"
                                            :layout="layout"
                                            :selected-element-id="selectedElementId"
                                            :validate-move-target="validateMoveTarget"
                                            @select-element="onSelectElement"
                                            @add-element="onAddElement"
                                            @duplicate-element="onDuplicateElement"
                                            @delete-element="onDeleteElement"
                                            @move-element="onMoveElement"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_experience_studio_detail_preview">
                                        <ct-experience-studio-preview
                                            class="ct-experience-studio-detail__preview"
                                            :layout="layout"
                                            :viewport="currentViewport"
                                            :channel-id="previewChannelId"
                                            :entity-type="previewEntityType"
                                            :entity-id="previewEntityId"
                                            :style-options="styleOptionStore.optionsByName"
                                            :suspend-auto-reload="isInlineEditing"
                                            @select-element="onSelectElement"
                                            @inline-edit-start="onInlineEditStart"
                                            @inline-edit-change="onInlineEditChange"
                                            @inline-edit-commit="onInlineEditCommit"
                                            @inline-edit-cancel="onInlineEditCancel"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_experience_studio_detail_element_settings">
                                        <ct-experience-studio-element-settings
                                            class="ct-experience-studio-detail__element-settings"
                                            :layout="layout"
                                            :selected-element-id="selectedElementId"
                                            :selected-element="selectedElement"
                                            :selected-element-type="selectedElementType"
                                            :style-options="styleOptionStore.optionsByName"
                                            :is-loading-types="elementTypeStore.isLoading"
                                            :is-loading-style-options="styleOptionStore.isLoading"
                                            :type-load-error="elementTypeStore.loadError"
                                            :style-option-load-error="styleOptionStore.loadError"
                                            :allow-edit="allowSave"
                                            :is-inline-editing-active="isInlineEditing"
                                            @update-properties="onElementSettingsChange"
                                            @update-style="onElementStyleChange"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-experience-studio-element-picker
                                :open="isElementPickerOpen"
                                :title="$t('ct-experience-studio.detail.elementPicker.title')"
                                :elements="availablePickerElements"
                                :top="pickerTop"
                                :left="pickerLeft"
                                @close="onCloseElementPicker"
                                @select="onSelectElementType"
                            />
                        </template>

                        <mt-loader v-if="isLoading" />
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type {
    ContentLayoutDraftDuplicatePayload,
    ContentLayoutDraftInsertPayload,
    ContentLayoutDraftMovePayload,
    ContentLayoutDraftMutationResponse,
    ContentLayoutDraftRemovePayload,
} from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ExperienceStudioElementTypeStore } from 'src/module/ct-experience-studio/store/experience-studio-element-type.store';
import type { ExperienceStudioStyleOptionStore } from 'src/module/ct-experience-studio/store/experience-studio-style-option.store';
import type { ContentElementNode } from 'src/module/ct-experience-studio/types/content-element.types';
import { getFrontendChannelCriteria } from 'src/module/ct-experience-studio/util/channel-criteria.util';
import { castContentElementNodes } from 'src/module/ct-experience-studio/util/content-element-label.util';
import {
    findElementLocation,
    sanitizeContentElementLayoutForWrite,
    updateElementPropertiesInLayout,
    updateElementStyleInLayout,
} from 'src/module/ct-experience-studio/util/content-element.util';
import 'src/module/ct-experience-studio/store/experience-studio-editor.store';
import 'src/module/ct-experience-studio/store/experience-studio-element-type.store';
import 'src/module/ct-experience-studio/store/experience-studio-style-option.store';
import './ct-experience-studio-detail.scss';
const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';
type LayoutMutationResult =
    | false
    | {
          selectedElementId?: string | null;
      };
type AddElementPayload = {
    parentElementId: string | null;
    slotName: string | null;
    anchorTop: number;
    anchorLeft: number;
};

type MoveElementPayload = {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
};

type LayoutAssignmentConfig = {
    repositoryName: 'blog_content_layout' | 'category_content_layout' | 'landing_page_content_layout';
    associationName: 'blogContentLayouts' | 'categoryContentLayouts' | 'landingPageContentLayouts';
    entityIdField: 'blogId' | 'categoryId' | 'landingPageId';
};

type DraftMutationOperation = 'insert' | 'remove' | 'duplicate' | 'move';
type LayoutMutator = (layoutValue: ContentElementNode[]) => LayoutMutationResult;
type SelectedElementIdResolver = (response: ContentLayoutDraftMutationResponse) => string | null;
type ContentSystemLayoutDraftMutationService = {
    insertElement: (payload: ContentLayoutDraftInsertPayload) => Promise<ContentLayoutDraftMutationResponse>;
    removeElement: (payload: ContentLayoutDraftRemovePayload) => Promise<ContentLayoutDraftMutationResponse>;
    duplicateElement: (payload: ContentLayoutDraftDuplicatePayload) => Promise<ContentLayoutDraftMutationResponse>;
    moveElement: (payload: ContentLayoutDraftMovePayload) => Promise<ContentLayoutDraftMutationResponse>;
};
type ContentSystemEntityTypeService = {
    getEntityTypes: () => Promise<string[]>;
};

defineProps({});

import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { createNotificationSuccess, createNotificationInfo, createNotificationWarning, createNotificationError } =
    useNotification();

const $t = t;
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const layout = ref(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const currentViewport = ref('desktop');
const selectedElementId = ref(null);
const previewChannelId = ref(null);
const previewEntityId = ref(null);
const historyKeydownHandler = ref(null);
const isElementPickerOpen = ref(false);
const pendingAddElementPayload = ref(null);
const pickerTop = ref(0);
const pickerLeft = ref(0);
const inlineEditSession = ref(null);
const mutationRequestSequence = ref(0);
const latestMutationRequestId = ref(0);
const availableLayoutTypes = ref<string[]>([]);
const isLoadingLayoutTypes = ref(false);
const layoutTypeLoadError = ref(null);
const createWizardName = ref('');
const createWizardSelectedType = ref(null);
const isAssignmentLoading = ref(false);

const layoutRepository = computed(() => {
    return repositoryFactory.create('content_layout');
});
const channelRepository = computed(() => {
    return repositoryFactory.create('channel');
});
const defaultChannelCriteria = computed(() => {
    return getFrontendChannelCriteria(1);
});
const defaultPreviewEntityCriteria = computed(() => {
    return new Criteria(1, 1);
});
const layoutId = computed(() => {
    return route.params.id as string;
});
const requestedRootSource = computed(() => {
    const rootSource = route.query.rootSource;

    return typeof rootSource === 'string' ? rootSource : null;
});
const requestedEntityId = computed(() => {
    const entityId = route.query.entityId;

    return typeof entityId === 'string' ? entityId : null;
});
const requestedChannelId = computed(() => {
    const channelId = route.query.channelId;

    return typeof channelId === 'string' ? channelId : null;
});
const layoutRootSource = computed(() => {
    return getLayoutRootSource(layout.value);
});
const layoutLoadCriteria = computed(() => {
    const criteria = new Criteria(1, 1);

    criteria.addAssociation('blogContentLayouts');
    criteria.addAssociation('categoryContentLayouts');
    criteria.addAssociation('landingPageContentLayouts');

    return criteria;
});
const resolvedPreviewContext = computed(() => {
    return resolvePreviewContext(layout.value);
});
const previewEntityType = computed(() => {
    return resolvedPreviewContext.value?.entityType ?? null;
});
const showPreviewEntitySelect = computed(() => {
    return !isSectionRootSource(previewEntityType.value);
});
const allowSave = computed(() => {
    return acl.can('experience_studio.editor');
});
const isCreateMode = computed(() => {
    return route.name === 'ct.experience.studio.create';
});
const assignmentConfig = computed<LayoutAssignmentConfig | null>(() => {
    const configByRootSource: Record<string, LayoutAssignmentConfig> = {
        blog: {
            repositoryName: 'blog_content_layout',
            associationName: 'blogContentLayouts',
            entityIdField: 'blogId',
        },
        category: {
            repositoryName: 'category_content_layout',
            associationName: 'categoryContentLayouts',
            entityIdField: 'categoryId',
        },
        landing_page: {
            repositoryName: 'landing_page_content_layout',
            associationName: 'landingPageContentLayouts',
            entityIdField: 'landingPageId',
        },
    };

    return layoutRootSource.value ? (configByRootSource[layoutRootSource.value] ?? null) : null;
});
const currentAssignment = computed<Record<string, unknown> | null>(() => {
    if (!layout.value || !assignmentConfig.value || !previewEntityId.value || !previewChannelId.value) {
        return null;
    }

    const config = assignmentConfig.value;
    const layoutAssociations = layout.value as Entity<'content_layout'> & Record<string, unknown>;
    const assignments = getAssociationEntries(layoutAssociations[config.associationName]);

    return (
        assignments.find((assignment) => {
            return (
                assignment[config.entityIdField] === previewEntityId.value && assignment.channelId === previewChannelId.value
            );
        }) ?? null
    );
});
const isCurrentPreviewAssigned = computed(() => {
    return currentAssignment.value !== null;
});
const allowAssign = computed(() => {
    return (
        allowSave.value &&
        !isCreateMode.value &&
        assignmentConfig.value !== null &&
        Boolean(previewEntityId.value) &&
        Boolean(previewChannelId.value)
    );
});
const showCreateWizard = computed(() => {
    return isCreateMode.value && !hasCreateLayoutMetadata.value;
});
const hasCreateLayoutMetadata = computed(() => {
    return Boolean(layoutRootSource.value && layout.value?.name?.trim().length);
});
const layoutTypeOptions = computed(() => {
    const upstreamOrder = new Map<string, number>([
        [
            'category',
            0,
        ],
        [
            'blog',
            1,
        ],
        [
            'landing_page',
            2,
        ],
    ]);

    return availableLayoutTypes.value
        .map((entityType, index) => ({ entityType, index }))
        .sort((left, right) => {
            return (
                (upstreamOrder.get(left.entityType) ?? upstreamOrder.size + left.index) -
                (upstreamOrder.get(right.entityType) ?? upstreamOrder.size + right.index)
            );
        })
        .map(({ entityType }) => {
            const snippetKey = `ct-experience-studio.createWizard.layoutTypes.${entityType}`;
            const translatedLabel = t(snippetKey);

            return {
                value: entityType,
                label: translatedLabel === snippetKey ? entityType : translatedLabel,
                icon: getLayoutTypeIcon(entityType),
            };
        });
});
const editorStore = computed(() => {
    return Contena.Store.get('experienceStudioEditor');
});
const elementTypeStore = computed(() => {
    return Contena.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
});
const styleOptionStore = computed(() => {
    return Contena.Store.get('experienceStudioStyleOption' as never) as ExperienceStudioStyleOptionStore;
});
const canUndo = computed(() => {
    return editorStore.value.canUndo;
});
const canRedo = computed(() => {
    return editorStore.value.canRedo;
});
const selectedElement = computed(() => {
    if (!layout.value || !selectedElementId.value) {
        return null;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);
    const location = findElementLocation(layoutElements, selectedElementId.value);

    if (!location) {
        return null;
    }

    return location.elements[location.index] ?? null;
});
const selectedElementType = computed(() => {
    if (!selectedElement.value) {
        return null;
    }

    return elementTypeStore.value.getByName(selectedElement.value.component);
});
const availablePickerElements = computed(() => {
    const availableTypes = getAvailableTypesForPayload(pendingAddElementPayload.value);

    return availableTypes.map((typeSpecification) => ({
        name: typeSpecification.name,
        label: typeSpecification.label,
        icon: typeSpecification.icon,
        category: typeSpecification.category,
    }));
});
const isInlineEditing = computed(() => {
    return inlineEditSession.value?.isEditing ?? false;
});

const sanitizeLayoutForWrite = (layout: ContentElementNode[]) => {
    return sanitizeContentElementLayoutForWrite(layout, styleOptionStore.value.optionsByName);
};
const loadLayout = async () => {
    isLoading.value = true;

    if (isCreateMode.value) {
        layout.value = layoutRepository.value.create(Contena.Context.api);
        layout.value.id = layoutId.value;
        layout.value.name = '';
        layout.value.version = '1.0.0';
        layout.value.layout = [];
        layout.value.rootSource = requestedRootSource.value ?? '';
        previewEntityId.value = requestedEntityId.value;
        previewChannelId.value = requestedChannelId.value;
    } else {
        layout.value = await layoutRepository.value.get(layoutId.value, Contena.Context.api, layoutLoadCriteria.value);
    }

    createWizardName.value = layout.value?.name ?? '';
    createWizardSelectedType.value = layoutRootSource.value;
    applyPreviewContextDefaults();
    await loadDefaultPreviewEntity();
    editorStore.value.initialize(layoutId.value);
    isLoading.value = false;
};
const onClickBack = () => {
    void router.push({ name: 'ct.experience.studio.index' });
};
const onViewportChange = (viewport: Viewport) => {
    currentViewport.value = viewport;
};
const loadDefaultPreviewChannel = async () => {
    if (previewChannelId.value) {
        return;
    }

    const contextChannelId = resolvedPreviewContext.value?.channelId ?? null;

    if (contextChannelId) {
        previewChannelId.value = contextChannelId;

        return;
    }

    const channels = await channelRepository.value.search(defaultChannelCriteria.value, Contena.Context.api);
    const firstChannel = channels.first();

    if (firstChannel) {
        previewChannelId.value = firstChannel.id;
    }
};
const loadDefaultPreviewEntity = async () => {
    if (previewEntityId.value) {
        return;
    }
    const previewEntityTypeValue = previewEntityType.value;
    if (!previewEntityTypeValue || isSectionRootSource(previewEntityTypeValue)) {
        return;
    }
    try {
        const repository = repositoryFactory.create(previewEntityTypeValue);
        const entities = await repository.search(defaultPreviewEntityCriteria.value, Contena.Context.api);
        const firstEntity = entities.first();

        if (!previewEntityId.value && firstEntity?.id) {
            previewEntityId.value = firstEntity.id;
        }
    } catch {
        // Keep preview entity empty when no default entity can be loaded.
    }
};
const applyPreviewContextDefaults = () => {
    const resolvedPreviewContextValue = resolvedPreviewContext.value;
    if (!previewEntityId.value && resolvedPreviewContextValue?.entityId) {
        previewEntityId.value = resolvedPreviewContextValue.entityId;
    }
    if (!previewChannelId.value && resolvedPreviewContextValue?.channelId) {
        previewChannelId.value = resolvedPreviewContextValue.channelId;
    }
};
const onCreateWizardNameChange = (name: string) => {
    createWizardName.value = name;
};
const onCreateWizardTypeChange = (type: string | null) => {
    createWizardSelectedType.value = type;
};
const onCreateWizardCancel = () => {
    onClickBack();
};
const getLayoutTypeIcon = (entityType: string) => {
    const iconByEntityType: Record<string, string> = {
        blog: 'regular-file-text',
        category: 'regular-sitemap',
        landing_page: 'regular-dashboard',
        header: 'regular-browser',
        footer: 'regular-browser',
    };

    return iconByEntityType[entityType] ?? 'regular-file';
};
const onCreateWizardComplete = (payload: { name: string; type: string }) => {
    if (!layout.value) {
        return;
    }

    layout.value.name = payload.name;
    layout.value.rootSource = payload.type;
    createWizardName.value = payload.name;
    createWizardSelectedType.value = payload.type;
    previewEntityId.value = requestedEntityId.value;
    previewChannelId.value = requestedChannelId.value;
    void loadDefaultPreviewEntity();
};
const isSectionRootSource = (rootSource: string | null) => {
    return rootSource === 'header' || rootSource === 'footer' || rootSource === 'none';
};
const getLayoutRootSource = (layout: Entity<'content_layout'> | null) => {
    const rootSource = (layout as Entity<'content_layout'> & { rootSource?: unknown })?.rootSource;

    return typeof rootSource === 'string' && rootSource.length > 0 ? rootSource : null;
};
const getFirstAssociationEntry = (association: unknown) => {
    return getAssociationEntries(association)[0] ?? null;
};
function getAssociationEntries(association: unknown): Record<string, unknown>[] {
    if (Array.isArray(association)) {
        return association as Record<string, unknown>[];
    }

    const getElements = (association as { getElements?: () => unknown } | null)?.getElements;

    if (typeof getElements === 'function') {
        const elements = getElements.call(association);

        return Array.isArray(elements) ? (elements as Record<string, unknown>[]) : [];
    }

    return [];
}
const resolveAssignedPreviewContext = (layout: Entity<'content_layout'> | null) => {
    if (!layout) {
        return null;
    }

    const blogAssignment = getFirstAssociationEntry(layout.blogContentLayouts);

    if (blogAssignment?.blogId) {
        return {
            entityType: 'blog',
            entityId: blogAssignment.blogId as string,
            channelId: (blogAssignment.channelId as string | null) ?? null,
        };
    }

    const categoryAssignment = getFirstAssociationEntry(layout.categoryContentLayouts);

    if (categoryAssignment?.categoryId) {
        return {
            entityType: 'category',
            entityId: categoryAssignment.categoryId as string,
            channelId: (categoryAssignment.channelId as string | null) ?? null,
        };
    }

    const landingPageAssignment = getFirstAssociationEntry(layout.landingPageContentLayouts);

    if (landingPageAssignment?.landingPageId) {
        return {
            entityType: 'landing_page',
            entityId: landingPageAssignment.landingPageId as string,
            channelId: (landingPageAssignment.channelId as string | null) ?? null,
        };
    }

    return null;
};
const resolvePreviewContext = (layout: Entity<'content_layout'> | null) => {
    if (!layout) {
        return null;
    }

    const assignedContext = resolveAssignedPreviewContext(layout);
    const rootSource = getLayoutRootSource(layout);

    if (!rootSource) {
        return assignedContext;
    }

    if (assignedContext?.entityType === rootSource) {
        return {
            entityType: rootSource,
            entityId: assignedContext.entityId,
            channelId: assignedContext.channelId,
        };
    }

    return {
        entityType: rootSource,
        entityId: null,
        channelId: null,
    };
};
const loadElementTypes = async () => {
    await elementTypeStore.value.loadTypes();
};
const loadStyleOptions = async () => {
    await styleOptionStore.value.loadStyleOptions();
};
const entityTypeService = () => {
    return Contena.Service('contentSystemEntityTypeService') as ContentSystemEntityTypeService;
};
const loadLayoutTypes = async () => {
    isLoadingLayoutTypes.value = true;
    layoutTypeLoadError.value = null;

    try {
        availableLayoutTypes.value = await entityTypeService().getEntityTypes();
    } catch {
        layoutTypeLoadError.value = 'Failed to load layout types.';
        availableLayoutTypes.value = [];
    } finally {
        isLoadingLayoutTypes.value = false;
    }
};
const onPreviewChannelChange = (channelId: string | null) => {
    if (!channelId) {
        return;
    }

    previewChannelId.value = channelId;
};
const onPreviewEntityIdChange = (entityId: string | null) => {
    previewEntityId.value = entityId;
};
const onAssignLayout = async () => {
    if (!layout.value || !allowAssign.value || !assignmentConfig.value) {
        return;
    }

    const config = assignmentConfig.value;
    const repository = repositoryFactory.create(config.repositoryName);
    const criteria = new Criteria(1, 1);

    criteria.addFilter(Criteria.equals(config.entityIdField, previewEntityId.value));
    criteria.addFilter(Criteria.equals('channelId', previewChannelId.value));
    isAssignmentLoading.value = true;

    try {
        const existingAssignments = await repository.search(criteria, Contena.Context.api);
        const assignmentEntity = existingAssignments.first() ?? repository.create(Contena.Context.api);
        const assignment = assignmentEntity;

        assignment[config.entityIdField] = previewEntityId.value;
        assignment.channelId = previewChannelId.value;
        assignment.contentLayoutId = layout.value.id;

        await repository.save(assignmentEntity, Contena.Context.api);
        layout.value = await layoutRepository.value.get(layout.value.id, Contena.Context.api, layoutLoadCriteria.value);

        createNotificationSuccess({
            message: t('ct-experience-studio.detail.messageAssigned'),
        });
    } finally {
        isAssignmentLoading.value = false;
    }
};
const onUnassignLayout = async () => {
    if (!layout.value || !allowAssign.value || !assignmentConfig.value || !currentAssignment.value?.id) {
        return;
    }

    const repository = repositoryFactory.create(assignmentConfig.value.repositoryName);
    isAssignmentLoading.value = true;

    try {
        await repository.delete(currentAssignment.value.id as string, Contena.Context.api);
        layout.value = await layoutRepository.value.get(layout.value.id, Contena.Context.api, layoutLoadCriteria.value);

        createNotificationSuccess({
            message: t('ct-experience-studio.detail.messageUnassigned'),
        });
    } finally {
        isAssignmentLoading.value = false;
    }
};
const onSelectElement = (elementId: string | null) => {
    selectedElementId.value = elementId;
};
const onInlineEditStart = (payload: { elementId: string }) => {
    const element = findElementById(payload.elementId);

    if (!isTextElement(element)) {
        return;
    }

    const currentValue = getElementTextValue(element);
    selectedElementId.value = payload.elementId;
    inlineEditSession.value = {
        elementId: payload.elementId,
        originalValue: currentValue,
        draftValue: currentValue,
        isEditing: true,
    };
};
const onInlineEditChange = (payload: { elementId: string; value: string }) => {
    if (!inlineEditSession.value || inlineEditSession.value.elementId !== payload.elementId) {
        return;
    }

    const normalizedValue = payload.value.trim();

    inlineEditSession.value = {
        ...inlineEditSession.value,
        draftValue: normalizedValue,
    };
};
const onInlineEditCommit = (payload: { elementId: string; value: string }) => {
    if (!inlineEditSession.value || inlineEditSession.value.elementId !== payload.elementId) {
        return;
    }

    const normalizedValue = payload.value.trim();
    const session = inlineEditSession.value;
    clearInlineEditSession();

    if (normalizedValue === session.originalValue) {
        return;
    }

    applyLayoutMutation((layout) => {
        return updateElementPropertiesInLayout(layout, payload.elementId, { text: normalizedValue }) ? {} : false;
    });
};
const onInlineEditCancel = (payload: { elementId: string }) => {
    if (!inlineEditSession.value || inlineEditSession.value.elementId !== payload.elementId) {
        return;
    }

    clearInlineEditSession();
};
const onAddElement = (payload: AddElementPayload) => {
    pendingAddElementPayload.value = payload;
    pickerTop.value = payload.anchorTop - 8;
    pickerLeft.value = payload.anchorLeft + 26;
    isElementPickerOpen.value = true;
};
const onCloseElementPicker = () => {
    isElementPickerOpen.value = false;
    pendingAddElementPayload.value = null;
};
const onSelectElementType = async (component: string) => {
    const payload = pendingAddElementPayload.value;

    if (!payload) {
        onCloseElementPicker();
        return;
    }

    if (payload.parentElementId !== null) {
        if (!payload.slotName || !layout.value) {
            onCloseElementPicker();
            return;
        }

        const parentLocation = findElementLocation(castContentElementNodes(layout.value.layout), payload.parentElementId);
        const parentElement = parentLocation ? parentLocation.elements[parentLocation.index] : null;

        if (!parentElement) {
            onCloseElementPicker();
            return;
        }

        if (!canInsertIntoSlot(parentElement.component, payload.slotName, component, parentElement)) {
            createNotificationInfo({
                message: t('ct-experience-studio.detail.sidebarTree.addElementNotAllowed'),
            });
            onCloseElementPicker();
            return;
        }
    }

    const layoutElements = layout.value ? castContentElementNodes(layout.value.layout) : [];
    const insertPayload: Omit<ContentLayoutDraftInsertPayload, 'layout' | 'rootSource'> = {
        type: component,
    };

    if (payload.parentElementId !== null) {
        insertPayload.parentElementId = payload.parentElementId;
        insertPayload.slot = payload.slotName;
    }

    await executeStructuralDraftMutation(
        'insert',
        layoutElements,
        insertPayload,
        (response) => response.affectedElementIds[0] ?? selectedElementId.value,
    );

    onCloseElementPicker();
};
const applyLayoutMutation = (mutator: LayoutMutator) => {
    if (!layout.value || !allowSave.value) {
        return;
    }
    const layoutElements = castContentElementNodes(layout.value.layout);
    const workingLayout = cloneDeep(layoutElements);
    const result = mutator(workingLayout);
    if (result === false) {
        return;
    }
    editorStore.value.pushToHistory(layoutElements, selectedElementId.value);
    layout.value.layout = sanitizeLayoutForWrite(workingLayout);
    if (result.selectedElementId !== undefined) {
        selectedElementId.value = result.selectedElementId;
    }
};
const onDuplicateElement = async (elementId: string) => {
    if (!layout.value || !allowSave.value) {
        return;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);

    await executeStructuralDraftMutation(
        'duplicate',
        layoutElements,
        {
            elementId,
        },
        (response) => response.affectedElementIds[0] ?? selectedElementId.value,
    );
};
const onDeleteElement = async (elementId: string) => {
    if (!layout.value || !allowSave.value) {
        return;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);

    await executeStructuralDraftMutation(
        'remove',
        layoutElements,
        {
            elementId,
        },
        (response) => {
            if (!selectedElementId.value) {
                return null;
            }

            const selectedLocation = findElementLocation(
                castContentElementNodes(response.layout as ContentElementNode[]),
                selectedElementId.value,
            );

            return selectedLocation ? selectedElementId.value : null;
        },
    );
};
const onMoveElement = async (payload: MoveElementPayload) => {
    if (!layout.value || !allowSave.value) {
        return;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);
    const normalizedMoveIndex = normalizeMoveIndex(layoutElements, payload);

    await executeStructuralDraftMutation(
        'move',
        layoutElements,
        {
            elementId: payload.elementId,
            newParentId: payload.newParentElementId,
            newSlot: payload.newSlotName,
            index: normalizedMoveIndex,
        },
        () => payload.elementId,
    );
};
const normalizeMoveIndex = (layout: ContentElementNode[], payload: MoveElementPayload) => {
    if (payload.newIndex === null || payload.newIndex === undefined) {
        return null;
    }

    const sourceLocation = findElementLocation(layout, payload.elementId);

    if (!sourceLocation) {
        return payload.newIndex;
    }

    const targetElements = resolveMoveTargetElements(layout, payload.newParentElementId, payload.newSlotName);

    if (!targetElements || sourceLocation.elements !== targetElements) {
        return payload.newIndex;
    }

    if (sourceLocation.index < payload.newIndex) {
        return payload.newIndex - 1;
    }

    return payload.newIndex;
};
const resolveMoveTargetElements = (
    layout: ContentElementNode[],
    newParentElementId: string | null,
    newSlotName: string | null,
) => {
    if (newParentElementId === null) {
        return layout;
    }

    if (!newSlotName) {
        return null;
    }

    const targetParentLocation = findElementLocation(layout, newParentElementId);
    const targetParentElement = targetParentLocation ? targetParentLocation.elements[targetParentLocation.index] : null;

    if (!targetParentElement) {
        return null;
    }

    return targetParentElement.slots?.[newSlotName] ?? [];
};
const validateMoveTarget = (payload: MoveElementPayload) => {
    if (!layout.value) {
        return false;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);
    const draggedLocation = findElementLocation(layoutElements, payload.elementId);
    const draggedElement = draggedLocation ? draggedLocation.elements[draggedLocation.index] : null;

    if (!draggedElement) {
        return false;
    }

    if (payload.newParentElementId === null) {
        return true;
    }

    if (!payload.newSlotName) {
        return false;
    }

    const targetParentLocation = findElementLocation(layoutElements, payload.newParentElementId);
    const targetParentElement = targetParentLocation ? targetParentLocation.elements[targetParentLocation.index] : null;

    if (!targetParentElement) {
        return false;
    }

    if (isElementInSubtree(draggedElement, payload.newParentElementId)) {
        return false;
    }

    return canInsertIntoSlot(
        targetParentElement.component,
        payload.newSlotName,
        draggedElement.component,
        targetParentElement,
        payload.elementId,
    );
};
const onElementSettingsChange = (payload: { elementId: string; properties: Record<string, unknown> }) => {
    applyLayoutMutation((layout) => {
        return updateElementPropertiesInLayout(layout, payload.elementId, payload.properties) ? {} : false;
    });
};
const onElementStyleChange = (payload: { elementId: string; style: Record<string, unknown> }) => {
    applyLayoutMutation((layout) => {
        return updateElementStyleInLayout(layout, payload.elementId, payload.style) ? {} : false;
    });
};
const draftMutationService = () => {
    return Contena.Service('contentSystemLayoutDraftMutationService') as ContentSystemLayoutDraftMutationService;
};
const resolveMutationRootSource = () => {
    return getLayoutRootSource(layout.value);
};
const extractMutationErrorCodes = (error: unknown) => {
    const responseErrors = (
        error as {
            response?: {
                data?: {
                    errors?: Array<{ code?: unknown }>;
                };
            };
        }
    ).response?.data?.errors;

    if (!Array.isArray(responseErrors)) {
        return [];
    }

    return responseErrors
        .map((item) => (typeof item.code === 'string' ? item.code : null))
        .filter((code): code is string => code !== null);
};
const notifyMutationError = (codes: string[]) => {
    const structuralErrorCodes = new Set([
        'CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND',
        'CONTENT_SYSTEM__MUTATION_CYCLE',
        'CONTENT_SYSTEM__MUTATION_SLOT_REQUIRED',
        'CONTENT_SYSTEM__MUTATION_INVALID_WRAP_TARGETS',
        'CONTENT_SYSTEM__MUTATION_UNKNOWN_TYPE',
        'CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE',
        'CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE',
    ]);

    if (codes.some((code) => structuralErrorCodes.has(code))) {
        createNotificationError({
            message: 'The layout edit is not valid in the current structure. Please review your change and try again.',
        });

        return;
    }

    createNotificationError({
        message: 'The layout edit failed. Please try again.',
    });
};
const createDraftMutationPayload = (layout: ContentElementNode[], operationPayload: Record<string, unknown>) => {
    return {
        layout: sanitizeLayoutForWrite(layout),
        rootSource: resolveMutationRootSource(),
        ...operationPayload,
    };
};
const requestDraftMutation = async (
    operation: DraftMutationOperation,
    layout: ContentElementNode[],
    operationPayload: Record<string, unknown>,
) => {
    const service = draftMutationService();
    const payload = createDraftMutationPayload(layout, operationPayload);

    if (operation === 'insert') {
        return service.insertElement(payload as ContentLayoutDraftInsertPayload);
    }

    if (operation === 'remove') {
        return service.removeElement(payload as ContentLayoutDraftRemovePayload);
    }

    if (operation === 'move') {
        return service.moveElement(payload as ContentLayoutDraftMovePayload);
    }

    return service.duplicateElement(payload as ContentLayoutDraftDuplicatePayload);
};
const executeStructuralDraftMutation = async (
    operation: DraftMutationOperation,
    currentLayout: ContentElementNode[],
    operationPayload: Record<string, unknown>,
    resolveSelectedElementId: SelectedElementIdResolver,
) => {
    if (!layout.value || !allowSave.value) {
        return;
    }

    const requestId = mutationRequestSequence.value + 1;
    mutationRequestSequence.value = requestId;
    latestMutationRequestId.value = requestId;
    isLoading.value = true;

    const previousSelectedElementId = selectedElementId.value;

    try {
        const response = await requestDraftMutation(operation, currentLayout, operationPayload);

        if (requestId !== latestMutationRequestId.value) {
            return;
        }

        editorStore.value.pushToHistory(currentLayout, previousSelectedElementId);
        layout.value.layout = sanitizeLayoutForWrite(response.layout as ContentElementNode[]);
        selectedElementId.value = resolveSelectedElementId(response);
    } catch (error) {
        if (requestId !== latestMutationRequestId.value) {
            return;
        }

        notifyMutationError(extractMutationErrorCodes(error));
    } finally {
        if (requestId === latestMutationRequestId.value) {
            isLoading.value = false;
        }
    }
};
const getAvailableTypesForPayload = (payload: AddElementPayload | null) => {
    const allTypes = elementTypeStore.value.allTypes;

    if (!payload) {
        return [];
    }

    if (payload.parentElementId === null) {
        return allTypes;
    }

    if (!payload.slotName || !layout.value) {
        return [];
    }

    const parentLocation = findElementLocation(castContentElementNodes(layout.value.layout), payload.parentElementId);
    const parentElement = parentLocation ? parentLocation.elements[parentLocation.index] : null;

    if (!parentElement) {
        return [];
    }

    const parentType = elementTypeStore.value.getByName(parentElement.component);
    const slotDefinition = parentType?.slots.find((slot) => slot.name === payload.slotName);

    if (!slotDefinition) {
        return allTypes;
    }

    const existingElements = parentElement.slots?.[payload.slotName] ?? [];

    if (slotDefinition.maxElements !== null && existingElements.length >= slotDefinition.maxElements) {
        return [];
    }

    if (slotDefinition.allowList.length === 0) {
        return allTypes;
    }

    return slotDefinition.allowList
        .map((typeName) => elementTypeStore.value.getByName(typeName))
        .filter((type): type is ContentSystemElementTypeSpecification => type !== null);
};
const canInsertIntoSlot = (
    parentComponent: string,
    slotName: string,
    childComponent: string,
    parentElement: ContentElementNode,
    ignoreElementId: string | null = null,
) => {
    const parentType = elementTypeStore.value.getByName(parentComponent);
    const slotDefinition = parentType?.slots.find((slot) => slot.name === slotName);

    if (!slotDefinition) {
        return true;
    }

    const existingElements = (parentElement.slots?.[slotName] ?? []).filter((element) => {
        return ignoreElementId === null || element.id !== ignoreElementId;
    });

    if (slotDefinition.maxElements !== null && existingElements.length >= slotDefinition.maxElements) {
        return false;
    }

    if (slotDefinition.allowList.length === 0) {
        return true;
    }

    return slotDefinition.allowList.includes(childComponent);
};
const isElementInSubtree = (element: ContentElementNode, soughtElementId: string) => {
    if (element.id === soughtElementId) {
        return true;
    }

    for (const slotElements of Object.values(element.slots ?? {})) {
        for (const childElement of slotElements) {
            if (isElementInSubtree(childElement, soughtElementId)) {
                return true;
            }
        }
    }

    return false;
};
const clearInlineEditSession = () => {
    inlineEditSession.value = null;
};
const findElementById = (elementId: string) => {
    if (!layout.value) {
        return null;
    }

    const location = findElementLocation(castContentElementNodes(layout.value.layout), elementId);

    if (!location) {
        return null;
    }

    return location.elements[location.index] ?? null;
};
const isTextElement = (element: ContentElementNode | null) => {
    if (!element) {
        return false;
    }

    const typeSpecification = elementTypeStore.value.getByName(element.component);

    if (!typeSpecification) {
        return false;
    }

    if (typeSpecification.name.endsWith(':text')) {
        return true;
    }

    return typeSpecification.properties.text?.adminUI?.component === 'text-editor';
};
const getElementTextValue = (element: ContentElementNode | null) => {
    if (!element) {
        return '';
    }

    return typeof element.properties?.text === 'string' ? element.properties.text : '';
};
const onUndo = () => {
    if (!layout.value || !canUndo.value) {
        return;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);
    const previousEntry = editorStore.value.undo(layoutElements, selectedElementId.value);

    if (!previousEntry) {
        return;
    }

    layout.value.layout = previousEntry.layout;
    selectedElementId.value = previousEntry.selectedElementId;
};
const onRedo = () => {
    if (!layout.value || !canRedo.value) {
        return;
    }

    const layoutElements = castContentElementNodes(layout.value.layout);
    const nextEntry = editorStore.value.redo(layoutElements, selectedElementId.value);

    if (!nextEntry) {
        return;
    }

    layout.value.layout = nextEntry.layout;
    selectedElementId.value = nextEntry.selectedElementId;
};
const onHistoryKeydown = (event: KeyboardEvent) => {
    if (!allowSave.value) {
        return;
    }

    const target = event.target;

    if (
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        (target instanceof HTMLElement && target.isContentEditable)
    ) {
        return;
    }

    const isModifierPressed = event.ctrlKey || event.metaKey;

    if (!isModifierPressed) {
        return;
    }

    if (event.key === 'z' && !event.shiftKey) {
        event.preventDefault();
        onUndo();
        return;
    }

    if ((event.key === 'z' && event.shiftKey) || event.key === 'y') {
        event.preventDefault();
        onRedo();
    }
};
const onSave = async () => {
    if (!layout.value || !allowSave.value) {
        return;
    }
    if (!layout.value.name?.trim() || !layoutRootSource.value) {
        createNotificationWarning({
            message: t('ct-experience-studio.createWizard.missingFields'),
        });

        return;
    }
    const layoutValue = layout.value;
    layoutValue.layout = sanitizeLayoutForWrite(castContentElementNodes(layoutValue.layout));
    isLoading.value = true;
    try {
        await layoutRepository.value.save(layoutValue, Contena.Context.api);
        layout.value = await layoutRepository.value.get(layoutValue.id, Contena.Context.api, layoutLoadCriteria.value);
        applyPreviewContextDefaults();
        createNotificationSuccess({
            message: t('ct-experience-studio.detail.messageSaved'),
        });

        if (isCreateMode.value) {
            void router.push({
                name: 'ct.experience.studio.detail',
                params: { id: layoutValue.id },
            });
        }
    } finally {
        isLoading.value = false;
    }
};

Contena.Store.get('adminMenu').collapseSidebar();
historyKeydownHandler.value = (event: KeyboardEvent): void => {
    onHistoryKeydown(event);
};
void loadLayout();
void loadDefaultPreviewChannel();
void loadElementTypes();
void loadStyleOptions();
void loadLayoutTypes();

onMounted(() => {
    if (historyKeydownHandler.value) {
        document.addEventListener('keydown', historyKeydownHandler.value);
    }
});
onBeforeUnmount(() => {
    if (historyKeydownHandler.value) {
        document.removeEventListener('keydown', historyKeydownHandler.value);
    }

    editorStore.value.reset();
});

swDefinePublic({
    repositoryFactory,
    acl,
    layout,
    isLoading,
    isSaveSuccessful,
    currentViewport,
    selectedElementId,
    previewChannelId,
    previewEntityId,
    historyKeydownHandler,
    isElementPickerOpen,
    pendingAddElementPayload,
    pickerTop,
    pickerLeft,
    inlineEditSession,
    mutationRequestSequence,
    latestMutationRequestId,
    availableLayoutTypes,
    isLoadingLayoutTypes,
    layoutTypeLoadError,
    createWizardName,
    createWizardSelectedType,
    isAssignmentLoading,
    layoutRepository,
    channelRepository,
    defaultChannelCriteria,
    defaultPreviewEntityCriteria,
    layoutId,
    requestedRootSource,
    requestedEntityId,
    requestedChannelId,
    layoutRootSource,
    layoutLoadCriteria,
    resolvedPreviewContext,
    previewEntityType,
    showPreviewEntitySelect,
    allowSave,
    assignmentConfig,
    currentAssignment,
    isCurrentPreviewAssigned,
    allowAssign,
    isCreateMode,
    showCreateWizard,
    hasCreateLayoutMetadata,
    layoutTypeOptions,
    editorStore,
    elementTypeStore,
    styleOptionStore,
    canUndo,
    canRedo,
    selectedElement,
    selectedElementType,
    availablePickerElements,
    isInlineEditing,
    sanitizeLayoutForWrite,
    loadLayout,
    onClickBack,
    onViewportChange,
    loadDefaultPreviewChannel,
    loadDefaultPreviewEntity,
    applyPreviewContextDefaults,
    onCreateWizardNameChange,
    onCreateWizardTypeChange,
    onCreateWizardCancel,
    getLayoutTypeIcon,
    onCreateWizardComplete,
    isSectionRootSource,
    getLayoutRootSource,
    getFirstAssociationEntry,
    getAssociationEntries,
    resolveAssignedPreviewContext,
    resolvePreviewContext,
    loadElementTypes,
    loadStyleOptions,
    entityTypeService,
    loadLayoutTypes,
    onPreviewChannelChange,
    onPreviewEntityIdChange,
    onAssignLayout,
    onUnassignLayout,
    onSelectElement,
    onInlineEditStart,
    onInlineEditChange,
    onInlineEditCommit,
    onInlineEditCancel,
    onAddElement,
    onCloseElementPicker,
    onSelectElementType,
    applyLayoutMutation,
    onDuplicateElement,
    onDeleteElement,
    onMoveElement,
    normalizeMoveIndex,
    resolveMoveTargetElements,
    validateMoveTarget,
    onElementSettingsChange,
    onElementStyleChange,
    draftMutationService,
    resolveMutationRootSource,
    extractMutationErrorCodes,
    notifyMutationError,
    createDraftMutationPayload,
    requestDraftMutation,
    executeStructuralDraftMutation,
    getAvailableTypesForPayload,
    canInsertIntoSlot,
    isElementInSubtree,
    clearInlineEditSession,
    findElementById,
    isTextElement,
    getElementTextValue,
    onUndo,
    onRedo,
    onHistoryKeydown,
    onSave,
});

defineExpose({
    repositoryFactory,
    acl,
    layout,
    isLoading,
    isSaveSuccessful,
    currentViewport,
    selectedElementId,
    previewChannelId,
    previewEntityId,
    historyKeydownHandler,
    isElementPickerOpen,
    pendingAddElementPayload,
    pickerTop,
    pickerLeft,
    inlineEditSession,
    mutationRequestSequence,
    latestMutationRequestId,
    availableLayoutTypes,
    isLoadingLayoutTypes,
    layoutTypeLoadError,
    createWizardName,
    createWizardSelectedType,
    isAssignmentLoading,
    layoutRepository,
    channelRepository,
    defaultChannelCriteria,
    defaultPreviewEntityCriteria,
    layoutId,
    requestedRootSource,
    requestedEntityId,
    requestedChannelId,
    layoutRootSource,
    layoutLoadCriteria,
    resolvedPreviewContext,
    previewEntityType,
    showPreviewEntitySelect,
    allowSave,
    assignmentConfig,
    currentAssignment,
    isCurrentPreviewAssigned,
    allowAssign,
    isCreateMode,
    showCreateWizard,
    hasCreateLayoutMetadata,
    layoutTypeOptions,
    editorStore,
    elementTypeStore,
    styleOptionStore,
    canUndo,
    canRedo,
    selectedElement,
    selectedElementType,
    availablePickerElements,
    isInlineEditing,
    sanitizeLayoutForWrite,
    loadLayout,
    onClickBack,
    onViewportChange,
    loadDefaultPreviewChannel,
    loadDefaultPreviewEntity,
    applyPreviewContextDefaults,
    onCreateWizardNameChange,
    onCreateWizardTypeChange,
    onCreateWizardCancel,
    getLayoutTypeIcon,
    onCreateWizardComplete,
    isSectionRootSource,
    getLayoutRootSource,
    getFirstAssociationEntry,
    getAssociationEntries,
    resolveAssignedPreviewContext,
    resolvePreviewContext,
    loadElementTypes,
    loadStyleOptions,
    entityTypeService,
    loadLayoutTypes,
    onPreviewChannelChange,
    onPreviewEntityIdChange,
    onAssignLayout,
    onUnassignLayout,
    onSelectElement,
    onInlineEditStart,
    onInlineEditChange,
    onInlineEditCommit,
    onInlineEditCancel,
    onAddElement,
    onCloseElementPicker,
    onSelectElementType,
    applyLayoutMutation,
    onDuplicateElement,
    onDeleteElement,
    onMoveElement,
    normalizeMoveIndex,
    resolveMoveTargetElements,
    validateMoveTarget,
    onElementSettingsChange,
    onElementStyleChange,
    draftMutationService,
    resolveMutationRootSource,
    extractMutationErrorCodes,
    notifyMutationError,
    createDraftMutationPayload,
    requestDraftMutation,
    executeStructuralDraftMutation,
    getAvailableTypesForPayload,
    canInsertIntoSlot,
    isElementInSubtree,
    clearInlineEditSession,
    findElementById,
    isTextElement,
    getElementTextValue,
    onUndo,
    onRedo,
    onHistoryKeydown,
    onSave,
});
</script>
