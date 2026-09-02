<template>
    <ct-block name="ct_category_tree_field">
        <div ref="ctCategoryTreeField" class="ct-category-tree-field" :class="{ 'is--disabled': disabled }">
            <mt-floating-ui
                :is-opened="isExpanded"
                :match-reference-width="true"
                class="ct-category-tree-field__floating-ui"
                @close="closeDropdown"
            >
                <template #trigger>
                    <ct-contextual-field v-bind="$attrs" class="ct-category-tree-field__main-wrapper">
                        <template #ct-field-input="{ setFocusClass, removeFocusClass }">
                            <mt-loader v-if="isCategoriesLoading" class="ct-category-tree-field__loader" />

                            <ct-block name="ct_category_tree_field_input_labels">
                                <mt-badge
                                    v-for="selectedCategory in visibleTags"
                                    :key="selectedCategory.id"
                                    v-tooltip="{
                                        message: getBreadcrumb(selectedCategory),
                                        width: 300,
                                    }"
                                    size="m"
                                    class="ct-category-tree-field__selected-label"
                                >
                                    <span class="ct-category-tree-field__label-property">
                                        <slot name="labelProperty" :item="selectedCategory">
                                            {{ getLabelName(selectedCategory) }}
                                        </slot>
                                    </span>

                                    <button
                                        v-if="!disabled"
                                        class="ct-category-tree-field__dismiss-button"
                                        type="button"
                                        :aria-label="$t('global.default.remove')"
                                        @mousedown.prevent
                                        @click.stop="removeItem(selectedCategory)"
                                    >
                                        <mt-icon name="regular-times-s" size="8" />
                                    </button>
                                </mt-badge>
                            </ct-block>

                            <ct-block name="ct_category_tree_field_input_labels_hidden_tag">
                                <button
                                    v-if="numberOfHiddenTags > 0"
                                    class="ct-category-tree-field__label-more"
                                    type="button"
                                    @click="removeTagLimit"
                                >
                                    <mt-badge size="m">+{{ numberOfHiddenTags }}</mt-badge>
                                </button>
                            </ct-block>

                            <ct-block name="ct_category_tree_field_input_field">
                                <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                                <input
                                    ref="searchInput"
                                    v-model="term"
                                    type="text"
                                    class="ct-category-tree__input-field"
                                    :placeholder="placeholder"
                                    :disabled="disabled"
                                    @focus="openDropdown({ setFocusClass, removeFocusClass })"
                                    @keydown.delete="onDeleteKeyup"
                                />
                            </ct-block>
                        </template>
                    </ct-contextual-field>
                </template>

                <div ref="resultsPopover" class="ct-category-tree-field__results-popover">
                    <ct-block name="ct_category_tree_field_input_results_tree">
                        <ct-tree
                            v-if="term.length <= 0 && categories.length > 0"
                            ref="ctTree"
                            :items="categories"
                            after-id-property="afterCategoryId"
                            :sortable="false"
                            @get-tree-items="getTreeItems"
                        >
                            <template #headline><span></span></template>
                            <template #search><span></span></template>

                            <template #items="{ treeItems, translationContext }">
                                <ct-tree-item
                                    v-for="item in treeItems"
                                    :key="item.id"
                                    :item="item"
                                    :translation-context="translationContext"
                                    :active-parent-ids="selectedCategoriesPathIds"
                                    :active-item-ids="selectedCategoriesItemsIds"
                                    :sortable="false"
                                    should-focus
                                    :active-focus-id="selectedTreeItem.id"
                                    mark-inactive
                                    should-show-active-state
                                    @check-item="onCheckItem"
                                >
                                    <template #actions><span></span></template>
                                </ct-tree-item>
                            </template>
                        </ct-tree>
                    </ct-block>

                    <ct-block name="ct_category_tree_field_input_results_search_results">
                        <ul v-if="searchResult.length > 0 && term.length > 0" class="ct-category-tree-field__search-results">
                            <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                            <li
                                v-for="item in searchResult"
                                :key="item.id"
                                class="ct-category-tree-field__search-result"
                                :class="{ 'is--focus': isSearchResultInFocus(item) }"
                                @click.stop="onCheckSearchItem(item)"
                            >
                                <mt-checkbox
                                    :checked="isSearchItemChecked(item.id)"
                                    :disabled="item.disabled || undefined"
                                    class="ct-category-tree-field__search-results-checkbox"
                                />

                                <div class="ct-category-tree-field__search-results-icon">
                                    <mt-icon
                                        :name="item.childCount > 0 ? 'regular-folder' : 'regular-circle-xxs'"
                                        :size="item.childCount > 0 ? '16px' : '8px'"
                                    />
                                </div>

                                <span class="ct-category-tree-field__search-results-name">
                                    <ct-highlight-text :search-term="term" :text="getBreadcrumb(item)" />
                                </span>
                            </li>
                        </ul>
                    </ct-block>

                    <ct-block name="ct_category_tree_field_input_search_results_empty">
                        <p v-if="term.length > 0 && searchResult.length === 0" class="ct-category-tree-field__empty-state">
                            {{ $t('ct-category-tree-field.emptySearchResults') }}
                        </p>
                    </ct-block>
                </div>
            </mt-floating-ui>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection, RepositoryFactory */
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import './ct-category-tree-field.scss';

const { Criteria } = Contena.Data;
const { debounce } = Contena.Utils;

type Category = Entity<'category'> & {
    disabled?: boolean;
    breadcrumb?: string[];
};

type TreeItem = {
    id: string;
    parentId: string | null;
    childCount: number;
    children: TreeItem[];
    data: Category & { afterCategoryId?: string | null };
    checked?: boolean;
};

type CategoryCollection = EntityCollection<'category'>;

const props = defineProps({
    categoriesCollection: {
        type: Array,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        required: true,
    },
    categoryCriteria: {
        type: Object,
        default: null,
    },
    singleSelect: {
        type: Boolean,
        default: false,
    },
    contentLayoutId: {
        type: String,
        default: null,
    },
    isCategoriesLoading: {
        type: Boolean,
        default: false,
    },
    allowedTypes: {
        type: Array,
        default: null,
    },
});

const emit = defineEmits([
    'selection-add',
    'selection-remove',
    'categories-load-more',
    'update:categoriesCollection',
]);

const ctCategoryTreeField = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const resultsPopover = ref<HTMLElement | null>(null);

const ctTree = ref<{ treeItems: TreeItem[]; findById: (_id: string) => TreeItem | null } | null>(null);

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const isFetching = ref(false);
const isComponentReady = ref(false);
const categories = ref<CategoryCollection | Category[]>([]);
const selectedCategories = ref<string[]>([]);
const isExpanded = ref(false);
const term = ref('');
const searchResult = ref<CategoryCollection | Category[]>([]);
const searchResultFocusItem = ref<Category | Record<string, never>>({});
const setInputFocusClass = ref<(() => void) | null>(null);
const removeInputFocusClass = ref<(() => void) | null>(null);
const selectedTreeItem = ref<TreeItem | Record<string, never>>({});
const selectedCategoriesTotal = ref(0);

const categoriesCollection = computed(() => props.categoriesCollection as CategoryCollection);
const globalCategoryRepository = computed(() => repositoryFactory.create('category'));
const categoryRepository = computed(() => {
    return repositoryFactory.create(categoriesCollection.value.entity, categoriesCollection.value.source);
});
const contentLayoutAssignmentRepository = computed(() => repositoryFactory.create('category_content_layout'));
const visibleTags = computed(() => categoriesCollection.value);
const selectedCategoriesItemsIds = computed(() => {
    return props.contentLayoutId ? selectedCategories.value : categoriesCollection.value.getIds();
});
const selectedCategoriesItemsTotal = computed(() => {
    return props.contentLayoutId ? selectedCategoriesTotal.value : categoriesCollection.value.length;
});
const numberOfHiddenTags = computed(() => {
    return Math.max(0, selectedCategoriesItemsTotal.value - visibleTags.value.length);
});
const selectedCategoriesPathIds = computed(() => {
    return categoriesCollection.value.reduce<string[]>((pathIds, item) => {
        const itemPathIds = item.path ? item.path.split('|').filter(Boolean) : [];
        return [
            ...pathIds,
            ...itemPathIds,
        ];
    }, []);
});
const contentLayoutAssignmentCriteria = computed(() => {
    const criteria = new Criteria(1, 500);
    criteria.addFilter(Criteria.equals('contentLayoutId', props.contentLayoutId));
    return criteria;
});

const createdComponent = async () => {
    document.addEventListener('keydown', handleGeneralKeyEvents);

    if (!props.contentLayoutId) {
        return;
    }

    const result = await contentLayoutAssignmentRepository.value.search(contentLayoutAssignmentCriteria.value);
    selectedCategories.value = result.map((assignment: Entity<'category_content_layout'>) => assignment.categoryId);
    selectedCategoriesTotal.value = result.total ?? result.length;
};
const destroyedComponent = () => {
    document.removeEventListener('keydown', handleGeneralKeyEvents);
};
const getTreeItems = async (parentId: string | null = null) => {
    isFetching.value = true;
    const baseCriteria = (props.categoryCriteria as InstanceType<typeof Criteria> | null) ?? new Criteria(1, 500);
    const criteria = Criteria.fromCriteria(baseCriteria);
    criteria.addFilter(Criteria.equals('parentId', parentId));
    const result = await globalCategoryRepository.value.search(criteria, Contena.Context.api);

    disableCategories(result);
    if (parentId === null) {
        categories.value = result;
        isFetching.value = false;
        return;
    }

    result.forEach((category: Category) => (categories.value as CategoryCollection).add(category));
};
function disableCategories(items: Category[]) {
    if (!props.allowedTypes) {
        return;
    }
    items.forEach((category) => {
        category.disabled = !props.allowedTypes?.includes(category.type);
    });
}
const onCheckSearchItem = (item: Category) => {
    if (item.disabled) {
        return;
    }

    onCheckItem({
        checked: !isSearchItemChecked(item.id),
        id: item.id,
        data: item,
    });
};
function onCheckItem(
    item:
        | TreeItem
        | {
              id: string;
              checked: boolean;
              data?: Category;
          },
) {
    if (item.data?.disabled) {
        return false;
    }
    removeCheckedItems(item.id);
    const itemIsInCategories = categoriesCollection.value.has(item.id);
    if (item.checked && !itemIsInCategories) {
        const category = item.data ?? (item as unknown as Category);
        categoriesCollection.value.add(category);
        emit('selection-add', category);
        emitCategoriesCollectionUpdate();
        if (props.singleSelect) {
            isExpanded.value = false;
        }
        if (props.contentLayoutId) {
            selectedCategories.value.push(item.id);
            selectedCategoriesTotal.value += 1;
        }
        return true;
    }
    removeItem(item);
    return false;
}
function removeItem(item: Category | TreeItem) {
    categoriesCollection.value.remove(item.id);
    if (props.contentLayoutId) {
        const itemIndex = selectedCategories.value.indexOf(item.id);
        if (itemIndex >= 0) {
            selectedCategories.value.splice(itemIndex, 1);
            selectedCategoriesTotal.value -= 1;
        }
    }
    const removedItem = 'data' in item ? item.data : item;
    emitCategoriesCollectionUpdate();
    emit('selection-remove', removedItem);
}
function emitCategoriesCollectionUpdate() {
    emit('update:categoriesCollection', categoriesCollection.value);
}
const searchCategories = async (searchTerm: string) => {
    const criteria = new Criteria(1, 500);
    criteria.addFilter(Criteria.equals('type', 'page'));
    criteria.setTerm(searchTerm);
    const result = await globalCategoryRepository.value.search(criteria, Contena.Context.api);
    disableCategories(result);
    return result;
};
function isSearchItemChecked(itemId: string) {
    return selectedCategoriesItemsIds.value.includes(itemId);
}
const isSearchResultInFocus = (item: Category) => item.id === searchResultFocusItem.value.id;
const getBreadcrumb = (item: Category) => {
    if (item.breadcrumb && item.breadcrumb.length > 1) {
        return item.breadcrumb.join(' / ');
    }
    return item.translated?.name || item.name;
};
const getLabelName = (item: Category) => {
    if (item.breadcrumb && item.breadcrumb.length > 1) {
        return `.. / ${item.translated?.name || item.name} `;
    }
    return item.translated?.name || item.name;
};
const onDeleteKeyup = () => {
    if (term.value.length > 0 || categoriesCollection.value.length === 0) {
        return;
    }
    removeItem(categoriesCollection.value.last());
};
const removeTagLimit = () => emit('categories-load-more');
const openDropdown = (focusHandlers: { setFocusClass: () => void; removeFocusClass: () => void }) => {
    if (props.disabled) {
        return;
    }
    isExpanded.value = true;
    setInputFocusClass.value = focusHandlers.setFocusClass;
    removeInputFocusClass.value = focusHandlers.removeFocusClass;
    setInputFocusClass.value();
};
const closeDropdown = () => {
    isExpanded.value = false;
    removeInputFocusClass.value?.();
};
function handleGeneralKeyEvents(event: KeyboardEvent) {
    if (!isExpanded.value) {
        return;
    }
    switch (event.key.toLowerCase()) {
        case 'tab':
        case 'escape':
            closeDropdown();
            break;
        case 'arrowdown':
        case 'arrowleft':
        case 'arrowright':
        case 'arrowup':
            handleArrowKeyEvents(event);
            break;
        case 'enter': {
            const item = term.value.length > 0 ? searchResultFocusItem.value : selectedTreeItem.value;
            if (!('id' in item) || !item.id) {
                break;
            }
            const checkedItem = item as TreeItem & Category;
            checkedItem.checked = !checkedItem.checked;
            onCheckItem(checkedItem);
            term.value = '';
            break;
        }
    }
}
function handleArrowKeyEvents(event: KeyboardEvent) {
    const key = event.key.toLowerCase();
    if (term.value.length > 0) {
        if (key === 'arrowdown' || key === 'arrowup') {
            event.preventDefault();
            changeSearchSelection(key === 'arrowup' ? 'previous' : 'next');
        }
        return;
    }
    const currentItem = findTreeItemById();
    if (!currentItem) {
        return;
    }
    if (key === 'arrowdown') {
        const nextItem = isTreeItemOpened(currentItem.id)
            ? currentItem.children[0]
            : (getSibling(true, currentItem) ?? getSibling(true, findTreeItemById(currentItem.parentId)));
        if (nextItem) selectedTreeItem.value = nextItem;
    } else if (key === 'arrowup') {
        const previousItem = getSibling(false, currentItem);
        selectedTreeItem.value = previousItem ?? findTreeItemById(currentItem.parentId) ?? currentItem;
    } else if (key === 'arrowright') {
        toggleSelectedTreeItem(true);
    } else if (key === 'arrowleft' && !toggleSelectedTreeItem(false)) {
        selectedTreeItem.value = findTreeItemById(currentItem.parentId) ?? currentItem;
    }
}
function changeSearchSelection(direction: 'next' | 'previous' = 'next') {
    const currentIndex = searchResult.value.indexOf(searchResultFocusItem.value as Category);
    const nextItem = searchResult.value[currentIndex + (direction === 'previous' ? -1 : 1)];
    if (nextItem) {
        searchResultFocusItem.value = nextItem;
    }
}
const getFirstChildById = (itemId: string, children = ctTree.value?.treeItems ?? []): TreeItem | null => {
    const item = children.find((child) => child.id === itemId);
    if (item) {
        return item.children[0] ?? null;
    }
    for (const child of children) {
        const found = getFirstChildById(itemId, child.children);
        if (found) return found;
    }
    return null;
};
function getSibling(isNext: boolean, item: TreeItem | null, children = ctTree.value?.treeItems ?? []): TreeItem | null {
    if (!item) return null;
    const sibling = isNext
        ? children.find((child) => child.data.afterCategoryId === item.id)
        : children.find((child) => child.id === item.data.afterCategoryId);
    if (sibling) {
        if (!isNext && isTreeItemOpened(sibling.id) && sibling.children.length > 0) {
            return sibling.children.at(-1) ?? sibling;
        }
        return sibling;
    }
    for (const child of children) {
        const found = getSibling(isNext, item, child.children);
        if (found) return found;
    }
    return null;
}
function isTreeItemOpened(itemId: string) {
    const element = resultsPopover.value?.querySelector<HTMLElement>(`[data-item-id="${itemId}"]`);
    return element?.getAttribute('aria-expanded') === 'true';
}
function toggleSelectedTreeItem(shouldOpen: boolean) {
    if (!('id' in selectedTreeItem.value) || !selectedTreeItem.value.id) {
        return false;
    }
    const element = resultsPopover.value?.querySelector<HTMLElement>(`[data-item-id="${selectedTreeItem.value.id}"]`);
    const toggle = element?.querySelector<HTMLElement>('.ct-tree-item__toggle');
    if (!toggle || isTreeItemOpened(selectedTreeItem.value.id) === shouldOpen) {
        return false;
    }
    toggle.click();
    return true;
}
function findTreeItemById(itemId?: string | null) {
    const id = itemId ?? ('id' in selectedTreeItem.value ? selectedTreeItem.value.id : null);
    return id ? (ctTree.value?.findById(id) ?? null) : null;
}
function removeCheckedItems(keepId: string) {
    if (!props.singleSelect) return;
    categoriesCollection.value.forEach((category, index) => {
        if (category.id !== keepId) {
            categoriesCollection.value.splice(index, 1);
            index -= 1;
        }
    });
}

watch(
    () => props.categoriesCollection,
    async () => {
        if (categoriesCollection.value.entity && !isComponentReady.value && !isFetching.value) {
            await getTreeItems();
            isComponentReady.value = true;
        }
    },
    { immediate: true },
);
watch(
    term,
    async (newTerm) => {
        if (newTerm.length > 0) {
            searchResult.value = await searchCategories(newTerm);
            searchResultFocusItem.value = searchResult.value[0] ?? {};
            return;
        }

        await nextTick();
        selectedTreeItem.value = ctTree.value?.treeItems[0] ?? {};
    },
    { immediate: true },
);
watch(selectedTreeItem, (newItem) => {
    if (!('id' in newItem) || !newItem.id) return;
    debounce(() => {
        const element = resultsPopover.value?.querySelector<HTMLElement>(`[data-item-id="${newItem.id}"]`);
        element?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 50)();
});

onMounted(createdComponent);
onBeforeUnmount(destroyedComponent);

ctDefinePublic({
    repositoryFactory,
    isFetching,
    isComponentReady,
    categories,
    selectedCategories,
    isExpanded,
    term,
    searchResult,
    searchResultFocusItem,
    setInputFocusClass,
    removeInputFocusClass,
    selectedTreeItem,
    selectedCategoriesTotal,
    globalCategoryRepository,
    categoryRepository,
    contentLayoutAssignmentRepository,
    visibleTags,
    numberOfHiddenTags,
    selectedCategoriesItemsIds,
    selectedCategoriesItemsTotal,
    selectedCategoriesPathIds,
    contentLayoutAssignmentCriteria,
    createdComponent,
    destroyedComponent,
    getTreeItems,
    disableCategories,
    onCheckSearchItem,
    onCheckItem,
    removeItem,
    emitCategoriesCollectionUpdate,
    searchCategories,
    isSearchItemChecked,
    isSearchResultInFocus,
    getBreadcrumb,
    getLabelName,
    onDeleteKeyup,
    removeTagLimit,
    openDropdown,
    closeDropdown,
    handleGeneralKeyEvents,
    handleArrowKeyEvents,
    changeSearchSelection,
    getFirstChildById,
    getSibling,
    toggleSelectedTreeItem,
    findTreeItemById,
    removeCheckedItems,
});

defineExpose({
    repositoryFactory,
    isFetching,
    isComponentReady,
    categories,
    selectedCategories,
    isExpanded,
    term,
    searchResult,
    searchResultFocusItem,
    setInputFocusClass,
    removeInputFocusClass,
    selectedTreeItem,
    selectedCategoriesTotal,
    globalCategoryRepository,
    categoryRepository,
    contentLayoutAssignmentRepository,
    visibleTags,
    numberOfHiddenTags,
    selectedCategoriesItemsIds,
    selectedCategoriesItemsTotal,
    selectedCategoriesPathIds,
    contentLayoutAssignmentCriteria,
    createdComponent,
    destroyedComponent,
    getTreeItems,
    disableCategories,
    onCheckSearchItem,
    onCheckItem,
    removeItem,
    emitCategoriesCollectionUpdate,
    searchCategories,
    isSearchItemChecked,
    isSearchResultInFocus,
    getBreadcrumb,
    getLabelName,
    onDeleteKeyup,
    removeTagLimit,
    openDropdown,
    closeDropdown,
    handleGeneralKeyEvents,
    handleArrowKeyEvents,
    changeSearchSelection,
    getFirstChildById,
    getSibling,
    toggleSelectedTreeItem,
    findTreeItemById,
    removeCheckedItems,
});
</script>
