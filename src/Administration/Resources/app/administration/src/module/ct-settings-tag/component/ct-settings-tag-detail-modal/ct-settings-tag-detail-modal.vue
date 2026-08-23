<template>
    <ct-block name="sw_settings_tag_detail_modal">
        <mt-modal-root :is-open="true" @change="$emit('close')">
            <mt-modal
                class="ct-settings-tag-detail-modal"
                :class="{ 'is--auto-height': initialTab === 'general' }"
                :title="title"
                width="full"
            >
                <ct-block name="sw_settings_tag_detail_modal_tabs">
                    <div class="ct-settings-tag-detail-modal__tabs">
                        <mt-tabs
                            position-identifier="ct-settings-tag-detail-modal"
                            :items="tagTabItems"
                            :default-item="initialTab"
                            :small="true"
                            @new-item-active="onTabChange"
                        />

                        <div class="ct-settings-tag-detail-modal__tabs-content">
                            <template v-if="initialTab === 'general'">
                                <ct-block name="sw_settings_tag_detail_modal_tabs_general_tab">
                                    <p class="ct-settings-tag-detail-modal__tag-name">
                                        <mt-text-field
                                            id="ct-field--tag-name"
                                            v-model="tag.name"
                                            name="ct-field--tag-name"
                                            :label="translate('ct-settings-tag.list.columnName')"
                                            :placeholder="translate('ct-settings-tag.list.placeholderTagName')"
                                            :error="tagNameError"
                                            maxlength="255"
                                            required
                                        />
                                    </p>
                                </ct-block>
                            </template>

                            <template v-if="initialTab === 'assignments'">
                                <ct-block name="sw_settings_tag_detail_modal_tabs_assignments_tab">
                                    <ct-settings-tag-detail-assignments
                                        :tag="tag"
                                        :initial-counts="computedCounts"
                                        :to-be-added="assignmentsToBeAdded"
                                        :to-be-deleted="assignmentsToBeDeleted"
                                        :property="property"
                                        :entity="entity"
                                        @add-assignment="addAssignment"
                                        @remove-assignment="removeAssignment"
                                    />
                                </ct-block>
                            </template>
                        </div>
                    </div>
                </ct-block>

                <template #footer>
                    <div class="ct-settings-tag-detail-modal__footer">
                        <ct-block name="sw_settings_tag_detail_modal_footer">
                            <ct-block name="sw_settings_tag_detail_modal_cancel">
                                <mt-modal-close as="mt-button" size="small" variant="secondary">
                                    {{ translate('global.default.cancel') }}
                                </mt-modal-close>
                            </ct-block>

                            <ct-block name="sw_settings_tag_detail_modal_confirm">
                                <mt-modal-action
                                    as="mt-button"
                                    variant="primary"
                                    size="small"
                                    :disabled="!allowSave"
                                    :is-loading="isLoading"
                                    @click="onSave"
                                >
                                    {{ translate('global.default.save') }}
                                </mt-modal-action>
                            </ct-block>
                        </ct-block>
                    </div>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup>
import './ct-settings-tag-detail-modal.scss';

const props = defineProps({
    editedTag: {
        type: Object,
        required: false,
        default: null,
    },
    counts: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },
    property: {
        type: String,
        required: false,
        default: null,
    },
    entity: {
        type: String,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'close',
    'finish',
]);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const translate = t;
const repositoryFactory = inject('repositoryFactory');
const syncService = inject('syncService');
const acl = inject('acl');

const tag = ref(null);
const isLoading = ref(false);
const assignmentsToBeAdded = ref({});
const assignmentsToBeDeleted = ref({});
const initialTab = ref(props.property && props.entity ? 'assignments' : 'general');

const tagTabItems = computed(() => {
    return [
        {
            label: t('ct-settings-tag.detail.generalTab'),
            name: 'general',
        },
        {
            label: t('ct-settings-tag.detail.assignmentsTab'),
            name: 'assignments',
        },
    ];
});
const tagRepository = computed(() => {
    return repositoryFactory.create('tag');
});
const tagDefinition = computed(() => {
    return Contena.EntityDefinition.get('tag');
});
const tagNameError = computed(() => {
    const entity = tag.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const title = computed(() => {
    return tag.value.isNew()
        ? t('ct-settings-tag.list.buttonAddTag')
        : t(
              'ct-settings-tag.detail.editTitle',
              {
                  name: tag.value.name,
              },
              0,
          );
});
const allowSave = computed(() => {
    return tag.value.isNew() ? acl.can('tag.creator') : acl.can('tag.editor');
});
const computedCounts = computed(() => {
    const counts = { ...props.counts };

    Object.keys(assignmentsToBeDeleted.value).forEach((propertyName) => {
        if (!counts.hasOwnProperty(propertyName)) {
            return;
        }

        counts[propertyName] -= Object.keys(assignmentsToBeDeleted.value[propertyName]).length;
    });

    Object.keys(assignmentsToBeAdded.value).forEach((propertyName) => {
        if (!counts.hasOwnProperty(propertyName)) {
            counts[propertyName] = Object.keys(assignmentsToBeAdded.value[propertyName]).length;

            return;
        }

        counts[propertyName] += Object.keys(assignmentsToBeAdded.value[propertyName]).length;
    });

    return counts;
});

const onTabChange = (tab) => {
    initialTab.value = tab;
};
const createdComponent = () => {
    if (props.editedTag) {
        tag.value = Object.assign(tagRepository.value.create(), props.editedTag);
        tag.value._isNew = false;
    } else {
        tag.value = tagRepository.value.create();
    }

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation === 'many_to_many') {
                assignmentsToBeAdded.value[propertyName] = {};
                assignmentsToBeDeleted.value[propertyName] = {};
            }
        },
    );
};
const onSave = async (done) => {
    isLoading.value = true;
    const deletePayload = [];

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation !== 'many_to_many') {
                return;
            }

            const toBeAdded = Object.keys(assignmentsToBeAdded.value[propertyName]);

            if (toBeAdded.length !== 0) {
                toBeAdded.forEach((id) => {
                    tag.value[propertyName].add(assignmentsToBeAdded.value[propertyName][id]);
                });
            }

            const toBeDeleted = Object.keys(assignmentsToBeDeleted.value[propertyName]);

            if (toBeDeleted.length === 0) {
                return;
            }

            const ids = toBeDeleted.map((id) => {
                return {
                    [property.reference]: id,
                    [property.local]: tag.value.id,
                };
            });

            deletePayload.push({
                action: 'delete',
                entity: property.mapping,
                payload: ids,
            });
        },
    );

    if (deletePayload.length) {
        await syncService.sync(deletePayload, {}, { 'single-operation': 1 });
    }

    return tagRepository.value
        .save(tag.value)
        .then(() => {
            if (done) {
                done();
            }

            emit('finish');
        })
        .catch(() => {
            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
            isLoading.value = false;
        });
};
const onCancel = () => {
    emit('close');
};
const addAssignment = (assignment, id, item) => {
    if (assignmentsToBeDeleted.value[assignment].hasOwnProperty(id)) {
        delete assignmentsToBeDeleted.value[assignment][id];

        return;
    }

    assignmentsToBeAdded.value[assignment][id] = item;
};
const removeAssignment = (assignment, id, item) => {
    if (assignmentsToBeAdded.value[assignment].hasOwnProperty(id)) {
        delete assignmentsToBeAdded.value[assignment][id];

        return;
    }

    assignmentsToBeDeleted.value[assignment][id] = item;
};

createdComponent();

swDefinePublic({
    repositoryFactory,
    syncService,
    acl,
    tag,
    isLoading,
    assignmentsToBeAdded,
    assignmentsToBeDeleted,
    initialTab,
    tagTabItems,
    tagRepository,
    tagDefinition,
    tagNameError,
    title,
    allowSave,
    computedCounts,
    onTabChange,
    createdComponent,
    onSave,
    onCancel,
    addAssignment,
    removeAssignment,
});

defineExpose({
    repositoryFactory,
    syncService,
    acl,
    tag,
    isLoading,
    assignmentsToBeAdded,
    assignmentsToBeDeleted,
    initialTab,
    tagTabItems,
    tagRepository,
    tagDefinition,
    tagNameError,
    title,
    allowSave,
    computedCounts,
    onTabChange,
    createdComponent,
    onSave,
    onCancel,
    addAssignment,
    removeAssignment,
});
</script>
