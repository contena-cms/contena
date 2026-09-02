<template>
    <ct-block name="ct_media_field">
        <div ref="mediaField" class="ct-media-field">
            <ct-block name="ct_media_field_label">
                <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                <label v-if="showLabel" class="ct-media-field__label">
                    <slot name="label">
                        {{ label }}
                    </slot>
                </label>
            </ct-block>

            <ct-block name="ct_media_field_input">
                <div class="ct-media-field__input-container" :class="mediaFieldClasses">
                    <ct-block name="ct_media_field_preview">
                        <ct-media-media-item
                            v-if="mediaEntity"
                            class="ct-media-field__media-list-item ct-media-field__input"
                            :item="mediaEntity"
                            :is-list="true"
                            :show-context-menu-button="false"
                            :editable="false"
                        />
                        <div v-else class="ct-media-field__empty-preview ct-media-field__input"></div>
                    </ct-block>

                    <ct-block name="ct_media_field_toggle_button">
                        <mt-button
                            ref="mediaToggleButton"
                            class="ct-media-field__toggle-button"
                            square
                            :disabled="disabled"
                            variant="secondary"
                            @click="onTogglePicker"
                        >
                            <ct-block name="ct_media_field_media_icon">
                                <mt-icon name="regular-image" />
                            </ct-block>

                            <ct-block name="ct_media_field_media_popover">
                                <mt-floating-ui
                                    v-if="showPicker"
                                    class="ct-media-field__popover ct-media-field__expanded-content"
                                    :is-opened="true"
                                    :anchor-element="popoverAnchorElement"
                                    :floating-ui-options="{ placement: 'bottom-end' }"
                                    :offset="6"
                                    detached
                                    show-arrow
                                >
                                    <div @click.stop>
                                        <ct-block name="ct_media_field_action_bar">
                                            <div class="ct-media-field__actions_bar">
                                                <ct-block name="ct_media_field_action_bar_button_toggle">
                                                    <mt-button
                                                        class="ct-media-field__action-button"
                                                        variant="secondary"
                                                        @click="toggleUploadField"
                                                    >
                                                        <mt-icon
                                                            class="ct-media-field__icon-add"
                                                            name="regular-plus"
                                                            size="16px"
                                                        />
                                                        {{ toggleButtonLabel }}
                                                    </mt-button>
                                                </ct-block>

                                                <ct-block name="ct_media_field_action_bar_button_unlink">
                                                    <mt-button
                                                        v-if="mediaId"
                                                        class="ct-media-field__action-button is--remove"
                                                        variant="secondary"
                                                        @click="removeLink"
                                                    >
                                                        <mt-icon
                                                            class="ct-media-field__icon-remove"
                                                            name="regular-times-circle-s"
                                                            size="16px"
                                                        />
                                                        {{ $t('global.ct-media-field.labelUnlink') }}
                                                    </mt-button>
                                                </ct-block>
                                            </div>
                                        </ct-block>

                                        <ct-block name="ct_media_field_upload_component">
                                            <ct-upload-listener
                                                :upload-tag="uploadTag"
                                                auto-upload
                                                @media-upload-finish="exposeNewId"
                                            />
                                            <ct-media-upload-v2
                                                v-if="showUploadField"
                                                variant="regular"
                                                :file-accept="fileAccept"
                                                :default-folder="defaultFolder"
                                                :allow-multi-select="false"
                                                :upload-tag="uploadTag"
                                                :disabled="disabled"
                                            />
                                        </ct-block>

                                        <template v-if="showUploadField"
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <div v-else class="ct-media-field__media-selection">
                                            <ct-block name="ct_media_field_search_field">
                                                <ct-simple-search-field
                                                    v-model:value="searchTerm"
                                                    @search-term-change="onSearchTermChange"
                                                />
                                            </ct-block>

                                            <ct-block name="ct_media_field_media_list">
                                                <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                                                <mt-loader
                                                    v-if="isLoadingSuggestions"
                                                    class="ct-media-field__picker-loader"
                                                />

                                                <ul v-else class="ct-media-field__suggestion-list">
                                                    <li
                                                        v-for="suggestion in suggestedItems"
                                                        :key="suggestion.id"
                                                        class="ct-media-field__suggestion-list-entry"
                                                    >
                                                        <ct-block name="ct_media_field_suggestion_preview">
                                                            <ct-media-media-item
                                                                class="ct-media-field__media-list-item"
                                                                :item="suggestion"
                                                                :is-list="true"
                                                                :show-context-menu-button="false"
                                                                @media-item-click="mediaItemChanged(suggestion.id)"
                                                            />
                                                        </ct-block>
                                                    </li>
                                                </ul>
                                            </ct-block>

                                            <ct-pagination
                                                v-bind="{ page, limit, total }"
                                                :total-visible="4"
                                                :steps="[5]"
                                                @page-change="onPageChange"
                                            />
                                        </div>
                                    </div>
                                </mt-floating-ui>
                            </ct-block>
                        </mt-button>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-field.scss';
const { Context, Utils } = Contena;
const { Criteria } = Contena.Data;

const props = defineProps({
    // need to be "value" instead of "modelValue" because of the compat build
    value: {
        type: [
            String,
            null,
        ],
        required: false,
        default: null,
    },

    disabled: {
        type: Boolean,
        default: false,
        required: false,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    defaultFolder: {
        type: String,
        required: false,
        validator(value) {
            return value.length > 0;
        },
        default: null,
    },

    fileAccept: {
        type: String,
        required: false,
        default: '*/*',
    },
});
const emit = defineEmits(['update:value']);

import { ref, computed, inject, watch, useSlots } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const slots = useSlots();

const mediaField = ref(null);
const mediaToggleButton = ref(null);

const repositoryFactory = inject('repositoryFactory');
const searchTerm = ref('');
const mediaEntity = ref(null);
const showPicker = ref(false);
const showUploadField = ref(false);
const suggestedItems = ref([]);
const isLoadingSuggestions = ref(false);
const pickerClasses = ref({});
const uploadTag = ref(Utils.createId());
const page = ref(1);
const limit = ref(5);
const total = ref(0);

const mediaId = computed({
    get: () => {
        return props.value;
    },
    set: (newValue) => {
        emit('update:value', newValue);
    },
});
const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const popoverAnchorElement = computed(() => {
    return mediaToggleButton.value?.$el ?? null;
});
const mediaFieldClasses = computed(() => {
    return {
        'is--active': showPicker.value,
    };
});
const toggleButtonLabel = computed(() => {
    return showUploadField.value
        ? t('global.ct-media-field.labelToggleSearchExisting')
        : t('global.ct-media-field.labelToggleUploadNew');
});
const showLabel = computed(() => {
    return Boolean(props.label || slots.label);
});
const suggestionCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    criteria.addFilter(Criteria.not('AND', [Criteria.equals('uploadedAt', null)]));

    if (searchTerm.value) {
        criteria.addFilter(
            Criteria.multi('OR', [
                Criteria.contains('fileName', searchTerm.value),
                Criteria.contains('fileExtension', searchTerm.value),
            ]),
        );
    }

    if (props.defaultFolder) {
        criteria.addFilter(Criteria.equals('mediaFolder.defaultFolder.entity', props.defaultFolder));
    }

    return criteria;
});

const createdComponent = () => {
    void fetchItem();
};
const onSearchTermChange = (searchTermValue) => {
    searchTerm.value = searchTermValue;
    page.value = 1;
    void fetchSuggestions();
};
async function fetchItem(id = mediaId.value) {
    if (!id) {
        mediaEntity.value = null;
        return;
    }
    mediaEntity.value = await mediaRepository.value.get(id, Context.api);
}
async function fetchSuggestions() {
    isLoadingSuggestions.value = true;
    try {
        suggestedItems.value = await mediaRepository.value.search(suggestionCriteria.value, Context.api);
        total.value = suggestedItems.value?.total ?? 0;
    } catch (e) {
        throw new Error(e);
    } finally {
        isLoadingSuggestions.value = false;
    }
}
const onTogglePicker = () => {
    page.value = 1;
    limit.value = 5;
    total.value = 0;
    showPicker.value = !showPicker.value;

    if (showPicker.value) {
        showUploadField.value = false;
        computePickerPositionAndStyle();
        void fetchSuggestions();
    }
};
const mediaItemChanged = (newMediaId) => {
    emit('update:value', newMediaId);
    onTogglePicker();
};
const removeLink = () => {
    emit('update:value', null);
};
function computePickerPositionAndStyle() {
    if (!mediaField.value) {
        pickerClasses.value = {};
        return;
    }
    const clientRect = mediaField.value.getBoundingClientRect();
    pickerClasses.value = {
        top: `${clientRect.height + 5}px`,
    };
}
const toggleUploadField = () => {
    showUploadField.value = !showUploadField.value;
};
const exposeNewId = ({ targetId }) => {
    emit('update:value', targetId);
    showUploadField.value = false;
    showPicker.value = false;
};
const onPageChange = ({ page: pageValue, limit: limitValue }) => {
    page.value = pageValue;
    limit.value = limitValue;
    void fetchSuggestions();
};

watch(
    () => mediaId.value,
    (newValue) => {
        void fetchItem(newValue);
        emit('update:value', newValue);
    },
);

createdComponent();

ctDefinePublic({
    repositoryFactory,
    searchTerm,
    mediaEntity,
    showPicker,
    showUploadField,
    suggestedItems,
    isLoadingSuggestions,
    pickerClasses,
    uploadTag,
    page,
    limit,
    total,
    mediaId,
    mediaRepository,
    popoverAnchorElement,
    mediaFieldClasses,
    toggleButtonLabel,
    showLabel,
    suggestionCriteria,
    createdComponent,
    onSearchTermChange,
    fetchItem,
    fetchSuggestions,
    onTogglePicker,
    mediaItemChanged,
    removeLink,
    computePickerPositionAndStyle,
    toggleUploadField,
    exposeNewId,
    onPageChange,
});

defineExpose({
    repositoryFactory,
    searchTerm,
    mediaEntity,
    showPicker,
    showUploadField,
    suggestedItems,
    isLoadingSuggestions,
    pickerClasses,
    uploadTag,
    page,
    limit,
    total,
    mediaId,
    mediaRepository,
    popoverAnchorElement,
    mediaFieldClasses,
    toggleButtonLabel,
    showLabel,
    suggestionCriteria,
    createdComponent,
    onSearchTermChange,
    fetchItem,
    fetchSuggestions,
    onTogglePicker,
    mediaItemChanged,
    removeLink,
    computePickerPositionAndStyle,
    toggleUploadField,
    exposeNewId,
    onPageChange,
});
</script>
