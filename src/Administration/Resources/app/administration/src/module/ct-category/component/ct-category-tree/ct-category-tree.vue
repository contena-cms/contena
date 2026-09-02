<template>
    <ct-block name="ct_category_tree">
        <div class="ct-category-tree">
            <ct-tree
                v-if="!isLoadingInitialData"
                ref="categoryTree"
                class="ct-category-tree__inner"
                after-id-property="afterCategoryId"
                :items="categories"
                :sortable="sortable"
                :searchable="false"
                :active-tree-item-id="categoryId"
                :translation-context="translationContext"
                :on-change-route="changeCategory"
                :disable-context-menu="disableContextMenu"
                :allow-delete-categories="allowDelete || undefined"
                :initially-expanded-root="!categoryId"
                @batch-delete="deleteCheckedItems"
                @delete-element="onDeleteCategory"
                @drag-end="onUpdatePositions"
                @get-tree-items="onGetTreeItems"
                @editing-end="syncSiblings"
                @checked-elements-count="checkedElementsCount"
            >
                <template #headline>
                    <span></span>
                </template>

                <template
                    #items="{
                        treeItems,
                        sortable,
                        draggedItem,
                        newElementId,
                        checkItem,
                        translationContext,
                        onChangeRoute,
                        disableContextMenu,
                        selectedItemsPathIds,
                        checkedItemIds,
                    }"
                >
                    <ct-block name="ct_category_tree_items">
                        <ct-tree-item
                            v-for="item in treeItems"
                            :key="item.id"
                            :item="item"
                            :should-show-active-state="true"
                            :allow-duplicate="false"
                            :allow-new-categories="allowCreate || undefined"
                            :allow-delete-categories="allowDelete || undefined"
                            :active="item.active"
                            :translation-context="translationContext"
                            :on-change-route="onChangeRoute"
                            :sortable="sortable"
                            :dragged-item="draggedItem"
                            :disable-context-menu="disableContextMenu"
                            :display-checkbox="allowEdit || undefined"
                            :context-menu-tooltip-text="contextMenuTooltipText"
                            :new-element-id="newElementId"
                            :get-item-url="getCategoryUrl"
                            :get-is-highlighted="isHighlighted"
                            :active-parent-ids="selectedItemsPathIds"
                            :active-item-ids="checkedItemIds"
                            @check-item="checkItem"
                        />
                    </ct-block>
                </template>
            </ct-tree>

            <div v-else>
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item-nested" />
                <ct-skeleton variant="tree-item-nested" />
                <ct-skeleton variant="tree-item-nested" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item-nested" />
                <ct-skeleton variant="tree-item-nested" />
            </div>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-category-tree.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    categoryId: {
        type: String,
        required: false,
        default: null,
    },

    currentLanguageId: {
        type: String,
        required: true,
    },

    allowEdit: {
        type: Boolean,
        required: false,
        default: true,
    },

    allowCreate: {
        type: Boolean,
        required: false,
        default: true,
    },

    allowDelete: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits([
    'category-checked-elements-count',
    'unsaved-changes',
]);

import { ref, computed, inject, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const { t } = useI18n();

const categoryTree = ref(null);

const { createNotificationError } = useNotification();
const repositoryFactory = inject('repositoryFactory');
const syncService = inject('syncService');

const loadedCategories = ref({});
const translationContext = ref('ct-category');
const linkContext = ref('ct.category.detail');
const isLoadingInitialData = ref(true);
const loadedParentIds = ref([]);
const sortable = ref(props.allowEdit);

const categoriesToDelete = computed(() => {
    return Contena.Store.get('ctCategoryDetail').categoriesToDelete;
});
const categoryRepository = computed(() => {
    return repositoryFactory.create('category');
});
const category = computed(() => {
    return Contena.Store.get('ctCategoryDetail').category;
});
const categories = computed(() => {
    return Object.values(loadedCategories.value);
});
const disableContextMenu = computed(() => {
    if (!props.allowEdit) {
        return true;
    }

    return props.currentLanguageId !== Contena.Context.api.systemLanguageId;
});
const contextMenuTooltipText = computed(() => {
    if (!props.allowEdit) {
        return t('ct-privileges.tooltip.warning');
    }

    return null;
});
const criteria = computed(() => {
    return new Criteria()
        .addAssociation('navigationChannels')
        .addAssociation('footerChannels')
        .addAssociation('serviceChannels');
});
const criteriaWithChildren = computed(() => {
    const parentCriteria = Criteria.fromCriteria(criteria.value).setLimit(1);
    parentCriteria.associations.push({
        association: 'children',
        criteria: Criteria.fromCriteria(criteria.value),
    });

    return parentCriteria;
});

const createdComponent = () => {
    if (category.value !== null) {
        openInitialTree();
    }

    if (!props.categoryId) {
        loadRootCategories().finally(() => {
            isLoadingInitialData.value = false;
        });
    }
};
const openInitialTree = () => {
    isLoadingInitialData.value = true;
    loadedCategories.value = {};
    loadedParentIds.value = [];

    loadRootCategories().then(() => {
        if (!category.value || category.value.path === null) {
            isLoadingInitialData.value = false;
            return Promise.resolve();
        }

        return loadActiveCategory().then(() => {
            isLoadingInitialData.value = false;
        });
    });
};
const loadActiveCategory = () => {
    if (!category.value || category.value.path === null || category.value.id in loadedCategories.value) {
        return Promise.resolve();
    }

    const parentIds = category.value.path.split('|').filter((id) => !!id);
    const parentPromises = [];

    parentIds.forEach((id) => {
        const promise = categoryRepository.value.get(id, Contena.Context.api, criteriaWithChildren.value).then((result) => {
            addCategories([
                result,
                ...result.children,
            ]);
        });
        parentPromises.push(promise);
    });

    return Promise.all(parentPromises);
};
const onUpdatePositions = Contena.Utils.debounce(({ draggedItem, oldParentId, newParentId }) => {
    if (draggedItem.children.length > 0) {
        draggedItem.children.forEach((child) => {
            removeFromStore(child.id);
        });
        loadedParentIds.value = loadedParentIds.value.filter((id) => id !== draggedItem.id);
    }

    syncSiblings({ parentId: newParentId }).then(() => {
        if (oldParentId !== newParentId) {
            syncSiblings({ parentId: oldParentId });
        }

        sortable.value = props.allowEdit;
    });
}, 400);
const checkedElementsCount = (count) => {
    emit('category-checked-elements-count', count);
};
const deleteCheckedItems = async (checkedItems) => {
    const ids = Object.keys(checkedItems);

    const hasNavigationCategories = ids.some((id) => {
        return (
            loadedCategories.value[id]?.navigationChannels !== null &&
            loadedCategories.value[id]?.navigationChannels.length > 0
        );
    });

    if (hasNavigationCategories) {
        createNotificationError({
            message: t('ct-category.general.errorNavigationEntryPointMultiple'),
        });

        const categories = ids.map((id) => {
            return loadedCategories.value[id];
        });

        // reload to remove selection
        ids.forEach((deleted) => {
            delete loadedCategories.value[deleted];
        });
        void nextTick(() => {
            addCategories(categories);
        });

        return;
    }

    await categoryRepository.value.syncDeleted(ids, Contena.Context.api);

    const categories = ids.map((id) => loadedCategories.value[id]);

    await fixSortingForCategories(categories);

    ids.forEach((id) => {
        removeFromStore(id);
    });
};
const onDeleteCategory = ({ data: category, children, checked }) => {
    if (category.isNew()) {
        delete loadedCategories.value[category.id];
        return Promise.resolve();
    }

    if (isErrorNavigationEntryPoint(category)) {
        // remove delete flags
        category.isDeleted = false;
        if (children.length > 0) {
            children.forEach((child) => {
                child.data.isDeleted = false;
            });
        }

        // reinsert category in sorting because the tree
        // already overwrites the afterCategoryId of the following category
        const next = getNextCategory(category);

        if (next) {
            next.afterCategoryId = category.id;
        }

        // reload after changes
        loadedCategories.value = { ...loadedCategories.value };

        createNotificationError({
            message: entryPointWarningMessage(category),
        });
        return Promise.resolve();
    }

    return categoryRepository.value.delete(category.id).then(async () => {
        removeFromStore(category.id);

        if (category.parentId !== null) {
            const updatedParent = await categoryRepository.value.get(category.parentId, Contena.Context.api, criteria.value);
            addCategory(updatedParent);
        }

        await fixSortingForCategories([category], true);

        if (category.id === props.categoryId) {
            void router.push({ name: 'ct.category.index' });
        }

        if (checked === true) {
            categoryTree.value.checkedElementsCount = Math.max(0, categoryTree.value.checkedElementsCount - 1);
            emit('category-checked-elements-count', categoryTree.value.checkedElementsCount);
        }
    });
};
const fixSortingForCategories = (categories, isSorted = false) => {
    const categoriesToBeChanged = [];

    categories.forEach((category) => {
        // We need the second parameter, because the value of `afterCategoryId` of the actual next category
        // is either updated already in case of `onDeleteCategory`, but not in case of `deleteCheckedItems`
        const nextCategory = getNextCategory(category, isSorted ? 'afterCategoryId' : 'id');

        if (!nextCategory) {
            return;
        }

        nextCategory.afterCategoryId = category.afterCategoryId;

        if (categories.find((item) => item.id === nextCategory.id)) {
            return;
        }

        categoriesToBeChanged.push(nextCategory);
    });

    return categoryRepository.value.saveAll(categoriesToBeChanged);
};
const getNextCategory = (category, key = 'id') => {
    return Object.values(loadedCategories.value).find((item) => {
        return item.parentId === category.parentId && item.afterCategoryId === category[key];
    });
};
const changeCategory = (categoryValue) => {
    const route = {
        name: 'ct.category.detail',
        params: { id: categoryValue.id },
    };
    if (category.value && categoryRepository.value.hasChanges(category.value)) {
        emit('unsaved-changes', route);
    } else {
        void router.push(route);
    }
};
const onGetTreeItems = (parentId) => {
    if (loadedParentIds.value.includes(parentId)) {
        return Promise.resolve();
    }
    const criteriaValue = Criteria.fromCriteria(criteria.value);
    criteriaValue.addFilter(Criteria.equals('parentId', parentId));
    criteriaValue.setIds([]);
    return categoryRepository.value.search(criteriaValue).then((children) => {
        addCategories(children);
        loadedParentIds.value.push(parentId);
    });
};
const getChildrenFromParent = (parentId) => {
    return onGetTreeItems(parentId);
};
const loadRootCategories = () => {
    const criteriaValue = Criteria.fromCriteria(criteria.value).addFilter(Criteria.equals('parentId', null));
    return categoryRepository.value.search(criteriaValue).then((result) => {
        addCategories(result);
    });
};
const createNewElement = (contextItem, parentId, name = '') => {
    sortable.value = false;

    if (!parentId && contextItem) {
        parentId = contextItem.parentId;
    }
    const newCategory = createNewCategory(name, parentId);
    addCategory(newCategory);
    return newCategory;
};
const createNewCategory = (name, parentId) => {
    const newCategory = categoryRepository.value.create();
    newCategory.name = name;
    newCategory.parentId = parentId;
    newCategory.childCount = 0;
    newCategory.active = false;
    newCategory.visible = true;
    newCategory.save = () => {
        return categoryRepository.value.save(newCategory).then(() => {
            const criteriaValue = Criteria.fromCriteria(criteria.value).setIds(
                [
                    newCategory.id,
                    parentId,
                ].filter((id) => id !== null),
            );
            categoryRepository.value.search(criteriaValue).then((categories) => {
                addCategories(categories);

                sortable.value = props.allowEdit;
            });
        });
    };
    return newCategory;
};
const syncSiblings = ({ parentId }) => {
    const siblings = categories.value.filter((category) => {
        return category.parentId === parentId;
    });

    return categoryRepository.value
        .sync(siblings)
        .then(() => {
            loadedParentIds.value = loadedParentIds.value.filter((id) => id !== parentId);
            return getChildrenFromParent(parentId);
        })
        .then(() => {
            categoryRepository.value.get(parentId, Contena.Context.api, criteria.value).then((parent) => {
                addCategory(parent);
            });
        });
};
const addCategory = (category) => {
    if (!category) {
        return;
    }

    loadedCategories.value[category.id] = category;
};
const addCategories = (categories) => {
    categories.forEach((category) => {
        loadedCategories.value[category.id] = category;
    });
};
const removeFromStore = (id) => {
    const deletedIds = getDeletedIds(id);
    loadedParentIds.value = loadedParentIds.value.filter((loadedId) => {
        return !deletedIds.includes(loadedId);
    });

    deletedIds.forEach((deleted) => {
        delete loadedCategories.value[deleted];
    });
};
const getDeletedIds = (idToDelete) => {
    const idsToDelete = [idToDelete];
    Object.keys(loadedCategories.value).forEach((id) => {
        const currentCategory = loadedCategories.value[id];
        if (currentCategory.parentId === idToDelete) {
            idsToDelete.push(...getDeletedIds(id));
        }
    });
    return idsToDelete;
};
const getCategoryUrl = (category) => {
    return router.resolve({
        name: linkContext.value,
        params: { id: category.id },
    }).href;
};
const isHighlighted = ({ data: category }) => {
    return (
        (category.navigationChannels !== null && category.navigationChannels.length > 0) ||
        (category.serviceChannels !== null && category.serviceChannels.length > 0) ||
        (category.footerChannels !== null && category.footerChannels.length > 0)
    );
};
const isErrorNavigationEntryPoint = (category) => {
    const { navigationChannels, serviceChannels, footerChannels } = category;

    return [
        navigationChannels,
        serviceChannels,
        footerChannels,
    ].some((navigation) => navigation !== null && navigation?.length > 0);
};
const entryPointWarningMessage = (category) => {
    const { serviceChannels, footerChannels } = category;

    if (serviceChannels !== null && serviceChannels?.length > 0) {
        return t(
            'ct-category.general.errorNavigationEntryPoint',
            {
                entryPointLabel: t('ct-category.base.entry-point-card.types.labelServiceNavigation'),
            },
            0,
        );
    }

    if (footerChannels !== null && footerChannels?.length > 0) {
        return t(
            'ct-category.general.errorNavigationEntryPoint',
            {
                entryPointLabel: t('ct-category.base.entry-point-card.types.labelFooterNavigation'),
            },
            0,
        );
    }

    return t(
        'ct-category.general.errorNavigationEntryPoint',
        {
            entryPointLabel: t('ct-category.base.entry-point-card.types.labelMainNavigation'),
        },
        0,
    );
};

watch(
    () => categoriesToDelete.value,
    (value) => {
        if (value === undefined) {
            return;
        }

        categoryTree.value.onDeleteElements(value);

        Contena.Store.get('ctCategoryDetail').categoriesToDelete = undefined;
    },
);
watch(
    () => props.allowEdit,
    (value) => {
        sortable.value = value;
    },
);
watch(
    () => category.value,
    (newVal, oldVal) => {
        if (!oldVal && isLoadingInitialData.value) {
            openInitialTree();
            return;
        }
        if (newVal === null) {
            return;
        }
        if (oldVal && newVal.id === oldVal.id) {
            const affectedCategoryIds = [
                newVal.id,
                ...oldVal.navigationChannels.map((channel) => channel.navigationCategoryId),
                ...oldVal.footerChannels.map((channel) => channel.footerCategoryId),
                ...oldVal.serviceChannels.map((channel) => channel.serviceCategoryId),
            ];

            const criteriaValue = Criteria.fromCriteria(criteria.value).setIds(
                affectedCategoryIds.filter((value, index, self) => {
                    return value !== null && self.indexOf(value) === index;
                }),
            );

            categoryRepository.value.search(criteriaValue).then((categories) => {
                addCategories(categories);
            });

            return;
        }
        void loadActiveCategory().then(() => {
            categoryTree.value.openTreeById();
        });
    },
);
watch(
    () => props.currentLanguageId,
    () => {
        openInitialTree();
    },
);

createdComponent();

ctDefinePublic({
    createNotificationError,
    repositoryFactory,
    syncService,
    loadedCategories,
    translationContext,
    linkContext,
    isLoadingInitialData,
    loadedParentIds,
    sortable,
    categoriesToDelete,
    categoryRepository,
    category,
    categories,
    disableContextMenu,
    contextMenuTooltipText,
    criteria,
    criteriaWithChildren,
    createdComponent,
    openInitialTree,
    loadActiveCategory,
    onUpdatePositions,
    checkedElementsCount,
    deleteCheckedItems,
    onDeleteCategory,
    fixSortingForCategories,
    getNextCategory,
    changeCategory,
    onGetTreeItems,
    getChildrenFromParent,
    loadRootCategories,
    createNewElement,
    createNewCategory,
    syncSiblings,
    addCategory,
    addCategories,
    removeFromStore,
    getDeletedIds,
    getCategoryUrl,
    isHighlighted,
    isErrorNavigationEntryPoint,
    entryPointWarningMessage,
});

defineExpose({
    createNotificationError,
    repositoryFactory,
    syncService,
    loadedCategories,
    translationContext,
    linkContext,
    isLoadingInitialData,
    loadedParentIds,
    sortable,
    categoriesToDelete,
    categoryRepository,
    category,
    categories,
    disableContextMenu,
    contextMenuTooltipText,
    criteria,
    criteriaWithChildren,
    createdComponent,
    openInitialTree,
    loadActiveCategory,
    onUpdatePositions,
    checkedElementsCount,
    deleteCheckedItems,
    onDeleteCategory,
    fixSortingForCategories,
    getNextCategory,
    changeCategory,
    onGetTreeItems,
    getChildrenFromParent,
    loadRootCategories,
    createNewElement,
    createNewCategory,
    syncSiblings,
    addCategory,
    addCategories,
    removeFromStore,
    getDeletedIds,
    getCategoryUrl,
    isHighlighted,
    isErrorNavigationEntryPoint,
    entryPointWarningMessage,
});
</script>
