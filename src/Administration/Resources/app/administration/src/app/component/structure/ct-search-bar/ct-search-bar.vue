<template>
    <ct-block name="sw_search_bar">
        <div class="ct-search-bar">
            <ct-block name="sw_search_bar_button_off_canvas_toggle">
                <mt-button
                    class="ct-search-bar__off-canvas-toggle"
                    variant="tertiary"
                    size="large"
                    square
                    :aria-label="
                        isOffCanvasShown
                            ? $t('global.ct-admin-menu.linkCloseMenu')
                            : $t('global.ct-admin-menu.linkExpandMenu')
                    "
                    @click="toggleOffCanvas"
                >
                    <mt-icon name="regular-bars" size="20px" />
                </mt-button>
            </ct-block>

            <ct-block name="sw_search_bar_container">
                <div class="ct-search-bar__container">
                    <ct-block name="sw_search_bar_mobile_controls">
                        <div v-if="!isSearchBarShown" class="ct-search-bar__mobile-controls">
                            <ct-block name="sw_search_bar_button_search">
                                <mt-button
                                    class="ct-search-bar__button"
                                    variant="tertiary"
                                    size="large"
                                    square
                                    :aria-label="$t('global.ct-search-bar.ariaLabelOpenSearch')"
                                    @click="showSearchBar"
                                >
                                    <mt-icon name="regular-search" size="20px" />
                                </mt-button>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_search_bar_field">
                        <div v-if="isSearchBarShown" class="ct-search-bar__field-wrapper" @click="setFocus">
                            <div class="ct-search-bar__field" :class="searchBarFieldClasses">
                                <ct-block name="sw_search_bar_type">
                                    <span
                                        class="ct-search-bar__type--v2"
                                        role="button"
                                        tabindex="0"
                                        @click.stop="onOpenModuleFiltersDropDown"
                                        @keydown.enter="onOpenModuleFiltersDropDown"
                                    >
                                        {{ getLabelSearchType() }}
                                        <mt-icon name="regular-chevron-down-xxs" />
                                    </span>
                                </ct-block>

                                <ct-block name="sw_search_bar_input">
                                    <slot name="search-input">
                                        <ct-block name="sw_search_bar_slot_input">
                                            <div
                                                class="ct-search-bar__input-wrapper"
                                                @focusin="onFocusInput"
                                                @focusout="onBlur"
                                                @keydown.delete="resetSearchType"
                                                @keyup.esc="clearSearchTerm"
                                                @keyup.enter.prevent="onKeyUpEnter"
                                                @keydown.up.prevent="navigateUpResults"
                                                @keydown.down.prevent="navigateDownResults"
                                            >
                                                <mt-search
                                                    ref="searchInput"
                                                    class="ct-search-bar__input"
                                                    :model-value="searchTerm"
                                                    :placeholder="placeholderSearchInput"
                                                    :aria-label="placeholderSearchInput"
                                                    @update:model-value="onSearchTermUpdate"
                                                />
                                            </div>
                                        </ct-block>
                                    </slot>
                                </ct-block>
                            </div>

                            <mt-button
                                class="ct-search-bar__field-close"
                                variant="tertiary"
                                size="large"
                                square
                                :aria-label="$t('global.ct-search-bar.ariaLabelCloseSearch')"
                                @click.stop="hideSearchBar"
                            >
                                <mt-icon name="solid-times" size="12px" />
                            </mt-button>
                        </div>
                    </ct-block>

                    <ct-block name="sw_search_bar_results">
                        <div
                            v-if="showResultsContainer"
                            ref="resultsContainer"
                            class="ct-search-bar__results ct-search-bar__results--v2"
                            :class="{ 'is-empty-state': isResultEmpty() }"
                        >
                            <ct-block name="sw_search_bar_results_content">
                                <div class="ct-search-bar__results-wrapper-content">
                                    <ct-block name="sw_search_bar_results_empty_state">
                                        <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                                        <mt-loader v-if="isLoading" />
                                    </ct-block>

                                    <!-- eslint-disable ct-deprecation-rules/no-twigjs-blocks -->
                                    <template v-if="isLoading"
                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                    >
                                    <template
                                        v-for="(entity, column) in results"
                                        v-else-if="!isResultEmpty()"
                                        :key="entity.entity"
                                    >
                                        <ct-block name="sw_search_bar_results_list">
                                            <div class="ct-search-bar__results-column">
                                                <ct-block name="sw_search_bar_results_list_column">
                                                    <ct-block name="sw_search_bar_results_list_column_header">
                                                        <div class="ct-search-bar__results-column-header">
                                                            <ct-block name="sw_search_bar_results_list_column_header_title">
                                                                <span class="ct-search-bar__types-header-entity">
                                                                    {{
                                                                        $t(`global.entities.${entity.entity}`, entity.total)
                                                                    }}
                                                                </span>
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>

                                                    <!-- "34" below is the <ct-search-bar-item />'s height -->
                                                    <ul
                                                        class="ct-search-bar__results-list"
                                                        :style="{ minHeight: `${34 * entity.entities.length}px` }"
                                                    >
                                                        <ct-search-bar-item
                                                            v-for="(item, index) in entity.entities"
                                                            :key="item.id"
                                                            :item="item"
                                                            :type="entity.entity"
                                                            :search-term="searchTerm"
                                                            :column="column"
                                                            :index="index"
                                                            :entity-icon-color="getEntityIconColor(entity.entity)"
                                                            :entity-icon-name="getEntityIconName(entity.entity)"
                                                        />

                                                        <ct-block name="sw_search_bar_results_list_bar_item">
                                                            <li
                                                                v-if="entity.entity !== 'module'"
                                                                class="ct-search-bar-item ct-search-bar-item--v2"
                                                            >
                                                                <ct-block name="sw_search_bar_results_list_bar_item_icon">
                                                                    <mt-icon
                                                                        name="regular-double-chevron-right-s"
                                                                        color="rgb(179, 191, 204)"
                                                                    />
                                                                </ct-block>

                                                                <ct-block
                                                                    name="sw_search_bar_results_list_bar_item_more_results"
                                                                >
                                                                    <ct-search-more-results
                                                                        :entity="entity.entity"
                                                                        :term="searchTerm"
                                                                    />
                                                                </ct-block>
                                                            </li>
                                                        </ct-block>
                                                    </ul>
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </template>

                                    <ct-block name="sw_search_bar_results_empty">
                                        <template v-if="isLoading || !isResultEmpty()"
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <div
                                            v-else
                                            class="ct-search-bar__results-empty-message ct-search-bar__results-empty-message--v2"
                                        >
                                            <ct-block name="sw_search_bar_results_empty_content">
                                                <ct-block name="sw_search_bar_results_empty_text">
                                                    <div class="ct-search-bar__results-empty-text">
                                                        {{
                                                            $t(
                                                                'global.ct-search-bar.messageNoResultsV2',
                                                                { term: searchTerm },
                                                                0,
                                                            )
                                                        }}
                                                    </div>
                                                </ct-block>

                                                <ct-block name="sw_search_bar_results_empty_detail">
                                                    <div class="ct-search-bar__results-empty-detail">
                                                        {{ $t('global.ct-search-bar.messageNoResultsDetailV2') }}
                                                    </div>
                                                </ct-block>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="sw_search_bar_results_footer">
                                <div class="ct-search-bar__footer">
                                    <mt-icon
                                        name="regular-cog"
                                        class="ct-search-bar__footer-action-setting"
                                        color="var(--color-icon-primary-default)"
                                        size="var(--scale-size-16)"
                                        @click="toggleSearchPreferencesModal"
                                    />
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_search_bar_types_container">
                        <div v-if="showTypeSelectContainer" class="ct-search-bar__types_container--v2">
                            <ct-block name="sw_search_bar_types_container_header">
                                <div class="ct-search-bar__header">
                                    <p class="ct-search-bar__header-title">
                                        {{ $t('global.ct-search-bar.moduleFiltersHeadline') }}
                                    </p>
                                </div>
                            </ct-block>
                            <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events vuejs-accessibility/mouse-events-have-key-events -->
                            <div
                                v-for="(type, index) in typeSelectResults"
                                :key="index"
                                class="ct-search-bar__type-item"
                                :class="{ 'is--active': activeTypeListIndex === index }"
                                role="row"
                                tabindex="0"
                                @mouseenter="onMouseEnterSearchType(index)"
                                @keydown.enter.prevent="onClickType(type.entityName)"
                                @click="onClickType(type.entityName)"
                            >
                                <span class="ct-search-bar__type-item-name">
                                    <mt-icon
                                        class="ct-search-bar__type-item-icon"
                                        size="12px"
                                        :style="{ color: getEntityIconColor(type.entityName) }"
                                        :name="type.entityName ? getEntityIcon(type.entityName) : 'regular-circle'"
                                    />

                                    {{
                                        type.entityName
                                            ? getLabelSearchType(type.entityName)
                                            : $t('global.ct-search-bar.searchTypeAll')
                                    }}
                                </span>

                                <p class="ct-search-bar__type--filter">
                                    {{ $t('global.ct-search-bar.moduleFilter') }}
                                </p>
                            </div>

                            <ct-block name="sw_search_bar_types_container_empty">
                                <div v-if="typeSelectResults.length < 1" class="ct-search-bar__type-results-empty-message">
                                    <ct-block name="sw_search_bar_types_container_empty_text">
                                        {{ $t('global.ct-search-bar.messageNoTypeResults') }}
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="sw_search_bar_types_container_footer">
                                <div class="ct-search-bar__footer">
                                    <mt-icon
                                        name="regular-cog"
                                        class="ct-search-bar__footer-action-setting"
                                        color="var(--color-icon-primary-default)"
                                        size="var(--scale-size-16)"
                                        @click="toggleSearchPreferencesModal"
                                    />
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_search_bar_types_module_filters_container">
                        <div
                            v-if="showModuleFiltersContainer"
                            class="ct-search-bar__types_module-filters-container ct-search-bar__types_container--v2"
                        >
                            <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events vuejs-accessibility/mouse-events-have-key-events -->
                            <div
                                v-for="(type, index) in typeSelectResults"
                                :key="index"
                                class="ct-search-bar__type-item"
                                :class="{ 'is--active': activeTypeListIndex === index }"
                                role="row"
                                tabindex="0"
                                @mouseenter="onMouseEnterSearchType(index)"
                                @keydown.enter.prevent="onClickType(type.entityName)"
                                @click="onClickType(type.entityName)"
                            >
                                <span class="ct-search-bar__type-item-name">
                                    <mt-icon
                                        class="ct-search-bar__type-item-icon"
                                        size="14px"
                                        :style="{ color: getEntityIconColor(type.entityName) }"
                                        :name="type.entityName ? getEntityIcon(type.entityName) : 'regular-circle'"
                                    />
                                    {{
                                        type.entityName
                                            ? getLabelSearchType(type.entityName)
                                            : $t('global.ct-search-bar.searchTypeAll')
                                    }}
                                </span>
                            </div>

                            <ct-block name="sw_search_bar_types_module_filters_container_empty">
                                <div v-if="typeSelectResults.length < 1" class="ct-search-bar__type-results-empty-message">
                                    <ct-block name="sw_search_bar_types_module_filters_container_empty_text">
                                        {{ $t('global.ct-search-bar.messageNoTypeResults') }}
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="sw_search_bar_types_module_filters_container_footer">
                                <div class="ct-search-bar__footer"></div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_search_bar_trends_results">
                        <div
                            v-if="showResultsSearchTrends && !showResultsContainer"
                            class="ct-search-bar__results ct-search-bar__results--v2"
                        >
                            <ct-block name="sw_search_bar_trends_results_content">
                                <div class="ct-search-bar__results-wrapper-content">
                                    <template v-for="(entity, column) in resultsSearchTrends" :key="entity.entity">
                                        <ct-block name="sw_search_bar_trends_results_list">
                                            <div class="ct-search-bar__results-column">
                                                <ct-block name="sw_search_bar_trends_results_list_column">
                                                    <ct-block name="sw_search_bar_trends_results_list_column_header">
                                                        <div class="ct-search-bar__results-column-header">
                                                            <ct-block
                                                                name="sw_search_bar_trends_results_list_column_header_title"
                                                            >
                                                                <span class="ct-search-bar__types-header-entity">
                                                                    {{
                                                                        $t(`global.entities.${entity.entity}`, entity.total)
                                                                    }}
                                                                </span>
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>
                                                    <ul class="ct-search-bar__results-list">
                                                        <ct-search-bar-item
                                                            v-for="(item, index) in entity.entities"
                                                            :key="index"
                                                            :item="entity.entity === 'frequently_used' ? item : item.item"
                                                            :type="
                                                                entity.entity === 'frequently_used'
                                                                    ? entity.entity
                                                                    : item.entity
                                                            "
                                                            :search-term="searchTerm"
                                                            :column="column"
                                                            :index="index"
                                                            :entity-icon-color="getEntityIconColor(item.entity)"
                                                            :entity-icon-name="getEntityIconName(item.entity)"
                                                        />
                                                    </ul>
                                                </ct-block>
                                            </div>
                                        </ct-block>
                                    </template>
                                </div>
                            </ct-block>

                            <ct-block name="sw_search_bar_trends_results_empty_content"></ct-block>

                            <ct-block name="sw_search_bar_trends_results_footer">
                                <div class="ct-search-bar__footer">
                                    <mt-icon
                                        name="regular-cog"
                                        class="ct-search-bar__footer-action-setting"
                                        color="var(--color-icon-primary-default)"
                                        size="var(--scale-size-16)"
                                        @click="toggleSearchPreferencesModal"
                                    />
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_search_bar_search_preferences_modal">
                <ct-search-preferences-modal v-if="showSearchPreferencesModal" @modal-close="toggleSearchPreferencesModal" />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-search-bar.scss';
const { Application } = Contena;
const { Criteria } = Contena.Data;
const utils = Contena.Utils;
const { cloneDeep } = utils.object;

const props = defineProps({
    /**
     * Determines if the initial search entity, e.g. for a search only in media, when entering its list
     */
    initialSearchType: {
        type: String,
        required: false,
        default: '',
    },
    /**
     * Forbids to search outside the defined search entity
     */
    typeSearchAlwaysInContainer: {
        type: Boolean,
        required: false,
        default: false,
    },
    /**
     * Search bar placeholder
     */
    placeholder: {
        type: String,
        required: false,
        default: '',
    },
    /**
     * Preset search term
     */
    initialSearch: {
        type: String,
        required: false,
        default: '',
    },
    /**
     * Keeps module-local search fields independent from the global route term.
     */
    ignoreRouteTerm: {
        type: Boolean,
        required: false,
        default: false,
    },
    /**
     * Color of the entity tag in the search bar
     */
    entitySearchColor: {
        type: String,
        required: false,
        default: '',
    },
});
const emit = defineEmits([
    'search',
    'active-item-index-select',
    'keyup-enter',
]);

import { ref, computed, inject, watch, nextTick, provide, getCurrentInstance, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const $route = useRoute();
// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();

const searchInput = ref(null);
const resultsContainer = ref(null);

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const searchService = inject('searchService');
const searchTypeService = inject('searchTypeService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const searchRankingService = inject('searchRankingService');
const userActivityApiService = inject('userActivityApiService');
const recentlySearchService = inject('recentlySearchService');

const currentSearchType = ref(props.initialSearchType);
const showResultsContainer = ref(false);
const showModuleFiltersContainer = ref(false);
const searchTerm = ref(props.initialSearch);
const results = ref([]);
const isActive = ref(false);
const isOffCanvasShown = ref(false);
const isSearchBarShown = ref(false);
const activeResultIndex = ref(0);
const activeResultColumn = ref(0);
const activeTypeListIndex = ref(0);
const isLoading = ref(false);
const searchTypes = ref(null);
const showTypeSelectContainer = ref(false);
const typeSelectResults = ref([]);
const moduleFactory = ref(Application.getContainer('factory').module || {});
const showResultsSearchTrends = ref(false);
const resultsSearchTrends = ref([]);
const showSearchPreferencesModal = ref(false);
const searchLimit = ref(10);
const userSearchPreference = ref(null);
const isComponentMounted = ref(true);
const activeItemIndexSelectHandler = ref([]);
const keyupEnterHandler = ref([]);
let searchTrendsRequestId = 0;

const searchBarFieldClasses = computed(() => {
    return {
        'is--active': isActive.value,
    };
});
const placeholderSearchInput = computed(() => {
    let placeholder = t('global.ct-search-bar.placeholderSearchField');

    if (currentSearchType.value) {
        if (props.placeholder !== '') {
            placeholder = props.placeholder;
        } else if (Object.keys(searchTypes.value).includes(currentSearchType.value)) {
            placeholder = t(searchTypes.value[currentSearchType.value].placeholderSnippet);
        }
    }

    return placeholder;
});
const moduleRegistry = computed(() => {
    return moduleFactory.value.getModuleRegistry();
});
const searchableModules = computed(() => {
    const modules = [];

    moduleRegistry.value.forEach((module) => {
        const privilege = module.manifest.routes?.index?.meta?.privilege;

        if (!module.manifest?.title || (privilege && !acl.can(privilege))) {
            return;
        }

        modules.push(module);
    });

    modules.sort((a, b) => a.manifest.name?.localeCompare(b.manifest.name));

    return modules;
});
const criteriaCollection = computed(() => {
    return {};
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});

const clearSearchTerm = () => {
    searchTrendsRequestId += 1;
    showResultsContainer.value = false;
    showResultsSearchTrends.value = false;
    activeResultIndex.value = 0;
    activeResultColumn.value = 0;
};
const closeOnClickOutside = (event) => {
    if (!event.target.closest('.ct-search-bar')) {
        isActive.value = false;
        clearSearchTerm();
        showTypeSelectContainer.value = false;
        showModuleFiltersContainer.value = false;
    }
};
const showSearchFieldOnLargerViewports = () => {
    if ((device?.getViewportWidth() ?? Number.POSITIVE_INFINITY) > 500) {
        isSearchBarShown.value = true;
    }
};
const registerListener = () => {
    document.addEventListener('click', closeOnClickOutside);
    Contena.Utils.EventBus.on('ct-admin-menu/toggle-offcanvas', onOffCanvasToggle);
};
const destroyedComponent = () => {
    document.removeEventListener('click', closeOnClickOutside);
    Contena.Utils.EventBus.off('ct-admin-menu/toggle-offcanvas', onOffCanvasToggle);
    device?.removeResizeListener(instance?.proxy);
};
const createdComponent = async () => {
    showSearchFieldOnLargerViewports();
    device?.onResize({
        listener: showSearchFieldOnLargerViewports,
        component: instance?.proxy,
    });

    if ($route.query.term && !props.ignoreRouteTerm) {
        searchTerm.value = $route.query.term;
    }

    searchTypes.value = searchTypeService.getTypes();
    typeSelectResults.value = Object.values(searchTypes.value).filter((searchType) => !searchType.hideOnGlobalSearchBar);
    registerListener();
    userSearchPreference.value = await searchRankingService.getUserSearchPreference();
};
const onMouseOver = (index, column) => {
    setActiveResultPosition({ index, column });
};
const registerActiveItemIndexSelectHandler = (handler) => {
    activeItemIndexSelectHandler.value.push(handler);
};
const unregisterActiveItemIndexSelectHandler = (handler) => {
    activeItemIndexSelectHandler.value = activeItemIndexSelectHandler.value.filter((h) => h !== handler);
};
const registerKeyupEnterHandler = (handler) => {
    keyupEnterHandler.value.push(handler);
};
const unregisterKeyupEnterHandler = (handler) => {
    keyupEnterHandler.value = keyupEnterHandler.value.filter((h) => h !== handler);
};
const getLabelSearchType = (type) => {
    if (!type && !currentSearchType.value) {
        type = 'all';
    }

    if (!type && currentSearchType.value) {
        type = currentSearchType.value;
    }

    if (type.startsWith('custom_entity_') || type.startsWith('ce_')) {
        const snippetKey = `${type}.moduleTitle`;
        return te(snippetKey) ? t(snippetKey) : type;
    }

    if (!te(`global.entities.${type}`)) {
        return currentSearchType.value;
    }

    return t(`global.entities.${type}`, 2);
};
const getSearchInputElement = () => searchInput.value?.$el?.querySelector('input') || searchInput.value;
const setFocus = () => {
    getSearchInputElement()?.focus();
};
const onSearchTermUpdate = (value) => {
    searchTerm.value = value;
    onSearchTermChange();
};
const onFocusInput = () => {
    isActive.value = true;
    const requestId = ++searchTrendsRequestId;

    if (searchTerm.value === '#') {
        showTypeContainer();
    }

    if (resultsSearchTrends.value?.length) {
        showModuleFiltersContainer.value = false;
        showResultsSearchTrends.value = true;
        return;
    }

    void loadSearchTrends().then((response) => {
        if (!isActive.value || requestId !== searchTrendsRequestId) {
            return;
        }

        resultsSearchTrends.value = response;

        showResultsSearchTrends.value = !!response?.length;
    });
};
const onBlur = () => {
    isActive.value = false;
    searchTrendsRequestId += 1;
};
const showSearchBar = () => {
    isSearchBarShown.value = true;
    isActive.value = true;
    isOffCanvasShown.value = false;

    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', isOffCanvasShown.value);
};
const hideSearchBar = () => {
    isSearchBarShown.value = false;
    isActive.value = false;
    showResultsContainer.value = false;
};
const onSearchTermChange = () => {
    const match = searchTerm.value.match(/^#(.*)/);
    if (match !== null) {
        showTypeContainer();
        filterTypeSelectResults(match[1]);

        return;
    }

    if (searchTerm.value.trim().length > 155) {
        return;
    }

    showTypeSelectContainer.value = false;
    showResultsSearchTrends.value = false;

    if (props.typeSearchAlwaysInContainer && currentSearchType.value && searchTypes.value[currentSearchType.value]) {
        doListSearchWithContainer();
        return;
    }

    if (!props.initialSearchType && currentSearchType.value) {
        doListSearchWithContainer();
        return;
    }

    if (props.initialSearchType && currentSearchType.value && props.initialSearchType !== currentSearchType.value) {
        doListSearchWithContainer();
        return;
    }

    if (currentSearchType.value) {
        doListSearch();
        return;
    }

    doGlobalSearch();
};
function showTypeContainer() {
    showTypeSelectContainer.value = true;
    showModuleFiltersContainer.value = false;
    showResultsContainer.value = false;
    showResultsSearchTrends.value = false;
    activeTypeListIndex.value = 0;
}
function filterTypeSelectResults(term) {
    typeSelectResults.value = [];
    Object.keys(searchTypes.value).forEach((key) => {
        const snippet = t(`global.entities.${searchTypes.value[key].entityName}`, 2);
        if (snippet.toLowerCase().includes(term.toLowerCase()) || term === '') {
            typeSelectResults.value.push(searchTypes.value[key]);
        }
    });
}
const onClickType = (type) => {
    setSearchType(type);
    setFocus();
};
function setSearchType(type) {
    const searchTermValue = searchTerm.value.startsWith('#') ? '' : searchTerm.value;
    currentSearchType.value = type;
    showTypeSelectContainer.value = false;
    showModuleFiltersContainer.value = false;
    showResultsSearchTrends.value = false;
    searchTerm.value = searchTermValue;
}
const toggleOffCanvas = () => {
    isOffCanvasShown.value = !isOffCanvasShown.value;

    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', isOffCanvasShown.value);
};
function onOffCanvasToggle(state) {
    isOffCanvasShown.value = state;
}
const resetSearchType = () => {
    if (searchTerm.value.length === 0) {
        isComponentMounted.value = false;
        currentSearchType.value = null;
    }
};
const doListSearch = utils.debounce(() => {
    const searchTermValue = searchTerm.value.trim();
    emit('search', searchTermValue);
}, 750);
const doListSearchWithContainer = utils.debounce(() => {
    const searchTermValue = searchTerm.value.trim();

    if (searchTermValue && searchTermValue.length > 0) {
        void loadTypeSearchResults(searchTermValue);
    } else {
        showResultsContainer.value = false;
    }
}, 750);
const doGlobalSearch = utils.debounce(() => {
    const searchTermValue = searchTerm.value.trim();
    if (searchTermValue && searchTermValue.length > 0) {
        void loadResults(searchTermValue);
    } else {
        showResultsContainer.value = false;
        showResultsSearchTrends.value = false;
    }
}, 750);
async function loadResults(searchTerm) {
    isLoading.value = true;
    results.value = [];
    const entities = getModuleEntities(searchTerm);
    if (entities?.length) {
        results.value.unshift({
            entity: 'module',
            total: entities.length,
            entities,
        });
    }
    if (!userSearchPreference.value || Object.keys(userSearchPreference.value).length < 1) {
        activeResultColumn.value = 0;
        activeResultIndex.value = 0;
        isLoading.value = false;
        if (!showTypeSelectContainer.value) {
            showResultsContainer.value = true;
        }
        return;
    }
    const queries = searchRankingService.buildGlobalSearchQueries(
        userSearchPreference.value,
        searchTerm,
        criteriaCollection.value,
        searchLimit.value + 1,
        0,
    );
    const response = await searchService.searchQuery(queries, {
        'ct-inheritance': true,
    });
    const data = response.data;
    if (!data) {
        return;
    }
    Object.keys(data).forEach((entity) => {
        if (data[entity].total > 0) {
            const item = data[entity];
            item.entities = Object.values(item.data).slice(0, searchLimit.value);
            item.entity = entity;
            results.value = results.value.filter((result) => entity !== result.entity);
            results.value = [
                ...results.value,
                item,
            ];
        }
    });
    activeResultColumn.value = 0;
    activeResultIndex.value = 0;
    isLoading.value = false;
    if (!showTypeSelectContainer.value) {
        showResultsContainer.value = true;
    }
}
async function loadTypeSearchResults(searchTerm) {
    // If searchType has an "entityService" load by service, otherwise load by entity
    if (searchTypes.value[currentSearchType.value]?.entityService) {
        loadTypeSearchResultsByService(searchTerm);
        return;
    }
    isLoading.value = true;
    results.value = [];
    const entityResults = {
        entity: currentSearchType.value,
        total: 0,
    };
    const entityName = searchTypes.value[currentSearchType.value].entityName;
    const repository = repositoryFactory.create(entityName);
    let criteria = criteriaCollection.value.hasOwnProperty(entityName)
        ? criteriaCollection.value[entityName]
        : new Criteria(1, searchLimit.value + 1);
    criteria.setTerm(searchTerm);
    // Set limit as `searchLimit + 1` to check if more than `searchLimit` results are returned
    criteria.setLimit(searchLimit.value + 1);
    criteria.setTotalCountMode(0);
    const searchRankingFields = await searchRankingService.getSearchFieldsByEntity(entityName);
    if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
        entityResults.total = 0;
        results.value.push(entityResults);
        isLoading.value = false;
        if (!showTypeSelectContainer.value) {
            showResultsContainer.value = true;
        }
        return;
    }
    criteria = searchRankingService.buildSearchQueriesForEntity(searchRankingFields, searchTerm, criteria);
    const response = await repository.search(criteria, {
        ...Contena.Context.api,
        inheritance: true,
    });
    entityResults.total = response.total;
    entityResults.entities = response.slice(0, searchLimit.value);
    if (entityResults.total > 0) {
        results.value = results.value.filter((result) => currentSearchType.value !== result.entity);
        results.value = [
            ...results.value,
            entityResults,
        ];
    }
    isLoading.value = false;
    if (!showTypeSelectContainer.value) {
        showResultsContainer.value = true;
    }
}
function loadTypeSearchResultsByService(searchTerm) {
    isLoading.value = true;
    const params = {
        limit: 25,
        term: searchTerm,
    };
    results.value = [];
    const entityResults = {};
    const apiServiceName = searchTypes.value[currentSearchType.value].entityService;
    if (!Application.getContainer('factory').apiService.has(apiServiceName)) {
        throw new Error(`ct-search-bar - Api service ${apiServiceName} not found`);
    }
    const apiService = Application.getContainer('factory').apiService.getByName(apiServiceName);
    apiService.getList(params).then((response) => {
        entityResults.total = response.meta.total;
        entityResults.entity = currentSearchType.value;
        entityResults.entities = response.data;
        results.value.push(entityResults);
        isLoading.value = false;
    });
    if (!showTypeSelectContainer.value) {
        showResultsContainer.value = true;
    }
}
function setActiveResultPosition({ index, column }) {
    activeResultIndex.value = index;
    activeResultColumn.value = column;
    emitActiveResultPosition();
}
function emitActiveResultPosition() {
    emit('active-item-index-select', {
        index: activeResultIndex.value,
        column: activeResultColumn.value,
    });
    activeItemIndexSelectHandler.value.forEach((callback) =>
        callback({
            index: activeResultIndex.value,
            column: activeResultColumn.value,
        }),
    );
}
const navigateUpResults = () => {
    if (showTypeSelectContainer.value) {
        if (activeTypeListIndex.value !== 0) {
            activeTypeListIndex.value -= 1;
        }
    }

    if (!showResultsContainer.value) {
        return;
    }

    if (activeResultIndex.value === 0) {
        if (activeResultColumn.value > 0) {
            activeResultColumn.value -= 1;
            const itemsInNewColumn = Object.keys(results.value[activeResultColumn.value].entities).length;
            activeResultIndex.value = itemsInNewColumn - 1;
        }
    } else {
        activeResultIndex.value -= 1;
    }

    setActiveResultPosition({
        index: activeResultIndex.value,
        column: activeResultColumn.value,
    });
    checkScrollPosition();
};
const navigateDownResults = () => {
    if (showTypeSelectContainer.value) {
        if (activeTypeListIndex.value !== typeSelectResults.value.length - 1) {
            activeTypeListIndex.value += 1;
        }
    }

    if (!showResultsContainer.value) {
        return;
    }

    const itemsInActualColumn = results.value[activeResultColumn.value].entities.length;

    if (activeResultIndex.value === itemsInActualColumn - 1 || itemsInActualColumn < 1) {
        if (activeResultColumn.value < results.value.length - 1) {
            // Move to the next column if it exists
            if (results.value[activeResultColumn.value + 1]) {
                activeResultColumn.value += 1;
                activeResultIndex.value = 0;
            } else {
                return;
            }
        }
    } else {
        activeResultIndex.value += 1;
    }
    setActiveResultPosition({
        index: activeResultIndex.value,
        column: activeResultColumn.value,
    });
    checkScrollPosition();
};
function checkScrollPosition() {
    void nextTick(() => {
        const resultsContainerValue = resultsContainer.value;
        const activeItem = resultsContainerValue.querySelector('.is--active');
        const itemHeight = resultsContainerValue.querySelector('.ct-search-bar-item').offsetHeight;
        const resultContainerHeight = resultsContainerValue.offsetHeight;
        const activeItemPosition = activeItem.offsetTop + itemHeight;
        if (activeItemPosition + itemHeight * 2 > resultContainerHeight + resultsContainerValue.scrollTop) {
            resultsContainerValue.scrollTop = activeItemPosition + itemHeight * 2 - resultContainerHeight;
        } else if (activeItemPosition - itemHeight * 3 < resultsContainerValue.scrollTop) {
            resultsContainerValue.scrollTop = activeItemPosition - itemHeight * 3;
        }
    });
}
const onKeyUpEnter = () => {
    emit('keyup-enter', activeResultIndex.value, activeResultColumn.value);

    keyupEnterHandler.value.forEach((callback) => callback(activeResultIndex.value, activeResultColumn.value));

    if (showTypeSelectContainer.value) {
        if (typeSelectResults.value.length > 0) {
            setSearchType(typeSelectResults.value[activeTypeListIndex.value].entityName);
        }
    }
};
const getSearchTypeProperty = (entityName, propertyName) => {
    if (!searchTypes.value[entityName] || !searchTypes.value[entityName].hasOwnProperty(propertyName)) {
        return '';
    }
    return searchTypes.value[entityName][propertyName];
};
const getEntityIconName = (entityName) => {
    const module = moduleFactory.value.getModuleByEntityName(entityName);

    return module?.manifest?.icon ?? 'regular-books';
};
const getEntityIconColor = (entityName) => {
    if (props.entitySearchColor !== '') {
        return props.entitySearchColor;
    }

    const module = moduleFactory.value.getModuleByEntityName(entityName);

    if (!module) {
        return '#5C738A';
    }

    return module.manifest.color || '#5C738A';
};
const getEntityIcon = (entityName) => {
    const module = moduleFactory.value.getModuleByEntityName(entityName);

    return module?.manifest?.icon ?? 'regular-books';
};
const isResultEmpty = () => {
    return !results.value.some((result) => result.total !== 0);
};
const onMouseEnterSearchType = (index) => {
    activeTypeListIndex.value = index;
};
const onOpenModuleFiltersDropDown = () => {
    isActive.value = true;
    showModuleFiltersContainer.value = true;
    showTypeSelectContainer.value = false;
    showResultsSearchTrends.value = false;
};
function getModuleEntities(searchTerm, limit = 5) {
    const minSearch = 3;
    const regex = new RegExp(`^${searchTerm.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&').toLowerCase()}(.*)`);
    if (!searchTerm || searchTerm.length < minSearch) {
        return [];
    }
    let moduleEntities = [];
    searchableModules.value.forEach((module) => {
        const matcher =
            typeof module.manifest.searchMatcher === 'function'
                ? module.manifest.searchMatcher
                : getDefaultMatchSearchableModules;
        const moduleType = te(`${module.manifest.title}`) && t(`${module.manifest.title}`, 2);
        if (!moduleType) {
            return;
        }
        const matches = matcher(regex, moduleType, module.manifest);
        if (!matches || matches.length === 0) {
            return;
        }
        moduleEntities.push(...matches.filter((item) => !item.privilege || acl.can(item.privilege)));
    });
    moduleEntities = moduleEntities.filter((item) => item?.entity);
    return moduleEntities.slice(0, limit);
}
function getDefaultMatchSearchableModules(regex, label, manifest) {
    const match = label.toLowerCase().match(regex);
    const matchAddNew = `${t('global.ct-search-bar.addNew')} ${label}`.toLowerCase().match(regex);
    if ((!match && !matchAddNew) || (!manifest?.routes?.index && !manifest?.routes?.list)) {
        return false;
    }
    const route = manifest?.routes?.index || manifest?.routes?.list;
    const { name, icon, color, entity, routes } = manifest;
    const entities = [];
    if (match && routes.index) {
        entities.push({
            name,
            icon,
            color,
            label,
            entity,
            route: route,
            privilege: routes.index?.meta?.privilege,
        });
    }
    if (routes.create) {
        entities.push({
            name,
            icon,
            color,
            entity,
            route: routes.create,
            privilege: routes.create?.meta?.privilege,
            action: true,
        });
    }
    return entities;
}
const toggleSearchPreferencesModal = () => {
    showSearchPreferencesModal.value = !showSearchPreferencesModal.value;

    // Clear search term, turn off search results
    searchTerm.value = null;
    showResultsContainer.value = false;
    showTypeSelectContainer.value = false;
    showResultsSearchTrends.value = false;
};
function loadSearchTrends() {
    return Promise.all([
        getFrequentlyUsedModules(),
        getRecentlySearch(),
    ]).then((response) => response.filter((item) => item?.total));
}
async function getFrequentlyUsedModules(checkNonExistentKeys = true) {
    try {
        const initialResponse = await userActivityApiService.getIncrement({
            cluster: currentUser.value.id,
        });
        const initialKeys = Object.keys(initialResponse || {});
        const initialModuleProcessingResults = initialKeys.map((key) => {
            return {
                key,
                info: getInfoModuleFrequentlyUsed(key),
            };
        });
        const nonExistentKeys = checkNonExistentKeys
            ? initialModuleProcessingResults.filter((item) => Object.keys(item.info).length === 0).map((item) => item.key)
            : [];
        const validInitialModules = initialModuleProcessingResults
            .filter((item) => Object.keys(item.info).length > 0)
            .map((item) => item.info);
        if (nonExistentKeys.length > 0) {
            try {
                await userActivityApiService.deleteActivityKeys({
                    keys: nonExistentKeys,
                    cluster: currentUser.value.id,
                });
                return await getFrequentlyUsedModules(false);
            } catch {
                // In case deleting keys or fetching fresh data fails, fallback to initially valid modules
                return {
                    entity: 'frequently_used',
                    total: validInitialModules.length,
                    entities: validInitialModules,
                };
            }
        }
        return {
            entity: 'frequently_used',
            total: validInitialModules.length,
            entities: validInitialModules,
        };
    } catch (_error) {
        return {
            entity: 'frequently_used',
            total: 0,
            entities: [],
        };
    }
}
function getRecentlySearch() {
    return new Promise((resolve) => {
        const items = recentlySearchService.get(currentUser.value.id);
        const queries = {};
        items.forEach((item) => {
            if (!acl.can(`${item.entity}:read`)) {
                return;
            }
            if (!queries.hasOwnProperty(item.entity)) {
                queries[item.entity] = criteriaCollection.value.hasOwnProperty(item.entity)
                    ? cloneDeep(criteriaCollection.value[item.entity])
                    : new Criteria(1, 25);
            }
            const ids = [
                item.id,
                ...queries[item.entity].ids,
            ];
            queries[item.entity].setIds(ids);
        });
        if (Object.keys(queries).length === 0) {
            resolve();
            return;
        }
        searchService
            .searchQuery(queries, {
                'ct-inheritance': true,
            })
            .then((searchResult) => {
                if (!searchResult.data) {
                    resolve();
                    return;
                }
                const mapResult = [];
                items.forEach((item) => {
                    const entities = searchResult.data[item.entity] ? searchResult.data[item.entity].data : {};
                    const foundEntity = entities[item.id];
                    if (foundEntity) {
                        mapResult.push({
                            item: foundEntity,
                            entity: item.entity,
                        });
                    }
                });
                resolve({
                    entity: 'recently_searched',
                    total: mapResult.length,
                    entities: mapResult,
                });
            });
    });
}
function getInfoModuleFrequentlyUsed(key) {
    const [
        moduleName,
        routeName,
    ] = key.split('@');
    const module = moduleFactory.value.getModuleByKey('name', moduleName);
    if (!module) {
        return {};
    }
    const { routes, ...manifest } = module.manifest;
    if (typeof manifest.searchMatcher === 'function') {
        // get metadata in searchMatcher
        const metadata = manifest.searchMatcher(
            new RegExp(`^${t(manifest.title).toLowerCase()}(.*)`),
            t(manifest.title, 2),
            module.manifest,
        );
        return metadata.find((item) => item.route.name === routeName) ?? {};
    }
    const route = Object.values(routes).find((item) => item.name === routeName);
    if (!route) {
        return {};
    }
    return {
        ...manifest,
        route,
        privilege: route?.meta?.privilege,
        action: route.routeKey === 'create',
    };
}

const onRouteChange = (newValue) => {
    if (props.ignoreRouteTerm) {
        searchTerm.value = '';
        return;
    }

    // Use type search again when route changes and the term is undefined
    if (isComponentMounted.value === true && newValue.query.term === undefined && props.initialSearchType) {
        currentSearchType.value = props.initialSearchType;
    }

    // Do not modify the search term when the user is currently typing
    if (isActive.value || newValue.query.term === undefined) {
        return;
    }

    searchTerm.value = newValue.query.term || '';
};

watch(() => ({ ...$route, params: { ...$route.params }, query: { ...$route.query } }), onRouteChange);
watch(
    () => $route.name,
    (to, from) => {
        if (from === undefined || to === from) {
            return;
        }

        resultsSearchTrends.value = [];
    },
    { immediate: true },
);

provide('searchBarOnMouseOver', onMouseOver);
provide('searchBarRegisterActiveItemIndexSelectHandler', registerActiveItemIndexSelectHandler);
provide('searchBarUnregisterActiveItemIndexSelectHandler', unregisterActiveItemIndexSelectHandler);
provide('searchBarRegisterKeyupEnterHandler', registerKeyupEnterHandler);
provide('searchBarUnregisterKeyupEnterHandler', unregisterKeyupEnterHandler);

void createdComponent();
onBeforeUnmount(destroyedComponent);

swDefinePublic({
    searchService,
    searchTypeService,
    repositoryFactory,
    acl,
    searchRankingService,
    userActivityApiService,
    recentlySearchService,
    currentSearchType,
    showResultsContainer,
    showModuleFiltersContainer,
    searchTerm,
    results,
    isActive,
    isOffCanvasShown,
    isSearchBarShown,
    activeResultIndex,
    activeResultColumn,
    activeTypeListIndex,
    isLoading,
    searchTypes,
    showTypeSelectContainer,
    typeSelectResults,
    moduleFactory,
    showResultsSearchTrends,
    resultsSearchTrends,
    showSearchPreferencesModal,
    searchLimit,
    userSearchPreference,
    isComponentMounted,
    activeItemIndexSelectHandler,
    keyupEnterHandler,
    searchBarFieldClasses,
    placeholderSearchInput,
    moduleRegistry,
    searchableModules,
    criteriaCollection,
    currentUser,
    createdComponent,
    destroyedComponent,
    registerListener,
    closeOnClickOutside,
    clearSearchTerm,
    showSearchFieldOnLargerViewports,
    onMouseOver,
    registerActiveItemIndexSelectHandler,
    unregisterActiveItemIndexSelectHandler,
    registerKeyupEnterHandler,
    unregisterKeyupEnterHandler,
    getLabelSearchType,
    setFocus,
    onFocusInput,
    onBlur,
    showSearchBar,
    hideSearchBar,
    onSearchTermChange,
    showTypeContainer,
    filterTypeSelectResults,
    onClickType,
    setSearchType,
    toggleOffCanvas,
    onOffCanvasToggle,
    resetSearchType,
    doListSearch,
    doListSearchWithContainer,
    doGlobalSearch,
    loadResults,
    loadTypeSearchResults,
    loadTypeSearchResultsByService,
    setActiveResultPosition,
    emitActiveResultPosition,
    navigateUpResults,
    navigateDownResults,
    checkScrollPosition,
    onKeyUpEnter,
    getSearchTypeProperty,
    getEntityIconName,
    getEntityIconColor,
    getEntityIcon,
    isResultEmpty,
    onMouseEnterSearchType,
    onOpenModuleFiltersDropDown,
    getModuleEntities,
    getDefaultMatchSearchableModules,
    toggleSearchPreferencesModal,
    loadSearchTrends,
    getFrequentlyUsedModules,
    getRecentlySearch,
    getInfoModuleFrequentlyUsed,
    onRouteChange,
});

defineExpose({
    searchService,
    searchTypeService,
    repositoryFactory,
    acl,
    searchRankingService,
    userActivityApiService,
    recentlySearchService,
    currentSearchType,
    showResultsContainer,
    showModuleFiltersContainer,
    searchTerm,
    results,
    isActive,
    isOffCanvasShown,
    isSearchBarShown,
    activeResultIndex,
    activeResultColumn,
    activeTypeListIndex,
    isLoading,
    searchTypes,
    showTypeSelectContainer,
    typeSelectResults,
    moduleFactory,
    showResultsSearchTrends,
    resultsSearchTrends,
    showSearchPreferencesModal,
    searchLimit,
    userSearchPreference,
    isComponentMounted,
    activeItemIndexSelectHandler,
    keyupEnterHandler,
    searchBarFieldClasses,
    placeholderSearchInput,
    moduleRegistry,
    searchableModules,
    criteriaCollection,
    currentUser,
    createdComponent,
    destroyedComponent,
    registerListener,
    closeOnClickOutside,
    clearSearchTerm,
    showSearchFieldOnLargerViewports,
    onMouseOver,
    registerActiveItemIndexSelectHandler,
    unregisterActiveItemIndexSelectHandler,
    registerKeyupEnterHandler,
    unregisterKeyupEnterHandler,
    getLabelSearchType,
    setFocus,
    onFocusInput,
    onBlur,
    showSearchBar,
    hideSearchBar,
    onSearchTermChange,
    showTypeContainer,
    filterTypeSelectResults,
    onClickType,
    setSearchType,
    toggleOffCanvas,
    onOffCanvasToggle,
    resetSearchType,
    doListSearch,
    doListSearchWithContainer,
    doGlobalSearch,
    loadResults,
    loadTypeSearchResults,
    loadTypeSearchResultsByService,
    setActiveResultPosition,
    emitActiveResultPosition,
    navigateUpResults,
    navigateDownResults,
    checkScrollPosition,
    onKeyUpEnter,
    getSearchTypeProperty,
    getEntityIconName,
    getEntityIconColor,
    getEntityIcon,
    isResultEmpty,
    onMouseEnterSearchType,
    onOpenModuleFiltersDropDown,
    getModuleEntities,
    getDefaultMatchSearchableModules,
    toggleSearchPreferencesModal,
    loadSearchTrends,
    getFrequentlyUsedModules,
    getRecentlySearch,
    getInfoModuleFrequentlyUsed,
    onRouteChange,
});
</script>
