<template>
    <ct-block name="ct_media_modal_folder_settings">
        <ct-modal
            v-if="!!mediaFolder"
            class="ct-media-modal-folder-settings"
            :class="modalClass"
            :title="mediaFolder.name"
            variant="large"
            @modal-close="closeModal"
        >
            <ct-block name="ct_media_modal_folder_settings_tabs">
                <div position-identifier="ct-media-modal-folder-settings">
                    <mt-tabs :items="tabItems" default-item="settings" @new-item-active="onActiveTabChanged" />
                    <ct-block name="ct_media_modal_folder_settings_tab_content_settings">
                        <ct-container v-if="activeTab === 'settings'" columns="1fr 1fr" gap="32px">
                            <ct-block name="ct_media_modal_folder_settings_name_field">
                                <mt-text-field
                                    v-model="mediaFolder.name"
                                    :disabled="disabled"
                                    :error="mediaFolderNameError"
                                    :label="translate('global.ct-media-modal-folder-settings.labelFolderName')"
                                />
                            </ct-block>

                            <ct-block name="ct_media_modal_folder_settings_default_folder">
                                <ct-entity-single-select
                                    id="defaultFolder"
                                    :disabled="disabled"
                                    entity="media_default_folder"
                                    :placeholder="
                                        translate('global.ct-media-modal-folder-settings.placeholderDefaultFolder')
                                    "
                                    :value="mediaFolder.defaultFolderId ? mediaFolder.defaultFolderId : ''"
                                    :label="translate('global.ct-media-modal-folder-settings.labelDefaultFolder')"
                                    show-clearable-button
                                    @update:value="onInputDefaultFolder"
                                >
                                    <!-- eslint-disable-next-line vue/no-unused-vars -->
                                    <template #selection-label-property="{ item }">
                                        {{ getItemName(item) }}
                                    </template>

                                    <template
                                        #result-item="{ isSelected, setValue, item, index, searchTerm, highlightSearchTerm }"
                                    >
                                        <ct-select-result
                                            :selected="isSelected(item)"
                                            v-bind="{ item, index }"
                                            @item-select="setValue"
                                        >
                                            <ct-highlight-text
                                                v-if="highlightSearchTerm"
                                                :text="getItemName(item)"
                                                :search-term="searchTerm"
                                            />
                                            <template v-else>
                                                {{ getItemName(item) }}
                                            </template>
                                        </ct-select-result>
                                    </template>
                                </ct-entity-single-select>
                            </ct-block>
                        </ct-container>
                    </ct-block>

                    <ct-block name="ct_media_modal_folder_settings_tab_content_thumbnails">
                        <ct-container
                            v-if="activeTab === 'thumbnails'"
                            class="ct-media-modal-folder-settings__thumbnails-container"
                            columns="1fr 1fr"
                            gap="32px"
                        >
                            <ct-block name="ct_media_modal_folder_settings_tab_content_thumbnails_left_container">
                                <div class="ct-media-modal-folder-settings__thumbnails-left-container">
                                    <ct-block name="ct_media_modal_folder_settings_inherit_settings_field">
                                        <mt-switch
                                            v-model="mediaFolder.useParentConfiguration"
                                            :label="translate('global.ct-media-modal-folder-settings.labelInheritSettings')"
                                            :disabled="mediaFolder.parentId === null"
                                            @update:model-value="onChangeInheritance"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_media_modal_folder_settings_generate_thumbnails_field">
                                        <mt-switch
                                            v-model="configuration.createThumbnails"
                                            :label="
                                                translate('global.ct-media-modal-folder-settings.labelGenerateThumbnails')
                                            "
                                            :disabled="mediaFolder.useParentConfiguration || disabled"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_media_modal_folder_settings_keep_proportions_field">
                                        <mt-switch
                                            v-model="configuration.keepAspectRatio"
                                            :label="translate('global.ct-media-modal-folder-settings.labelKeepProportions')"
                                            :disabled="notEditable"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_media_modal_folder_settings_thumbnails_quality_field">
                                        <mt-number-field
                                            v-model="configuration.thumbnailQuality"
                                            number-type="int"
                                            :label="translate('global.ct-media-modal-folder-settings.labelThumbnailQuality')"
                                            :min="0"
                                            :max="100"
                                            :step="1"
                                            :disabled="notEditable"
                                            autocomplete="off"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_media_modal_folder_settings_tab_content_thumbnails_right_container">
                                <div class="ct-media-modal-folder-settings__thumbnails-right-container">
                                    <ct-block name="ct_media_modal_folder_settings_thumbnail_list_caption">
                                        <div class="ct-media-modal-folder-settings__thumbnails-list-caption">
                                            <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                                            <label>{{
                                                translate('global.ct-media-modal-folder-settings.labelThumbnailSize')
                                            }}</label>
                                        </div>
                                    </ct-block>

                                    <ct-block name="ct_media_modal_folder_settings_thumbnail_list_container">
                                        <div class="ct-media-modal-folder-settings__thumbnails-list-container">
                                            <ct-media-add-thumbnail-form
                                                v-if="!notEditable"
                                                :disabled="disabled"
                                                @on-input="checkIfThumbnailExists"
                                                @thumbnail-form-size-add="addThumbnail"
                                            />

                                            <ct-block name="ct_media_modal_folder_settings_thumbnail_list">
                                                <ul class="ct-media-modal-folder-settings__thumbnails-list">
                                                    <ct-block name="ct_media_modal_folder_settings_thumbnail_size">
                                                        <li
                                                            v-for="(size, index) in thumbnailSizes"
                                                            :key="`thumbnail-size-${index}`"
                                                            class="ct-media-modal-folder-settings__thumbnail-size-entry"
                                                            :class="'ct-media-modal-folder-settings__entry--' + index"
                                                        >
                                                            <ct-block
                                                                name="ct_media_modal_folder_settings_thumbnail_size_switch"
                                                            >
                                                                <mt-switch
                                                                    :model-value="isThumbnailSizeActive(size)"
                                                                    :name="thumbnailSizeCheckboxName(size)"
                                                                    :label="thumbnailSizeFilter(size)"
                                                                    :disabled="notEditable"
                                                                    @update:model-value="onChangeThumbnailSize($event, size)"
                                                                />
                                                            </ct-block>

                                                            <ct-block
                                                                name="ct_media_modal_folder_settings_thumbnail_size_delete_button"
                                                            >
                                                                <button
                                                                    v-tooltip="{
                                                                        message: translate(
                                                                            'global.ct-media-modal-folder-settings.tooltipCanNotDeleteThumbnailSize',
                                                                        ),
                                                                        disabled: size.deletable,
                                                                        showOnDisabledElements: true,
                                                                    }"
                                                                    class="ct-media-modal-folder-settings__delete-thumbnail"
                                                                    :title="translate('global.default.delete')"
                                                                    :aria-label="translate('global.default.delete')"
                                                                    :disabled="!size.deletable"
                                                                    @click="deleteThumbnail(size)"
                                                                >
                                                                    <mt-icon name="regular-times-s" size="12px" />
                                                                </button>
                                                            </ct-block>
                                                        </li>
                                                    </ct-block>
                                                </ul>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </ct-container>
                    </ct-block>
                </div>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_media_modal_folder_settings_footer">
                    <ct-block name="ct_media_modal_folder_settings_cancel_button">
                        <mt-button size="small" variant="secondary" @click="onClickCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_media_modal_folder_settings_confirm_button">
                        <mt-button
                            v-tooltip="{
                                message: translate('ct-privileges.tooltip.warning'),
                                disabled: !disabled,
                                showOnDisabledElements: true,
                            }"
                            class="ct-media-modal-folder-settings__confirm"
                            size="small"
                            :disabled="disabled"
                            variant="primary"
                            @click="onClickSave"
                        >
                            {{ translate('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-media-modal-folder-settings.scss';
const { Context } = Contena;
const { Criteria } = Contena.Data;

const props = defineProps({
    mediaFolderId: {
        required: true,
        type: String,
    },
    disabled: {
        required: false,
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits([
    'media-settings-modal-save',
    'media-settings-modal-close',
]);

import { ref, computed, inject, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const translate = t;
const repositoryFactory = inject('repositoryFactory');

const modalClass = ref('ct-media-modal-folder-settings--shows-overflow');
const activeTab = ref('settings');
const unusedThumbnailSizes = ref([]);
const thumbnailSizes = ref([]);
const parent = ref(null);
const configuration = ref(null);
const mediaFolderConfigurationThumbnailSizeRepository = ref(null);
const originalConfiguration = ref(null);
const mediaFolder = ref(null);

const tabItems = computed(() => {
    return [
        {
            label: t('global.ct-media-modal-folder-settings.labelSettings'),
            name: 'settings',
            hasError: !!mediaFolderNameError.value,
        },
        {
            label: t('global.ct-media-modal-folder-settings.labelThumbnails'),
            name: 'thumbnails',
        },
    ];
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaDefaultFolderRepository = computed(() => {
    return repositoryFactory.create('media_default_folder');
});
const mediaThumbnailSizeRepository = computed(() => {
    return repositoryFactory.create('media_thumbnail_size');
});
const mediaFolderConfigurationRepository = computed(() => {
    return repositoryFactory.create('media_folder_configuration');
});
const unusedMediaThumbnailSizeCriteria = computed(() => {
    const criteria = new Criteria(1, null);
    criteria.addFilter(Criteria.equals('mediaFolderConfigurations.mediaFolders.id', null));

    return criteria;
});
const mediaThumbnailSizeCriteria = new Criteria(1, null);
mediaThumbnailSizeCriteria.addSorting(Criteria.sort('width'));
const notEditable = computed(() => {
    return (
        !mediaFolder.value ||
        !configuration.value ||
        mediaFolder.value.useParentConfiguration ||
        !configuration.value.createThumbnails ||
        props.disabled
    );
});
const thumbnailSizeFilter = computed(() => {
    return Contena.Filter.getByName('thumbnailSize');
});
const mediaFolderNameError = computed(() => {
    const entity = mediaFolder.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});

const createdComponent = async () => {
    mediaFolder.value = await loadMediaFolder();

    await getUnusedThumbnailSizes();
    await getThumbnailSizes();
    configuration.value = await mediaFolderConfigurationRepository.value.get(mediaFolder.value.configurationId, Context.api);

    mediaFolderConfigurationThumbnailSizeRepository.value = repositoryFactory.create(
        configuration.value.mediaThumbnailSizes.entity,
        configuration.value.mediaThumbnailSizes.source,
    );

    configuration.value.mediaThumbnailSizes = await mediaFolderConfigurationThumbnailSizeRepository.value.search(
        new Criteria(1, 25),
        Context.api,
    );

    if (mediaFolder.value.parentId !== null) {
        parent.value = await mediaFolderRepository.value.get(mediaFolder.value.parentId, Context.api);
        parent.value.configuration = await mediaFolderConfigurationRepository.value.get(
            parent.value.configurationId,
            Context.api,
        );
    }
};
const getItemName = (item) => {
    const entityNameIdentifier = `global.entities.${item.entity}`;

    return `${item.entity} (${t(entityNameIdentifier)})`;
};
async function getUnusedThumbnailSizes() {
    const response = await mediaThumbnailSizeRepository.value.searchIds(unusedMediaThumbnailSizeCriteria.value);
    unusedThumbnailSizes.value = response.data;
}
async function getThumbnailSizes() {
    thumbnailSizes.value = await mediaThumbnailSizeRepository.value.search(mediaThumbnailSizeCriteria);
    thumbnailSizes.value.forEach((thumbnailSize) => {
        thumbnailSize.deletable = Boolean(
            unusedThumbnailSizes.value.find((unusedThumbnailSize) => {
                return unusedThumbnailSize === thumbnailSize.id;
            }),
        );
    });
}
const addThumbnail = async ({ width, height }) => {
    if (checkIfThumbnailExists({ width, height })) {
        createNotificationError({
            message: t('global.ct-media-modal-folder-settings.notification.error.messageThumbnailSizeExisted'),
        });

        return;
    }

    const thumbnailSize = mediaThumbnailSizeRepository.value.create(Context.api);
    thumbnailSize.width = width;
    thumbnailSize.height = height;

    await mediaThumbnailSizeRepository.value.save(thumbnailSize, Context.api);

    await getUnusedThumbnailSizes();
    void getThumbnailSizes();
};
function checkIfThumbnailExists({ width, height }) {
    const exists = thumbnailSizes.value.some((size) => {
        return size.width === width && size.height === height;
    });
    return exists;
}
const deleteThumbnail = async (thumbnailSize) => {
    if (await mediaFolderConfigurationThumbnailSizeRepository.value.get(thumbnailSize.id, Context.api)) {
        await mediaFolderConfigurationThumbnailSizeRepository.value.delete(thumbnailSize.id, Context.api);
    }

    configuration.value.mediaThumbnailSizes.remove(thumbnailSize.id);
    await mediaThumbnailSizeRepository.value.delete(thumbnailSize.id, Context.api);

    await getUnusedThumbnailSizes();
    void getThumbnailSizes();
};
const isThumbnailSizeActive = (size) => {
    if (!configuration.value.mediaThumbnailSizes) {
        return false;
    }

    return configuration.value.mediaThumbnailSizes.some((value) => {
        return value.id === size.id;
    });
};
const thumbnailSizeCheckboxName = (size) => {
    return `thumbnail-size-${size.width}-${size.height}-active`;
};
const onActiveTabChanged = (activeTabValue) => {
    activeTab.value = activeTabValue;
    if (activeTabValue === 'settings') {
        modalClass.value = 'ct-media-modal-folder-settings--shows-overflow';
        return;
    }
    modalClass.value = '';
};
const onChangeThumbnailSize = (value, size) => {
    if (value === true) {
        configuration.value.mediaThumbnailSizes.add(size);
        return;
    }

    configuration.value.mediaThumbnailSizes.remove(size.id);
};
const onChangeInheritance = (value) => {
    if (value === true) {
        originalConfiguration.value = configuration.value;
        configuration.value = parent.value.configuration;

        return;
    }

    if (originalConfiguration.value) {
        configuration.value = originalConfiguration.value;

        return;
    }

    const newConfiguration = mediaFolderConfigurationRepository.value.create();
    Object.keys(configuration.value).forEach((key) => {
        if (key === 'id') {
            return;
        }
        newConfiguration[key] = configuration.value[key];
    });
    configuration.value = newConfiguration;
};
const onClickSave = async () => {
    mediaFolder.value.configurationId = configuration.value.id;

    if (configuration.value.keepAspectRatio === null) {
        configuration.value.keepAspectRatio = false;
    }

    if (configuration.value.createThumbnails === null) {
        configuration.value.createThumbnails = false;
    }

    if (mediaFolder.value.defaultFolderId) {
        await ensureUniqueDefaultFolder(mediaFolder.value.id, mediaFolder.value.defaultFolderId);
    } else {
        mediaFolder.value.defaultFolderId = null;
    }

    try {
        await mediaFolderConfigurationRepository.value.save(configuration.value).then(() => {
            // Delete the original configuration if we inherit again
            if (originalConfiguration.value && configuration.value.id === parent.value.configuration.id) {
                mediaFolderConfigurationRepository.value.delete(originalConfiguration.value.id);
            }
        });

        if (mediaFolder.value && mediaFolder.value.getEntityName) {
            await mediaFolderRepository.value.save(mediaFolder.value, Context.api);
        }

        createNotificationSuccess({
            title: t('global.default.success'),
            message: t('global.ct-media-modal-folder-settings.notification.success.message'),
        });

        void nextTick(() => {
            emit('media-settings-modal-save', mediaFolder.value);
        });
    } catch (_e) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-media-modal-folder-settings.notification.error.message'),
        });
    }
};
async function ensureUniqueDefaultFolder(folderId, defaultFolderId) {
    const criteria = new Criteria(1, 25).addFilter(
        Criteria.multi('and', [
            Criteria.equals('defaultFolderId', defaultFolderId),
            Criteria.not('or', [Criteria.equals('id', folderId)]),
        ]),
    );
    const items = await mediaFolderRepository.value.search(criteria, Context.api);
    await Promise.all(
        items.map((folder) => {
            folder.defaultFolderId = null;
            return mediaFolderRepository.value.save(folder, Context.api);
        }),
    );
}
const onClickCancel = (originalDomEvent) => {
    mediaFolderRepository.value.discard(mediaFolder.value);

    closeModal(originalDomEvent);
};
function closeModal(originalDomEvent) {
    emit('media-settings-modal-close', {
        originalDomEvent,
    });
}
const onInputDefaultFolder = (defaultFolderId) => {
    mediaFolder.value.defaultFolderId = defaultFolderId;
};
function loadMediaFolder() {
    return mediaFolderRepository.value.get(props.mediaFolderId, Context.api);
}

void createdComponent();

ctDefinePublic({
    repositoryFactory,
    modalClass,
    activeTab,
    unusedThumbnailSizes,
    thumbnailSizes,
    parent,
    configuration,
    mediaFolderConfigurationThumbnailSizeRepository,
    originalConfiguration,
    mediaFolder,
    tabItems,
    mediaFolderRepository,
    mediaDefaultFolderRepository,
    mediaThumbnailSizeRepository,
    mediaFolderConfigurationRepository,
    unusedMediaThumbnailSizeCriteria,
    mediaThumbnailSizeCriteria,
    notEditable,
    thumbnailSizeFilter,
    mediaFolderNameError,
    createdComponent,
    getItemName,
    getUnusedThumbnailSizes,
    getThumbnailSizes,
    addThumbnail,
    checkIfThumbnailExists,
    deleteThumbnail,
    isThumbnailSizeActive,
    thumbnailSizeCheckboxName,
    onActiveTabChanged,
    onChangeThumbnailSize,
    onChangeInheritance,
    onClickSave,
    ensureUniqueDefaultFolder,
    onClickCancel,
    closeModal,
    onInputDefaultFolder,
    loadMediaFolder,
});

defineExpose({
    repositoryFactory,
    modalClass,
    activeTab,
    unusedThumbnailSizes,
    thumbnailSizes,
    parent,
    configuration,
    mediaFolderConfigurationThumbnailSizeRepository,
    originalConfiguration,
    mediaFolder,
    tabItems,
    mediaFolderRepository,
    mediaDefaultFolderRepository,
    mediaThumbnailSizeRepository,
    mediaFolderConfigurationRepository,
    unusedMediaThumbnailSizeCriteria,
    mediaThumbnailSizeCriteria,
    notEditable,
    thumbnailSizeFilter,
    mediaFolderNameError,
    createdComponent,
    getItemName,
    getUnusedThumbnailSizes,
    getThumbnailSizes,
    addThumbnail,
    checkIfThumbnailExists,
    deleteThumbnail,
    isThumbnailSizeActive,
    thumbnailSizeCheckboxName,
    onActiveTabChanged,
    onChangeThumbnailSize,
    onChangeInheritance,
    onClickSave,
    ensureUniqueDefaultFolder,
    onClickCancel,
    closeModal,
    onInputDefaultFolder,
    loadMediaFolder,
});
</script>
