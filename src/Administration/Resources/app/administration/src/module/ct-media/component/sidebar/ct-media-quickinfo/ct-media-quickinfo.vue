<template>
    <ct-block name="ct_media_quickinfo">
        <div class="ct-media-quickinfo">
            <ct-block name="ct_media_quickinfo_broken_file">
                <mt-banner
                    v-if="!item.hasFile"
                    class="ct-media-quickinfo__alert-file-missing"
                    variant="attention"
                    :title="translate('ct-media.sidebar.infoMissingFile.titleMissingFile')"
                >
                    {{ translate('ct-media.sidebar.infoMissingFile.descriptionMissingFile') }}
                </mt-banner>
            </ct-block>

            <ct-block name="ct_media_quickinfo_quickactions">
                <ct-media-collapse
                    v-if="editable"
                    class="ct-media-quickinfo__section ct-media-quickinfo__section--actions"
                    :title="translate('ct-media.sidebar.sections.actions')"
                    :expand-on-loading="true"
                >
                    <template #content>
                        <ct-block name="ct_media_quickinfo_quickactions_content">
                            <ul :key="item.id" class="ct-media-sidebar__quickactions-list">
                                <ct-block name="ct_media_quickinfo_quickactions_replace">
                                    <li
                                        v-if="!item.private"
                                        v-tooltip="editorTooltip"
                                        class="quickaction--replace"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalReplace"
                                        @keydown.enter="openModalReplace"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-files"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.replace') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_quickactions_download">
                                    <template v-if="item.hasFile">
                                        <li
                                            v-if="item.private"
                                            class="ct-media-sidebar__quickaction quickaction--download"
                                            role="button"
                                            tabindex="0"
                                            @click="downloadMedia()"
                                            @keydown.enter="downloadMedia()"
                                        >
                                            <mt-icon
                                                size="16px"
                                                name="regular-cloud-download"
                                                class="ct-media-sidebar__quickactions-icon"
                                            />
                                            {{ translate('ct-media.sidebar.actions.download') }}
                                        </li>
                                        <li v-else class="ct-media-sidebar__quickaction quickaction--download">
                                            <!-- TODO Codemod: Converted from ct-external-link - please check if everything works correctly -->
                                            <mt-external-link :href="item.url" download>
                                                <mt-icon
                                                    size="16px"
                                                    name="regular-cloud-download"
                                                    class="ct-media-sidebar__quickactions-icon"
                                                />
                                                {{ translate('ct-media.sidebar.actions.download') }}
                                            </mt-external-link>
                                        </li>
                                    </template>
                                </ct-block>
                                <ct-block name="ct_media_quickinfo_quickactions_move">
                                    <li
                                        v-tooltip="editorTooltip"
                                        class="quickaction--move"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalMove"
                                        @keydown.enter="openModalMove"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-file-export"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.move') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_quickactions_set_cover">
                                    <li
                                        v-if="canManageVideoCover"
                                        v-tooltip="editorTooltip"
                                        class="quickaction--set-cover"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openCoverSelectionModal"
                                        @keydown.enter="openCoverSelectionModal"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-image"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.setCover') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_quickactions_remove_cover">
                                    <li
                                        v-if="canManageVideoCover && hasVideoCover"
                                        v-tooltip="editorTooltip"
                                        class="quickaction--remove-cover"
                                        :class="quickActionClasses(!acl.can('media.editor'))"
                                        role="button"
                                        tabindex="0"
                                        @click="removeVideoCover"
                                        @keydown.enter="removeVideoCover"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-times"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.removeCover') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_quickactions_copy_link">
                                    <li
                                        v-if="item.hasFile"
                                        class="ct-media-sidebar__quickaction quickaction--copy-link"
                                        role="button"
                                        tabindex="0"
                                        @click="copyLinkToClipboard"
                                        @keydown.enter="copyLinkToClipboard"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-link"
                                            class="ct-media-sidebar__quickactions-icon"
                                        />
                                        {{ translate('ct-media.sidebar.actions.copyLink') }}
                                    </li>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_quickactions_delete">
                                    <li
                                        v-if="!item.private"
                                        v-tooltip="deleterTooltip"
                                        class="quickaction--delete"
                                        :class="quickActionClasses(!acl.can('media.deleter'))"
                                        role="button"
                                        tabindex="0"
                                        @click="openModalDelete"
                                        @keydown.enter="openModalDelete"
                                    >
                                        <mt-icon
                                            size="16px"
                                            name="regular-trash"
                                            class="ct-media-sidebar__quickactions-icon is--danger"
                                        />
                                        {{ translate('ct-media.sidebar.actions.delete') }}
                                    </li>
                                </ct-block>
                            </ul>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_spatial_configuration">
                <ct-media-collapse
                    v-if="isSpatial"
                    :title="translate('ct-media.sidebar.sections.configuration')"
                    :expand-on-loading="true"
                >
                    <template #content>
                        <ct-inherit-wrapper
                            v-model:value="arReady"
                            :inherited-value="defaultArReady"
                            @update:value="toggleAR"
                        >
                            <template #content="inheritanceProps">
                                <mt-switch
                                    :is-inheritance-field="inheritanceProps.isInheritField"
                                    :is-inherited="inheritanceProps.isInherited"
                                    :help-text="buildAugmentedRealityTooltip('ct-media.sidebar.actions.arHelpText')"
                                    :label="translate('ct-media.sidebar.actions.ar')"
                                    :disabled="inheritanceProps.isInherited || !editable"
                                    :model-value="inheritanceProps.currentValue"
                                    class="ct-media-sidebar__quickactions-switch ar-ready-toggle"
                                    @inheritance-restore="inheritanceProps.restoreInheritance"
                                    @inheritance-remove="inheritanceProps.removeInheritance"
                                    @update:model-value="inheritanceProps.updateCurrentValue"
                                />
                            </template>
                        </ct-inherit-wrapper>

                        <ct-inherit-wrapper
                            v-if="arReady"
                            v-model:value="arPlacement"
                            :inherited-value="defaultArPlacement"
                            @update:value="changeARPlacement"
                        >
                            <template #content="inheritanceProps">
                                <mt-select
                                    :is-inheritance-field="inheritanceProps.isInheritField"
                                    :is-inherited="inheritanceProps.isInherited"
                                    :label="translate('ct-media.sidebar.actions.arPlacement')"
                                    :help-text="translate('ct-media.sidebar.actions.arPlacementHelpText')"
                                    :disabled="inheritanceProps.isInherited || !editable"
                                    :model-value="inheritanceProps.currentValue"
                                    :options="arPlacementOptions"
                                    @inheritance-restore="inheritanceProps.restoreInheritance"
                                    @inheritance-remove="inheritanceProps.removeInheritance"
                                    @update:model-value="inheritanceProps.updateCurrentValue"
                                />
                            </template>
                        </ct-inherit-wrapper>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_preview">
                <ct-media-collapse
                    v-if="item.hasFile"
                    class="ct-media-quickinfo__section ct-media-quickinfo__section--preview"
                    :expand-on-loading="true"
                    :title="translate('ct-media.sidebar.sections.preview')"
                >
                    <template #content>
                        <ct-block name="ct_media_quickinfo_preview_content">
                            <div>
                                <ct-block name="ct_media_quickinfo_unsupported_format_banner">
                                    <mt-banner
                                        v-if="showUnsupportedFormatWarning"
                                        variant="attention"
                                        class="ct-media-quickinfo__unsupported-format-banner"
                                    >
                                        {{
                                            translate('global.ct-media-preview-v2.warningUnsupportedFormat', {
                                                format: item.mimeType,
                                            })
                                        }}
                                    </mt-banner>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_preview_item">
                                    <ct-media-preview-v2
                                        class="ct-media-quickinfo__media-preview"
                                        :source="item.id"
                                        :show-controls="true"
                                        :use-thumbnails="false"
                                    />
                                </ct-block>
                            </div>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_metadata">
                <ct-media-collapse
                    v-if="item.hasFile"
                    class="ct-media-quickinfo__section ct-media-quickinfo__section--metadata"
                    :expand-on-loading="true"
                    :title="translate('ct-media.sidebar.sections.metadata')"
                >
                    <template #content>
                        <ct-block name="ct_media_quickinfo_metadata_content">
                            <dl class="ct-media-sidebar__metadata-list">
                                <ct-block name="ct_media_quickinfo_metadata_content_base">
                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-name"
                                        :class="fileNameClasses"
                                        :label-name="translate('ct-media.sidebar.metadata.name')"
                                        :truncated="false"
                                    >
                                        <ct-confirm-field
                                            v-if="editable"
                                            ref="inlineEditFieldName"
                                            class="ct-media-quickinfo-metadata-name"
                                            :disabled="!acl.can('media.editor')"
                                            compact
                                            :value="item.fileName"
                                            :error="fileNameError"
                                            @input="onChangeFileName"
                                            @remove-error="onRemoveFileNameError"
                                        /><template v-else>
                                            {{ item.fileName }}
                                        </template>
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-file-type"
                                        :label-name="translate('ct-media.sidebar.metadata.fileType')"
                                    >
                                        {{ item.fileExtension.toUpperCase() }}
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-alt-field"
                                        :label-name="translate('ct-media.sidebar.metadata.altText')"
                                        :truncated="false"
                                    >
                                        <ct-confirm-field
                                            v-if="editable"
                                            ref="inlineEditFieldAlt"
                                            :disabled="!acl.can('media.editor')"
                                            compact
                                            :placeholder="
                                                placeholder(item, 'alt', translate('ct-media.sidebar.metadata.altText'))
                                            "
                                            :value="item.alt"
                                            @input="onSubmitAltText"
                                        />
                                        <template v-else>
                                            {{ placeholder(item, 'alt') }}
                                        </template>
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-alt-field"
                                        :label-name="translate('ct-media.sidebar.metadata.title')"
                                        :truncated="false"
                                    >
                                        <ct-confirm-field
                                            v-if="editable"
                                            ref="inlineEditFieldTitle"
                                            :disabled="!acl.can('media.editor')"
                                            compact
                                            :placeholder="
                                                placeholder(item, 'title', translate('ct-media.sidebar.metadata.title'))
                                            "
                                            :value="item.title"
                                            @input="onSubmitTitle"
                                        />
                                        <template v-else>
                                            {{ placeholder(item, 'title') }}
                                        </template>
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-mimeType"
                                        :label-name="translate('ct-media.sidebar.metadata.mimeType')"
                                    >
                                        {{ item.mimeType }}
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-size"
                                        :label-name="translate('ct-media.sidebar.metadata.fileSize')"
                                    >
                                        {{ fileSize }}
                                    </ct-media-quickinfo-metadata-item>

                                    <ct-media-quickinfo-metadata-item
                                        class="ct-media-quickinfo-metadata-createdAt"
                                        :label-name="translate('ct-media.sidebar.metadata.createdAt')"
                                    >
                                        {{ createdAt }}
                                    </ct-media-quickinfo-metadata-item>
                                </ct-block>

                                <template v-if="item.metaData">
                                    <ct-block name="ct_media_quickinfo_metadata_specific_meta_data">
                                        <template v-if="item.mediaType.name === 'IMAGE'">
                                            <ct-block name="ct_media_quickinfo_metadata_content_image">
                                                <ct-media-quickinfo-metadata-item
                                                    v-if="item.metaData.width"
                                                    class="ct-media-quickinfo-metadata-width"
                                                    :label-name="translate('ct-media.sidebar.metadata.width')"
                                                >
                                                    {{ item.metaData.width }}px
                                                </ct-media-quickinfo-metadata-item>

                                                <ct-media-quickinfo-metadata-item
                                                    v-if="item.metaData.height"
                                                    class="ct-media-quickinfo-metadata-height"
                                                    :label-name="translate('ct-media.sidebar.metadata.height')"
                                                >
                                                    {{ item.metaData.height }}px
                                                </ct-media-quickinfo-metadata-item>
                                            </ct-block>
                                        </template>
                                    </ct-block>
                                </template>
                            </dl>
                        </ct-block>
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_tags">
                <ct-media-tag :disabled="!acl.can('media.editor')" :media="item" />
            </ct-block>

            <ct-block name="ct_media_quickinfo_usage">
                <ct-media-collapse
                    v-if="editable && item.hasFile"
                    :expand-on-loading="true"
                    :title="translate('ct-media.sidebar.sections.usage')"
                >
                    <template #content>
                        <ct-media-quickinfo-usage :item="item" />
                    </template>
                </ct-media-collapse>
            </ct-block>

            <ct-block name="ct_media_quickinfo_modal_replace">
                <ct-media-modal-replace
                    v-if="showModalReplace"
                    :item-to-replace="item"
                    @media-replace-modal-item-replaced="emitRefreshMediaLibrary"
                    @media-replace-modal-close="closeModalReplace"
                />
            </ct-block>

            <ct-block name="ct_media_quickinfo_modal_delete">
                <ct-media-modal-delete
                    v-if="showModalDelete"
                    :items-to-delete="[item]"
                    @media-delete-modal-close="closeModalDelete"
                    @media-delete-modal-items-delete="deleteSelectedItems"
                />
            </ct-block>

            <ct-block name="ct_media_quickinfo_move_modal">
                <ct-media-modal-move
                    v-if="showModalMove"
                    :items-to-move="[item]"
                    @media-move-modal-close="closeModalMove"
                    @media-move-modal-items-move="onFolderMoved"
                />
            </ct-block>

            <ct-block name="ct_media_quickinfo_cover_modal">
                <ct-media-modal-v2
                    v-if="showCoverSelectionModal"
                    :allow-multi-select="false"
                    file-accept="image/*"
                    @modal-close="closeCoverSelectionModal"
                    @media-modal-selection-change="onCoverSelectionChange"
                />
            </ct-block>

            <ct-block name="ct_media_quickinfo_custom_field_sets">
                <ct-custom-field-set-renderer
                    :key="item.id"
                    :disabled="!acl.can('media.editor')"
                    :entity="item"
                    variant="media-collapse"
                    :sets="customFieldSets"
                    :is-loading="isLoading"
                    :is-save-successful="isSaveSuccessful"
                    @save="onSave"
                    @process-finish="saveFinish"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';
import './ct-media-quickinfo.scss';
const { Context, Utils } = Contena;
const { dom, format } = Utils;

const props = defineProps({
    item: {
        required: true,
        type: Object,
        validator(value) {
            return value.getEntityName() === 'media';
        },
    },

    editable: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-item-rename-success',
    'media-item-replaced',
    'update:item',
    'media-sidebar-items-delete',
    'media-sidebar-folder-items-dissolve',
    'media-sidebar-items-move',
]);

import { ref, computed, inject, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePlaceholder } from 'src/app/composables/use-placeholder';
import useMediaSidebarModal from 'src/app/composables/use-media-sidebar-modal';
import useVideoCover from 'src/app/composables/use-video-cover';

const router = useRouter();
const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();
const { placeholder } = usePlaceholder();

const translate = t;

const mediaService = inject('mediaService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const customFieldDataProviderService = inject('customFieldDataProviderService');
const systemConfigApiService = inject('systemConfigApiService');

const customFieldSets = ref([]);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const showModalReplace = ref(false);
const fileNameError = ref(null);
const arReady = ref(false);
const defaultArReady = ref(false);
const arPlacement = ref('horizontal');
const defaultArPlacement = ref('horizontal');
const arPlacementOptions = ref([]);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const isMediaObject = computed(() => {
    return props.item.type === 'media';
});
const fileSize = computed(() => {
    return format.fileSize(props.item.fileSize);
});
const createdAt = computed(() => {
    const date = props.item.uploadedAt || props.item.createdAt;
    return format.date(date);
});
const fileNameClasses = computed(() => {
    return {
        'has--error': fileNameError.value,
    };
});
const isSpatial = computed(() => {
    // we need to check the media url since media.fileExtension is set directly after upload
    return props.item?.fileExtension === 'glb' || !!props.item?.url?.endsWith('.glb');
});
const isPlayable = computed(() => {
    return isPlayableMediaFormat(props.item.mimeType);
});
const showUnsupportedFormatWarning = computed(() => {
    return shouldShowUnsupportedFormatWarning(props.item.mimeType);
});
const canManageVideoCover = computed(() => {
    return isVideoMedia.value && isPlayable.value;
});
const editorTooltip = computed(() => {
    const isDisabled = !acl.can('media.editor');
    return {
        message: t('ct-privileges.tooltip.warning'),
        disabled: acl.can('media.editor'),
        showOnDisabledElements: isDisabled,
    };
});
const deleterTooltip = computed(() => {
    return {
        message: t('ct-privileges.tooltip.warning'),
        disabled: acl.can('media.deleter'),
        showOnDisabledElements: true,
    };
});
const fileName = computed(() => {
    if (props.item.fileExtension) {
        return `${props.item.fileName}.${props.item.fileExtension}`;
    }

    return props.item.fileName;
});

const createdComponent = () => {
    loadCustomFieldSets();
    fetchSpatialItemConfig();
};
const fetchSpatialItemConfig = () => {
    systemConfigApiService.getValues('core.media').then((values) => {
        defaultArReady.value = values['core.media.defaultEnableAugmentedReality'];
        defaultArPlacement.value = values['core.media.defaultARPlacement'];
    });

    systemConfigApiService.getConfig('core.media').then((config) => {
        config
            .flat()[0]
            .elements.filter((element) => element.name === 'core.media.defaultARPlacement')
            .forEach((element) => {
                arPlacementOptions.value = element.config.options.map((option) => {
                    return {
                        id: option.id,
                        value: option.id,
                        label: t(`ct-media.sidebar.actions.${option.id}`),
                    };
                });
            });
    });

    mediaRepository.value.get(props.item.id, Contena.Context.api).then((entity) => {
        arReady.value = entity?.config?.spatial?.arReady;
        arPlacement.value = entity?.config?.spatial?.arPlacement;
    });
};
const buildAugmentedRealityTooltip = (snippet) => {
    const route = { name: 'ct.settings.media.index' };
    const routeData = router.resolve(route);

    const data = {
        settingsLink: routeData.href,
    };

    return t(snippet, data);
};
const loadCustomFieldSets = () => {
    return customFieldDataProviderService.getCustomFieldSets('media').then((sets) => {
        customFieldSets.value = sets;
    });
};
const onSave = async () => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    try {
        await mediaRepository.value.save(props.item, Context.api);
        isSaveSuccessful.value = true;
    } catch (error) {
        createNotificationError({
            message: error.message,
        });
    } finally {
        isLoading.value = false;
        Contena.Utils.EventBus.emit('ct-media-library-item-updated', props.item.id);
    }
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const copyLinkToClipboard = async () => {
    if (props.item) {
        try {
            await dom.copyStringToClipboard(props.item.url);
            createNotificationSuccess({
                message: t('ct-media.general.notification.urlCopied.message'),
            });
        } catch (_err) {
            createNotificationError({
                message: t('global.ct-field.notification.notificationCopyFailureMessage'),
            });
        }
    }
};
const onSubmitTitle = (value) => {
    const item = props.item;
    item.title = value;

    return onSave();
};
const onSubmitAltText = (value) => {
    const item = props.item;
    item.alt = value;

    return onSave();
};
const onChangeFileName = async (value) => {
    const item = props.item;
    item.isLoading = true;
    fileNameError.value = null;

    try {
        await mediaService.renameMedia(item.id, value).catch((error) => {
            const fileNameErrorCodes = [
                'CONTENT__MEDIA_EMPTY_FILE',
                'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
            ];

            error.response.data.errors.forEach((mediaError) => {
                if (!fileNameError.value && fileNameErrorCodes.includes(mediaError.code)) {
                    fileNameError.value = mediaError;
                }
            });

            throw error;
        });
        item.fileName = value;
        createNotificationSuccess({
            message: t('global.ct-media-media-item.notification.renamingSuccess.message'),
        });
        emit('media-item-rename-success', item);
    } catch (exception) {
        exception.response.data.errors.forEach(handleErrorMessage);
    } finally {
        item.isLoading = false;
    }
};
const handleErrorMessage = (error) => {
    switch (error.code) {
        case 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG':
            createNotificationError({
                message: t('global.ct-media-media-item.notification.fileNameTooLong.message', {
                    length: error.meta.parameters.maxLength,
                }),
            });
            break;
        default:
            createNotificationError({
                message: t('global.ct-media-media-item.notification.renamingError.message'),
            });
    }
};
const openModalReplace = () => {
    if (!acl.can('media.editor')) {
        return;
    }

    showModalReplace.value = true;
};
const closeModalReplace = () => {
    showModalReplace.value = false;
};
const emitRefreshMediaLibrary = () => {
    closeModalReplace();

    void nextTick(() => {
        emit('media-item-replaced');
    });
};
const quickActionClasses = (disabled) => {
    return [
        'ct-media-sidebar__quickaction',
        {
            'ct-media-sidebar__quickaction--disabled': disabled,
        },
    ];
};
const onRemoveFileNameError = () => {
    fileNameError.value = null;
};
const toggleAR = (newValue) => {
    const newSpatialConfig = {
        spatial: {
            arReady: newValue,
            arPlacement: arPlacement.value,
            updatedAt: Date.now(),
        },
    };
    const newItemConfig = {
        config: {
            ...props.item.config,
            ...newSpatialConfig,
        },
    };

    emit('update:item', { ...props.item, ...newItemConfig });
};
const changeARPlacement = (newPlacement) => {
    const newSpatialConfig = {
        spatial: {
            arReady: arReady.value,
            arPlacement: newPlacement,
            updatedAt: Date.now(),
        },
    };
    const newItemConfig = {
        config: {
            ...props.item.config,
            ...newSpatialConfig,
        },
    };

    emit('update:item', { ...props.item, ...newItemConfig });
};
const downloadMedia = () => {
    mediaService
        .prepareDownloadMedia(props.item.id)
        .then((download) => {
            if (download.type === 'external') {
                triggerDownload(download.url);

                return;
            }

            return mediaService.downloadMedia(props.item.id).then((data) => {
                const url = window.URL.createObjectURL(data);
                triggerDownload(url, fileName.value);
                URL.revokeObjectURL(url);
            });
        })
        .catch(() => {
            createNotificationError({
                message: t('global.ct-media-media-item.notification.downloadError.message'),
            });
        });
};
const triggerDownload = (url, fileName = null) => {
    const link = document.createElement('a');
    link.href = url;

    if (fileName) {
        link.download = fileName;
    } else {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
    }

    link.dispatchEvent(new MouseEvent('click'));
    link.remove();
};
const {
    showModalDelete,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderMoved,
} = useMediaSidebarModal({
    onItemsDelete: (ids) => emit('media-sidebar-items-delete', ids),
    onFolderItemsDissolve: (ids) => emit('media-sidebar-folder-items-dissolve', ids),
    onItemsMove: (ids) => emit('media-sidebar-items-move', ids),
});
const {
    showCoverSelectionModal,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
} = useVideoCover({
    item: () => props.item,
});

watch(
    () => props.item.id,
    () => {
        fetchSpatialItemConfig();
        fileNameError.value = null;
    },
);

createdComponent();

ctDefinePublic({
    mediaService,
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    systemConfigApiService,
    customFieldSets,
    isLoading,
    isSaveSuccessful,
    showModalReplace,
    fileNameError,
    arReady,
    defaultArReady,
    arPlacement,
    defaultArPlacement,
    arPlacementOptions,
    showCoverSelectionModal,
    mediaRepository,
    isMediaObject,
    fileSize,
    createdAt,
    fileNameClasses,
    isSpatial,
    isPlayable,
    showUnsupportedFormatWarning,
    canManageVideoCover,
    editorTooltip,
    deleterTooltip,
    fileName,
    createdComponent,
    fetchSpatialItemConfig,
    buildAugmentedRealityTooltip,
    loadCustomFieldSets,
    onSave,
    saveFinish,
    copyLinkToClipboard,
    onSubmitTitle,
    onSubmitAltText,
    onChangeFileName,
    handleErrorMessage,
    openModalReplace,
    closeModalReplace,
    emitRefreshMediaLibrary,
    quickActionClasses,
    onRemoveFileNameError,
    toggleAR,
    changeARPlacement,
    downloadMedia,
    triggerDownload,
    showModalDelete,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderMoved,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
});

defineExpose({
    mediaService,
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    systemConfigApiService,
    customFieldSets,
    isLoading,
    isSaveSuccessful,
    showModalReplace,
    fileNameError,
    arReady,
    defaultArReady,
    arPlacement,
    defaultArPlacement,
    arPlacementOptions,
    showCoverSelectionModal,
    mediaRepository,
    isMediaObject,
    fileSize,
    createdAt,
    fileNameClasses,
    isSpatial,
    isPlayable,
    showUnsupportedFormatWarning,
    canManageVideoCover,
    editorTooltip,
    deleterTooltip,
    fileName,
    createdComponent,
    fetchSpatialItemConfig,
    buildAugmentedRealityTooltip,
    loadCustomFieldSets,
    onSave,
    saveFinish,
    copyLinkToClipboard,
    onSubmitTitle,
    onSubmitAltText,
    onChangeFileName,
    handleErrorMessage,
    openModalReplace,
    closeModalReplace,
    emitRefreshMediaLibrary,
    quickActionClasses,
    onRemoveFileNameError,
    toggleAR,
    changeARPlacement,
    downloadMedia,
    triggerDownload,
    showModalDelete,
    showModalMove,
    openModalDelete,
    closeModalDelete,
    openModalMove,
    closeModalMove,
    deleteSelectedItems,
    onFolderMoved,
    isVideoMedia,
    hasVideoCover,
    openCoverSelectionModal,
    closeCoverSelectionModal,
    onCoverSelectionChange,
    removeVideoCover,
});
</script>
