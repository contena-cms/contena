<template>
    <ct-block name="sw_landing_page_tree">
        <div class="ct-landing-page-tree">
            <ct-block name="sw_landing_page_tree_inner">
                <ct-tree
                    v-if="!isLoadingInitialData"
                    ref="landingPageTree"
                    class="ct-landing-page-tree__inner"
                    :items="landingPages"
                    :sortable="false"
                    :searchable="false"
                    :translation-context="translationContext"
                    :on-change-route="changeLandingPage"
                    :disable-context-menu="disableContextMenu"
                    :allow-delete-categories="allowDelete || undefined"
                    :allow-create-categories="false"
                    :active-tree-item-id="landingPageId"
                    @batch-delete="deleteCheckedItems"
                    @delete-element="onDeleteLandingPage"
                    @editing-end="syncLandingPages"
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
                        }"
                    >
                        <ct-block name="sw_landing_page_tree_items">
                            <ct-tree-item
                                v-for="item in treeItems"
                                :key="item.id"
                                :item="item"
                                :should-show-active-state="true"
                                :allow-duplicate="true"
                                :allow-new-categories="false || undefined"
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
                                :get-item-url="getLandingPageUrl"
                                @check-item="checkItem"
                            >
                                <template #grip>
                                    <span></span>
                                </template>

                                <template #actions="{ onDuplicate, onChangeRoute, deleteElement, toolTip }">
                                    <ct-block name="sw_landing_page_tree_items_actions">
                                        <ct-context-button
                                            v-tooltip="toolTip"
                                            class="ct-tree-item__context_button"
                                            :disabled="disableContextMenu || undefined"
                                        >
                                            <ct-block name="sw_landing_page_tree_items_actions_edit">
                                                <ct-context-menu-item @click="onChangeRoute(item)">
                                                    {{ $t('global.default.edit') }}
                                                </ct-context-menu-item>
                                            </ct-block>

                                            <ct-block name="sw_landing_page_tree_items_actions_duplicate">
                                                <ct-context-menu-item
                                                    class="ct-context-menu__duplicate-action"
                                                    @click="onDuplicate(item)"
                                                >
                                                    {{ $t(`global.default.duplicate`) }}
                                                </ct-context-menu-item>
                                            </ct-block>

                                            <ct-block name="sw_landing_page_tree_items_actions_delete">
                                                <ct-context-menu-item
                                                    class="ct-context-menu__group-button-delete"
                                                    variant="danger"
                                                    @click="deleteElement(item)"
                                                >
                                                    {{ $t('global.default.delete') }}
                                                </ct-context-menu-item>
                                            </ct-block>
                                        </ct-context-button>
                                    </ct-block>
                                </template>
                            </ct-tree-item>
                        </ct-block>
                    </template>
                </ct-tree>
            </ct-block>

            <template v-if="!isLoadingInitialData"><!-- Keeps the conditional chain connected across ct-block. --></template>
            <div v-else>
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item" />
            </div>

            <ct-block name="sw_landing_page_tree_load_more">
                <div v-if="hasMoreLandingPages" class="ct-landing-page-tree__load-more">
                    <mt-button
                        class="ct-landing-page-tree__load-more-button"
                        size="small"
                        variant="secondary"
                        :disabled="isLoadingMore"
                        :is-loading="isLoadingMore"
                        @click="loadMoreLandingPages"
                    >
                        {{ $t('ct-landing-page.general.buttonLoadMore') }}
                    </mt-button>
                </div>
            </ct-block>

            <ct-block name="sw_landing_page_tree_action">
                <div class="ct-landing-page-tree__add-button">
                    <mt-button
                        class="ct-landing-page-tree__add-button-button"
                        size="small"
                        :disabled="disableContextMenu || !acl.can('landing_page.creator') || undefined"
                        variant="secondary"
                        @click="$router.push(newLandingPageUrl())"
                    >
                        {{ $t('ct-landing-page.general.buttonCreate') }}
                    </mt-button>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-landing-page-tree.scss';
const { Criteria } = Contena.Data;

// contena.api.max_limit caps every Admin API request, rejecting anything higher instead of clamping.
// It is configurable but defaults to 500, which the Administration hardcodes everywhere; stay consistent
// with that until the value is exposed to the client.
const PAGE_SIZE = 500;

const createLandingPageCriteria = (page) => {
    const criteria = new Criteria(page, PAGE_SIZE);
    criteria.addSorting(Criteria.sort('name'));
    // Names are not unique, so paging without a stable tiebreaker can skip or repeat entries.
    criteria.addSorting(Criteria.sort('id'));

    return criteria;
};

const props = defineProps({
    landingPageId: {
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
    'landing-page-checked-elements-count',
    'unsaved-changes',
]);

import { ref, computed, inject, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const $router = router;
const landingPageTree = ref(null);

const repositoryFactory = inject('repositoryFactory');
const syncService = inject('syncService');
const acl = inject('acl');

const loadedLandingPages = ref({});
const translationContext = ref('ct-landing-page');
const linkContext = ref('ct.category.landingPageDetail');
const isLoadingInitialData = ref(true);
const isLoadingMore = ref(false);
const page = ref(1);
const total = ref(0);

const landingPagesToDelete = computed(() => {
    return Contena.Store.get('swCategoryDetail').landingPagesToDelete;
});
const landingPageCriteria = computed(() => createLandingPageCriteria(page.value));
const landingPage = computed(() => {
    return Contena.Store.get('swCategoryDetail').landingPage;
});
const landingPageRepository = computed(() => {
    return repositoryFactory.create('landing_page');
});
const landingPages = computed(() => {
    return Object.values(loadedLandingPages.value);
});
const hasMoreLandingPages = computed(() => {
    return landingPages.value.length < total.value;
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

const createdComponent = () => {
    loadLandingPages()
        .catch(() => {
            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
        })
        .finally(() => {
            isLoadingInitialData.value = false;
        });
};
const loadLandingPages = () => {
    return landingPageRepository.value.search(landingPageCriteria.value).then((result) => {
        total.value = result.total ?? result.length;
        addLandingPages(result);
    });
};
const loadMoreLandingPages = () => {
    isLoadingMore.value = true;
    page.value += 1;

    return loadLandingPages()
        .catch(() => {
            page.value -= 1;

            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
        })
        .finally(() => {
            isLoadingMore.value = false;
        });
};
const resetLandingPages = () => {
    page.value = 1;
    total.value = 0;
    loadedLandingPages.value = {};
};
// Offsets shift as soon as entries are added, removed or renamed, so every page that was
// already loaded has to be fetched again to stay in sync with the server ordering.
const reloadLandingPages = async () => {
    const loadedPages = page.value;
    const reloaded = {};
    let reloadedTotal = 0;

    for (let currentPage = 1; currentPage <= loadedPages; currentPage += 1) {
        page.value = currentPage;

        const result = await landingPageRepository.value.search(landingPageCriteria.value);

        reloadedTotal = result.total ?? result.length;
        result.forEach((landingPageValue) => {
            reloaded[landingPageValue.id] = landingPageValue;
        });
    }

    // Swapped in one go: emptying the map first would flash an empty tree on every mutation.
    total.value = reloadedTotal;
    loadedLandingPages.value = reloaded;
};
const checkedElementsCount = (count) => {
    emit('landing-page-checked-elements-count', count);
};
const deleteCheckedItems = (checkedItems) => {
    const ids = Object.keys(checkedItems);

    return landingPageRepository.value.syncDeleted(ids).then(() => {
        ids.forEach((id) => removeFromStore(id));

        return reloadLandingPages();
    });
};
const onDeleteLandingPage = ({ data: landingPage }) => {
    if (landingPage.isNew()) {
        delete loadedLandingPages.value[landingPage.id];
        return Promise.resolve();
    }

    return landingPageRepository.value.delete(landingPage.id).then(() => {
        removeFromStore(landingPage.id);

        if (landingPage.id === props.landingPageId) {
            void router.push({ name: 'ct.category.index' });
        }

        return reloadLandingPages();
    });
};
const changeLandingPage = (landingPageValue) => {
    const route = {
        name: 'ct.category.landingPageDetail',
        params: { id: landingPageValue.id },
    };
    if (landingPage.value && landingPageRepository.value.hasChanges(landingPage.value)) {
        emit('unsaved-changes', route);
    } else {
        void router.push(route);
    }
};
const duplicateElement = (contextItem) => {
    const behavior = {
        cloneChildren: false,
        overwrites: {
            name: `${contextItem.data.name} ${t('global.default.copy')}`,
            url: `${contextItem.data.url}-${t('global.default.copy')}`,
            active: false,
        },
    };

    landingPageRepository.value
        .clone(contextItem.id, behavior, Contena.Context.api)
        .then((clone) => {
            return reloadLandingPages().then(() => {
                const criteria = new Criteria(1, 25);
                criteria.setIds([clone.id]);

                return landingPageRepository.value.search(criteria).then((landingPages) => {
                    landingPages.forEach((element) => {
                        element.childCount = 0;
                        element.parentId = null;
                    });

                    addLandingPages(landingPages);
                });
            });
        })
        .catch(() => {
            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
        });
};
const createNewElement = (contextItem, parentId, name = '') => {
    const newLandingPage = createNewLandingPage(name);
    addLandingPage(newLandingPage);
    return newLandingPage;
};
const syncLandingPages = () => {
    return landingPageRepository.value.sync(landingPages.value).then(() => {
        return reloadLandingPages();
    });
};
const createNewLandingPage = (name) => {
    const newLandingPage = landingPageRepository.value.create();

    newLandingPage.name = name;
    newLandingPage.active = false;

    newLandingPage.save = () => {
        return landingPageRepository.value.save(newLandingPage).then(() => {
            return reloadLandingPages().then(() => {
                const criteria = new Criteria(1, 25);
                criteria.setIds([newLandingPage.id].filter((id) => id !== null));

                return landingPageRepository.value.search(criteria).then((landingPages) => {
                    addLandingPages(landingPages);
                });
            });
        });
    };

    return newLandingPage;
};
const addLandingPage = (landingPage) => {
    if (!landingPage) {
        return;
    }

    loadedLandingPages.value = {
        ...loadedLandingPages.value,
        [landingPage.id]: landingPage,
    };
};
const addLandingPages = (landingPages) => {
    if (!landingPages) {
        return;
    }

    const existingLandingPageEntries = Object.entries(loadedLandingPages.value || {});
    const newLandingPageEntries = landingPages.map((landingPage) => {
        return [
            landingPage.id,
            landingPage,
        ];
    });

    loadedLandingPages.value = Object.fromEntries([
        ...existingLandingPageEntries,
        ...newLandingPageEntries,
    ]);
};
const removeFromStore = (id) => {
    loadedLandingPages.value = Object.fromEntries(
        Object.entries(loadedLandingPages.value || {}).filter(([key]) => {
            return key !== id;
        }),
    );

    total.value = Math.max(0, total.value - 1);
};
const getLandingPageUrl = (landingPage) => {
    return router.resolve({
        name: linkContext.value,
        params: { id: landingPage.id },
    }).href;
};
const newLandingPageUrl = () => {
    return {
        name: 'ct.category.landingPageDetail',
        params: { id: 'create' },
    };
};

watch(
    () => landingPagesToDelete.value,
    (value) => {
        if (value === undefined) {
            return;
        }

        landingPageTree.value.onDeleteElements(value);

        Contena.Store.get('swCategoryDetail').landingPagesToDelete = undefined;
    },
);
watch(
    () => landingPage.value,
    (newVal, oldVal) => {
        // load data when path is available
        if (!oldVal && isLoadingInitialData.value) {
            loadLandingPages();
            return;
        }

        // back to index
        if (newVal === null) {
            return;
        }

        // reload after save
        if (oldVal && props.landingPageId !== 'create' && newVal.id === oldVal.id) {
            landingPageRepository.value.get(newVal.id).then((newLandingPage) => {
                loadedLandingPages.value[newLandingPage.id] = newLandingPage;
            });
        }
    },
);
watch(
    () => props.currentLanguageId,
    () => {
        isLoadingInitialData.value = true;
        resetLandingPages();

        loadLandingPages().finally(() => {
            isLoadingInitialData.value = false;
        });
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    syncService,
    acl,
    loadedLandingPages,
    translationContext,
    linkContext,
    isLoadingInitialData,
    isLoadingMore,
    page,
    total,
    landingPagesToDelete,
    landingPageCriteria,
    landingPage,
    landingPageRepository,
    landingPages,
    hasMoreLandingPages,
    disableContextMenu,
    contextMenuTooltipText,
    createdComponent,
    loadLandingPages,
    loadMoreLandingPages,
    resetLandingPages,
    reloadLandingPages,
    checkedElementsCount,
    deleteCheckedItems,
    onDeleteLandingPage,
    changeLandingPage,
    duplicateElement,
    createNewElement,
    syncLandingPages,
    createNewLandingPage,
    addLandingPage,
    addLandingPages,
    removeFromStore,
    getLandingPageUrl,
    newLandingPageUrl,
});

defineExpose({
    repositoryFactory,
    syncService,
    acl,
    loadedLandingPages,
    translationContext,
    linkContext,
    isLoadingInitialData,
    isLoadingMore,
    page,
    total,
    landingPagesToDelete,
    landingPageCriteria,
    landingPage,
    landingPageRepository,
    landingPages,
    hasMoreLandingPages,
    disableContextMenu,
    contextMenuTooltipText,
    createdComponent,
    loadLandingPages,
    loadMoreLandingPages,
    resetLandingPages,
    reloadLandingPages,
    checkedElementsCount,
    deleteCheckedItems,
    onDeleteLandingPage,
    changeLandingPage,
    duplicateElement,
    createNewElement,
    syncLandingPages,
    createNewLandingPage,
    addLandingPage,
    addLandingPages,
    removeFromStore,
    getLandingPageUrl,
    newLandingPageUrl,
});
</script>
