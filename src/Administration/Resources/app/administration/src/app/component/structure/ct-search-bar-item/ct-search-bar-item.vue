<template>
    <ct-block name="ct_search_bar_item">
        <li class="ct-search-bar-item ct-search-bar-item--v2" :class="componentClasses" @mouseenter="onMouseEnter($event)">
            <ct-block name="ct_search_bar_item_icon">
                <mt-icon v-if="iconName" :name="iconName" :color="iconColor" size="16px" />
            </ct-block>

            <ct-block name="ct_search_bar_item_media">
                <router-link
                    v-if="type === 'media'"
                    ref="routerLinkRef"
                    v-slot="{ href, navigate }"
                    class="ct-search-bar-item__link"
                    :to="{
                        name: 'ct.media.index',
                        params: { folderId: item.mediaFolderId },
                        query: { term: item.fileName },
                    }"
                    custom
                >
                    <a
                        :href="href"
                        class="ct-search-bar-item__link"
                        @click="onClickSearchResult('media', item.id) && navigate($event)"
                    >
                        <span class="ct-search-bar-item__label">
                            <ct-highlight-text :search-term="searchTerm" :text="mediaNameFilter(item)" />
                        </span>
                    </a>
                </router-link>
            </ct-block>

            <ct-block name="ct_search_bar_item_module">
                <template v-if="type === 'media'"><!-- Keeps the conditional chain connected across ct-block. --></template>
                <router-link
                    v-else-if="['frequently_used', 'module'].includes(type)"
                    ref="routerLinkRef"
                    class="ct-search-bar-item__link"
                    :to="routeName"
                >
                    <span class="ct-search-bar-item__label">
                        <ct-highlight-text :search-term="searchTerm" :text="moduleName" />
                        <ct-highlight-text
                            :text="$t(`global.ct-search-bar-item.${item.action ? 'typeLabelAction' : 'typeLabelModule'}`)"
                        />
                    </span>
                </router-link>
            </ct-block>

            <ct-block name="ct_search_bar_item_other_entity">
                <template v-if="type === 'media' || ['frequently_used', 'module'].includes(type)"
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <router-link
                    v-else-if="detailRoute && displayValue.length > 0"
                    ref="routerLinkRef"
                    class="ct-search-bar-item__link"
                    :to="{ name: detailRoute, params: { id: item.id } }"
                >
                    <span class="ct-search-bar-item__label">
                        <ct-highlight-text :search-term="searchTerm" :text="displayValue" />
                    </span>
                </router-link>
            </ct-block>
        </li>
    </ct-block>
</template>

<script setup>
import './ct-search-bar-item.scss';
const { Application } = Contena;

const props = defineProps({
    item: {
        type: Object,
        required: false,
        default: () => ({}),
    },
    type: {
        required: true,
        type: String,
    },
    index: {
        type: Number,
        required: true,
    },
    column: {
        type: Number,
        required: true,
    },
    searchTerm: {
        type: String,
        required: false,
        default: null,
    },
    entityIconColor: {
        type: String,
        required: true,
    },
    entityIconName: {
        type: String,
        required: true,
    },
});

import { ref, computed, inject, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { t } = useI18n();

const routerLinkRef = ref(null);

const searchTypeService = inject('searchTypeService');
const feature = inject('feature');
const recentlySearchService = inject('recentlySearchService');
const searchBarOnMouseOver = inject('searchBarOnMouseOver', null);
const searchBarRegisterActiveItemIndexSelectHandler = inject('searchBarRegisterActiveItemIndexSelectHandler', null);
const searchBarUnregisterActiveItemIndexSelectHandler = inject('searchBarUnregisterActiveItemIndexSelectHandler', null);
const searchBarRegisterKeyupEnterHandler = inject('searchBarRegisterKeyupEnterHandler', null);
const searchBarUnregisterKeyupEnterHandler = inject('searchBarUnregisterKeyupEnterHandler', null);

const isActive = ref(false);

const searchTypes = computed(() => {
    return searchTypeService.getTypes();
});
const moduleManifest = computed(() => {
    const moduleFactory = Application.getContainer('factory').module;

    return moduleFactory.getModuleByEntityName(props.type)?.manifest ?? {};
});
const detailRoute = computed(() => {
    return moduleManifest.value?.routes?.detail?.name;
});
const displayValue = computed(() => {
    if (!moduleManifest.value.hasOwnProperty('entityDisplayProperty')) {
        return props.item.hasOwnProperty('name') ? props.item.name : props.item.id;
    }

    if (!props.item.hasOwnProperty(moduleManifest.value.entityDisplayProperty)) {
        return props.item.hasOwnProperty('name') ? props.item.name : props.item.id;
    }

    return props.item[moduleManifest.value.entityDisplayProperty];
});
const componentClasses = computed(() => {
    return [
        {
            'is--active': isActive.value,
        },
    ];
});
const moduleName = computed(() => {
    const { action, label, entity, title } = props.item;

    if (title && !action) {
        return t(`${title}`, 2);
    }

    return action
        ? t(
              'global.ct-search-bar-item.addNewEntity',
              {
                  entity: label?.toLowerCase() ?? t(`global.entities.${entity}`).toLowerCase(),
              },
              0,
          )
        : label;
});
const routeName = computed(() => {
    return typeof props.item.route === 'object' ? props.item.route : { name: props.item.route };
});
const iconName = computed(() => {
    return [
        'module',
        'frequently_used',
    ].includes(props.type) && props.item?.icon
        ? props.item.icon
        : props.entityIconName;
});
const iconColor = computed(() => {
    return [
        'module',
        'frequently_used',
    ].includes(props.type) && props.item?.color
        ? props.item.color
        : props.entityIconColor;
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const mediaNameFilter = computed(() => {
    return Contena.Filter.getByName('mediaName');
});

const createdComponent = () => {
    registerEvents();

    if (props.index === 0 && props.column === 0) {
        isActive.value = true;
    }
};
const destroyedComponent = () => {
    removeEvents();
};
function registerEvents() {
    searchBarRegisterActiveItemIndexSelectHandler(checkActiveState);
    searchBarRegisterKeyupEnterHandler(onEnter);
}
function removeEvents() {
    searchBarUnregisterActiveItemIndexSelectHandler(checkActiveState);
    searchBarUnregisterKeyupEnterHandler(onEnter);
}
function checkActiveState({ index, column }) {
    if (index === props.index && column === props.column) {
        isActive.value = true;
        return;
    }
    if (isActive.value) {
        isActive.value = false;
    }
}
function onEnter(index, column) {
    if (index !== props.index || column !== props.column) {
        return;
    }
    const link = routerLinkRef.value;
    void router.push(link.to);
}
const onMouseEnter = (originalDomEvent) => {
    searchBarOnMouseOver({
        originalDomEvent,
        index: props.index,
        column: props.column,
    });

    isActive.value = true;
};
const onClickSearchResult = (entity, id, payload = {}) => {
    recentlySearchService.add(currentUser.value.id, entity, id, payload);
};

createdComponent();

onUnmounted(() => {
    destroyedComponent();
});

ctDefinePublic({
    searchTypeService,
    feature,
    recentlySearchService,
    searchBarOnMouseOver,
    searchBarRegisterActiveItemIndexSelectHandler,
    searchBarUnregisterActiveItemIndexSelectHandler,
    searchBarRegisterKeyupEnterHandler,
    searchBarUnregisterKeyupEnterHandler,
    isActive,
    searchTypes,
    moduleManifest,
    detailRoute,
    displayValue,
    componentClasses,
    moduleName,
    routeName,
    iconName,
    iconColor,
    currentUser,
    mediaNameFilter,
    createdComponent,
    destroyedComponent,
    registerEvents,
    removeEvents,
    checkActiveState,
    onEnter,
    onMouseEnter,
    onClickSearchResult,
});

defineExpose({
    searchTypeService,
    feature,
    recentlySearchService,
    searchBarOnMouseOver,
    searchBarRegisterActiveItemIndexSelectHandler,
    searchBarUnregisterActiveItemIndexSelectHandler,
    searchBarRegisterKeyupEnterHandler,
    searchBarUnregisterKeyupEnterHandler,
    isActive,
    searchTypes,
    moduleManifest,
    detailRoute,
    displayValue,
    componentClasses,
    moduleName,
    routeName,
    iconName,
    iconColor,
    currentUser,
    mediaNameFilter,
    createdComponent,
    destroyedComponent,
    registerEvents,
    removeEvents,
    checkActiveState,
    onEnter,
    onMouseEnter,
    onClickSearchResult,
});
</script>
