<template>
    <ct-block name="ct_settings_tag_detail_assignments">
        <div class="ct-settings-tag-detail-assignments">
            <ct-block name="ct_settings_tag_detail_assignments_card">
                <mt-card
                    class="ct-settings-tag-detail-assignments__card"
                    position-identifier="ct-settings-tag-detail-assignments-card"
                    large
                >
                    <template #toolbar>
                        <ct-block name="ct_settings_tag_detail_assignments_toolbar">
                            <ct-card-filter
                                :placeholder="$t('ct-settings-tag.detail.assignments.searchPlaceholder')"
                                @ct-card-filter-term-change="onTermChange"
                            />
                        </ct-block>
                    </template>

                    <ct-block name="ct_settings_tag_detail_assignments_header">
                        <ct-container columns="1fr 1fr">
                            <ct-block name="ct_settings_tag_detail_assignments_header_selected_filter">
                                <ct-card-section
                                    class="ct-settings-tag-detail-assignments__filter-selected"
                                    divider="bottom"
                                >
                                    <mt-switch
                                        v-model="showSelected"
                                        :disabled="isLoading"
                                        :label="$t('ct-settings-tag.detail.assignments.showSelected')"
                                    />
                                </ct-card-section>
                            </ct-block>
                            <ct-block name="ct_settings_tag_detail_assignments_header_total_selected">
                                <ct-card-section class="ct-settings-tag-detail-assignments__total-selected" divider="bottom">
                                    {{ totalAssignments }} {{ $t('ct-settings-tag.detail.assignments.selected') }}
                                </ct-card-section>
                            </ct-block>
                        </ct-container>
                    </ct-block>

                    <template #grid>
                        <ct-block name="ct_settings_tag_detail_assignments_grid">
                            <ct-container columns="300px 1fr">
                                <ct-card-section divider="right">
                                    <ct-block name="ct_settings_tag_detail_assignments_associations_grid">
                                        <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                                        <ct-data-grid
                                            class="ct-settings-tag-detail-assignments__associations-grid"
                                            :data-source="assignmentAssociations"
                                            :columns="assignmentAssociationsColumns"
                                            :v-bind="$attrs"
                                            :show-selection="false"
                                            :show-actions="false"
                                            :show-header="false"
                                            :plain-appearance="true"
                                            :full-page="true"
                                            item-identifier-property="entity"
                                        >
                                            <template #column-name="{ item }">
                                                <ct-block
                                                    name="ct_settings_tag_detail_assignments_associations_grid_column_name"
                                                >
                                                    <mt-button
                                                        class="associations-grid__row"
                                                        :class="{ 'is--selected': item.entity === selectedEntity }"
                                                        :disabled="isLoading"
                                                        variant="secondary"
                                                        @click.prevent="onAssignmentChange(item)"
                                                    >
                                                        <mt-icon
                                                            v-if="item.entity === selectedEntity"
                                                            name="regular-folder-open"
                                                            size="16px"
                                                        />
                                                        <mt-icon v-else name="regular-folder" size="16px" />
                                                        <span>
                                                            {{ item.name }}
                                                        </span>
                                                        <span
                                                            v-if="getCount(item.assignment)"
                                                            class="associations-grid__count"
                                                        >
                                                            {{ getCount(item.assignment) }}
                                                            {{
                                                                $t(
                                                                    'ct-settings-tag.detail.assignments.assignments',
                                                                    {},
                                                                    getCount(item.assignment),
                                                                )
                                                            }}
                                                        </span>
                                                    </mt-button>
                                                </ct-block>
                                            </template>

                                            <template #actions> </template>
                                        </ct-data-grid>
                                    </ct-block>
                                </ct-card-section>
                                <ct-card-section>
                                    <ct-block name="ct_settings_tag_detail_assignments_entities_grid">
                                        <ct-entity-listing
                                            :key="entitiesGridKey"
                                            class="ct-settings-tag-detail-assignments__entities-grid"
                                            :data-source="entities"
                                            :columns="entitiesColumns"
                                            :repository="entityRepository"
                                            :plain-appearance="true"
                                            :compact-mode="true"
                                            :show-selection="true"
                                            :show-actions="false"
                                            :show-header="true"
                                            :is-loading="isLoading"
                                            :disable-data-fetching="true"
                                            :pre-selection="selectedAssignments"
                                            :allow-inline-edit="false"
                                            :allow-delete="false"
                                            @page-change="onPageChange"
                                            @select-item="onSelectionChange"
                                        >
                                            <template
                                                #selection-content="{ item, isSelected, selectItem, itemIdentifierProperty }"
                                            >
                                                <ct-block
                                                    name="ct_settings_tag_detail_assignments_entities_grid_selection_content"
                                                >
                                                    <div class="ct-data-grid__cell-content">
                                                        <ct-block
                                                            name="ct_settings_tag_detail_assignments_entities_grid_select_item_checkbox"
                                                        >
                                                            <mt-checkbox
                                                                v-if="isInherited(item.id, item.parentId)"
                                                                :key="`${itemIdentifierProperty}-inherited`"
                                                                :checked="hasInheritedTag(item.id, item.parentId)"
                                                                :disabled="true"
                                                            />
                                                            <mt-checkbox
                                                                v-else
                                                                :key="itemIdentifierProperty"
                                                                :checked="isSelected(item[itemIdentifierProperty])"
                                                                @update:checked="selectItem($event, item)"
                                                            />
                                                        </ct-block>
                                                    </div>
                                                </ct-block>
                                            </template>

                                            <template #column-name="{ item, selectItem }">
                                                <ct-block
                                                    name="ct_settings_tag_detail_assignments_entities_grid_column_inheritance_switch"
                                                >
                                                    <ct-inheritance-switch
                                                        v-if="
                                                            isInheritable &&
                                                            item.parentId &&
                                                            parentHasTags(item.id, item.parentId)
                                                        "
                                                        :is-inherited="isInherited(item.id, item.parentId)"
                                                        :disabled="!isInherited(item.id, item.parentId)"
                                                        @inheritance-remove="selectItem(true, item)"
                                                    />
                                                </ct-block>
                                                <ct-block
                                                    name="ct_settings_tag_detail_assignments_entities_grid_column_name_media"
                                                >
                                                    <template v-if="selectedEntity === 'media'">
                                                        <ct-media-preview-v2
                                                            :source="item.id"
                                                            :media-is-private="item.private"
                                                        />
                                                        <ct-highlight-text
                                                            :search-term="term"
                                                            :text="`${item.fileName}.${item.fileExtension}`"
                                                        />
                                                    </template>
                                                </ct-block>
                                                <ct-block
                                                    name="ct_settings_tag_detail_assignments_entities_grid_column_name_default"
                                                >
                                                    <template v-if="selectedEntity === 'media'"
                                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                    >
                                                    <ct-highlight-text
                                                        v-else
                                                        :search-term="term"
                                                        :text="item.translated ? item.translated.name : item.name"
                                                    />
                                                </ct-block>
                                            </template>
                                        </ct-entity-listing>
                                    </ct-block>
                                </ct-card-section>
                            </ct-container>
                        </ct-block>
                    </template>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import utils from 'src/core/service/util.service';
import './ct-settings-tag-detail-assignments.scss';
const { Context } = Contena;
const { Criteria } = Contena.Data;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    tag: {
        type: Object,
        required: true,
    },
    toBeAdded: {
        type: Object,
        required: true,
    },
    toBeDeleted: {
        type: Object,
        required: true,
    },
    initialCounts: {
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
    'remove-assignment',
    'add-assignment',
]);

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';

const { t } = useI18n();
const { total: total2, term: term2, initializeListing } = useListing();
const term = term2;

const repositoryFactory = inject('repositoryFactory');

const selectedEntity = ref(props.entity ?? 'media');
const selectedAssignment = ref(props.property ?? 'media');
const entitiesGridKey = ref(null);
const preSelected = ref({});
const entities = ref(null);
const isLoading = ref(false);
const showSelected = ref(props.property && props.entity);
const counts = ref({ ...props.initialCounts });
const currentPageCountBuckets = ref([]);
const disableRouteParams = ref(true);
const page = ref(1);
const limit = ref(25);

const tagDefinition = computed(() => {
    return Contena.EntityDefinition.get('tag');
});
const isInheritable = computed(() => {
    return Contena.EntityDefinition.get(selectedEntity.value)?.properties?.tags?.flags?.inherited === true;
});
const assignmentAssociations = computed(() => {
    const assignmentAssociations = [];

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation === 'many_to_many') {
                assignmentAssociations.push({
                    name: t(`ct-settings-tag.detail.assignments.${propertyName}`),
                    entity: property.entity,
                    assignment: propertyName,
                });
            }
        },
    );

    return assignmentAssociations;
});
const assignmentAssociationsColumns = computed(() => {
    return [
        {
            property: 'name',
            dataIndex: 'name',
            primary: true,
            allowResize: false,
            sortable: false,
        },
    ];
});
const entityRepository = computed(() => {
    return repositoryFactory.create(selectedEntity.value);
});
const entityCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);
    criteria.setTerm(term2.value);
    // Criteria is a local mutable query object, not component state.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

    if (isInheritable.value) {
        addTagAggregations(criteria);
    }

    if (!showSelected.value) {
        return criteria;
    }

    const toBeAdded = Object.keys(props.toBeAdded[selectedAssignment.value]);
    const toBeDeleted = Object.keys(props.toBeDeleted[selectedAssignment.value]).filter((id) => {
        const parentId = props.toBeDeleted[selectedAssignment.value][id].parentId;

        if (!isInheritable.value || !parentId) {
            return true;
        }

        return !isInherited(id, parentId) || !hasInheritedTag(id, parentId);
    });

    if (toBeAdded.length) {
        criteria.addFilter(
            Criteria.multi('OR', [
                Criteria.equals('tags.id', props.tag.id),
                Criteria.equalsAny('id', toBeAdded),
            ]),
        );
    } else {
        criteria.addFilter(Criteria.equals('tags.id', props.tag.id));
    }

    if (!toBeDeleted.length) {
        return criteria;
    }

    criteria.addFilter(
        Criteria.not('AND', [
            Criteria.equalsAny('id', toBeDeleted),
        ]),
    );

    return criteria;
});
const entitiesColumns = computed(() => {
    return [
        {
            property: 'name',
            primary: true,
            allowResize: false,
            sortable: false,
        },
    ];
});
const selectedAssignments = computed(() => {
    const selection = new Proxy(
        { ...preSelected.value },
        {
            get(target, key) {
                return target[key];
            },
            set(target, key, value) {
                target[key] = value;
                return true;
            },
        },
    );

    if (props.toBeAdded?.[selectedAssignment.value]) {
        Object.values(props.toBeAdded[selectedAssignment.value]).forEach((toBeAdded) => {
            selection[toBeAdded.id] = toBeAdded;
        });
    }

    if (props.toBeDeleted?.[selectedAssignment.value]) {
        Object.values(props.toBeDeleted[selectedAssignment.value]).forEach((toBeDeleted) => {
            if (selection.hasOwnProperty(toBeDeleted.id)) {
                delete selection[toBeDeleted.id];
            }
        });
    }

    return selection;
});
const totalAssignments = computed(() => {
    let total = 0;

    Object.values(counts.value).forEach((count) => {
        total += count;
    });

    return total;
});

const getList = () => {
    isLoading.value = true;
    const criteria = entityCriteria.value;

    if (showSelected.value && isInheritable.value) {
        return searchInheritedEntities(criteria)
            .then(() => {
                return search(criteria);
            })
            .catch(() => {
                isLoading.value = false;
            });
    }

    return search(criteria);
};
const search = (criteria) => {
    return entityRepository.value
        .search(criteria, {
            ...Context.api,
            inheritance: true,
        })
        .then((items) => {
            if (props.tag.isNew() || items.total === 0) {
                entitiesGridKey.value = utils.createId();
                total2.value = items.total;
                entities.value = items;
                isLoading.value = false;

                return null;
            }

            const entityIds = items.map(({ id }) => {
                return id;
            });
            const relationCriteria = new Criteria(1, limit.value);
            relationCriteria.addFilter(Criteria.equalsAny('id', entityIds));
            if (isInheritable.value) {
                addTagAggregations(relationCriteria, false);
            }
            relationCriteria.addPostFilter(Criteria.equals('tags.id', props.tag.id));

            return entityRepository.value.search(relationCriteria).then((selected) => {
                if (isInheritable.value) {
                    currentPageCountBuckets.value = selected.aggregations.tags.buckets;
                }

                const preSelectedValue = {};
                selected.forEach((item) => {
                    preSelectedValue[item.id] = item;
                });
                preSelected.value = preSelectedValue;
                entitiesGridKey.value = utils.createId();

                total2.value = items.total;
                entities.value = items;
                isLoading.value = false;
            });
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const addTagAggregations = (criteria, filter = true) => {
    let aggregation = Criteria.count('tags', `${selectedEntity.value}.tags.id`);

    if (filter) {
        aggregation = Criteria.filter('tags', [Criteria.equals('tags.id', props.tag.id)], aggregation);

        criteria.addAggregation(
            Criteria.terms(
                'parentTags',
                'id',
                null,
                null,
                Criteria.count('parentTags', `${selectedEntity.value}.parent.tags.id`),
            ),
        );
    }

    criteria.addAggregation(Criteria.terms('tags', 'id', null, null, aggregation));
};
const searchInheritedEntities = (criteria) => {
    const toBeAdded = Object.keys(props.toBeAdded[selectedAssignment.value]);
    const toBeDeleted = Object.keys(props.toBeDeleted[selectedAssignment.value]);

    if (!toBeAdded.length && !toBeDeleted.length) {
        return Promise.resolve();
    }

    let addedPromise = Promise.resolve();
    let deletedPromise = Promise.resolve();

    if (toBeAdded.length) {
        const inheritedAddedCriteria = new Criteria(1, 25);
        inheritedAddedCriteria.addFilter(
            Criteria.multi('AND', [
                Criteria.equals('tags.id', null),
                Criteria.equalsAny('parentId', toBeAdded),
            ]),
        );

        addedPromise = entityRepository.value.searchIds(inheritedAddedCriteria).then(({ data, total }) => {
            if (total === 0) {
                return;
            }

            criteria.filters = [
                Criteria.multi('OR', [
                    Criteria.multi('AND', criteria.filters),
                    Criteria.equalsAny('id', data),
                ]),
            ];
        });
    }

    if (toBeDeleted.length) {
        const inheritedDeletedCriteria = new Criteria(1, 25);
        inheritedDeletedCriteria.addFilter(Criteria.equals('tags.id', null));
        inheritedDeletedCriteria.addFilter(Criteria.equalsAny('parentId', toBeDeleted));
        if (toBeAdded.length) {
            inheritedDeletedCriteria.addFilter(
                Criteria.not('AND', [
                    Criteria.equalsAny('id', toBeAdded),
                ]),
            );
        }

        deletedPromise = entityRepository.value.searchIds(inheritedDeletedCriteria).then(({ data, total }) => {
            if (total === 0) {
                return;
            }

            criteria.addFilter(
                Criteria.not('AND', [
                    Criteria.equalsAny('id', data),
                ]),
            );
        });
    }

    return Promise.all([
        addedPromise,
        deletedPromise,
    ]);
};
const onTermChange = async (termValue) => {
    term2.value = termValue;
    page.value = 1;
    await getList();
};
const onAssignmentChange = ({ entity, assignment }) => {
    selectedEntity.value = entity;
    selectedAssignment.value = assignment;
};
const onSelectionChange = (selection, item, selected) => {
    const id = item.id;

    if (!selected) {
        emit('remove-assignment', selectedAssignment.value, id, item);
        countDecrease(selectedAssignment.value);

        return;
    }

    emit('add-assignment', selectedAssignment.value, id, item);
    countIncrease(selectedAssignment.value);
};
const getCount = (propertyName) => {
    if (counts.value.hasOwnProperty(propertyName)) {
        return counts.value[propertyName];
    }

    return null;
};
const countIncrease = (propertyName) => {
    if (counts.value.hasOwnProperty(propertyName)) {
        counts.value[propertyName] += 1;
    } else counts.value[propertyName] = 1;
};
const countDecrease = (propertyName) => {
    if (counts.value.hasOwnProperty(propertyName) && counts.value[propertyName] !== 0) {
        counts.value[propertyName] -= 1;
    } else counts.value[propertyName] = 0;

    if (!showSelected.value) {
        return;
    }

    if (page.value > 1 && entities.value.length === 1) {
        page.value -= 1;
    }

    getList();
};
const isInherited = (id, parentId) => {
    if (!isInheritable.value || !parentId || props.toBeAdded[selectedAssignment.value].hasOwnProperty(id)) {
        return false;
    }

    const selfToBeDeleted = props.toBeDeleted[selectedAssignment.value].hasOwnProperty(id);
    const hasOwnTags =
        currentPageCountBuckets.value.filter(({ key, tags }) => {
            return key === id && (selfToBeDeleted ? tags.count - 1 : tags.count) > 0;
        }).length > 0;

    if (hasOwnTags) {
        return false;
    }

    return parentHasTags(id, parentId);
};
const parentHasTags = (id, parentId) => {
    const parentToBeDeleted = props.toBeDeleted[selectedAssignment.value].hasOwnProperty(parentId);
    const parentHasTags =
        entities.value.aggregations.parentTags.buckets.filter(({ key, parentTags }) => {
            return key === id && (parentToBeDeleted ? parentTags.count - 1 : parentTags.count) > 0;
        }).length > 0;

    if (!parentHasTags) {
        return props.toBeAdded[selectedAssignment.value].hasOwnProperty(parentId);
    }

    return true;
};
const hasInheritedTag = (id, parentId) => {
    const parentToBeAdded = props.toBeAdded[selectedAssignment.value].hasOwnProperty(parentId);
    const parentToBeDeleted = props.toBeDeleted[selectedAssignment.value].hasOwnProperty(parentId);

    if (preSelected.value.hasOwnProperty(id) || props.toBeDeleted[selectedAssignment.value].hasOwnProperty(id)) {
        return parentToBeAdded || (preSelected.value.hasOwnProperty(parentId) && !parentToBeDeleted);
    }

    const hasInheritedTag =
        entities.value.aggregations.tags.buckets.filter((bucket) => {
            return bucket.key === id;
        }).length > 0;

    return (hasInheritedTag || parentToBeAdded) && !parentToBeDeleted;
};
const onPageChange = ({ page: pageValue, limit: limitValue }) => {
    page.value = pageValue;
    limit.value = limitValue;
    getList();
};

initializeListing({
    getList,
    page,
    limit,
    disableRouteParams,
});

watch(
    () => selectedEntity.value,
    () => {
        page.value = 1;
        getList();
    },
);
watch(
    () => showSelected.value,
    () => {
        page.value = 1;
        getList();
    },
);

ctDefinePublic({
    repositoryFactory,
    selectedEntity,
    selectedAssignment,
    entitiesGridKey,
    preSelected,
    entities,
    isLoading,
    showSelected,
    counts,
    currentPageCountBuckets,
    disableRouteParams,
    page,
    limit,
    tagDefinition,
    isInheritable,
    assignmentAssociations,
    assignmentAssociationsColumns,
    entityRepository,
    entityCriteria,
    entitiesColumns,
    selectedAssignments,
    totalAssignments,
    getList,
    search,
    addTagAggregations,
    searchInheritedEntities,
    onTermChange,
    onAssignmentChange,
    onSelectionChange,
    getCount,
    countIncrease,
    countDecrease,
    isInherited,
    parentHasTags,
    hasInheritedTag,
    onPageChange,
});

defineExpose({
    repositoryFactory,
    selectedEntity,
    selectedAssignment,
    entitiesGridKey,
    preSelected,
    entities,
    isLoading,
    showSelected,
    counts,
    currentPageCountBuckets,
    disableRouteParams,
    page,
    limit,
    tagDefinition,
    isInheritable,
    assignmentAssociations,
    assignmentAssociationsColumns,
    entityRepository,
    entityCriteria,
    entitiesColumns,
    selectedAssignments,
    totalAssignments,
    getList,
    search,
    addTagAggregations,
    searchInheritedEntities,
    onTermChange,
    onAssignmentChange,
    onSelectionChange,
    getCount,
    countIncrease,
    countDecrease,
    isInherited,
    parentHasTags,
    hasInheritedTag,
    onPageChange,
});
</script>
