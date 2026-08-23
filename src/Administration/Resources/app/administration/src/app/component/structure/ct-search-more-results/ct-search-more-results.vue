<template>
    <ct-block name="sw_search_more_results">
        <router-link
            v-if="searchTypeRoute"
            :to="{ name: searchTypeRoute, query: { term: term } }"
            class="ct-search-more-results__link ct-search-more-results__link--v2"
        >
            <ct-block name="sw_search_more_results_content">
                <slot name="content">
                    <ct-block name="sw_search_more_results_slot_content">
                        {{ searchContent }}
                    </ct-block>
                </slot>
            </ct-block>
        </router-link>
    </ct-block>
</template>

<script setup>
import './ct-search-more-results.scss';
const { Application } = Contena;

const props = defineProps({
    entity: {
        required: true,
        type: String,
        default: '',
    },
    term: {
        type: String,
        required: false,
        default: null,
    },
});

import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const searchTypeService = inject('searchTypeService');

const moduleFactory = computed(() => {
    return Application.getContainer('factory').module || {};
});
const searchTypeRoute = computed(() => {
    if (!props.entity || !searchTypes.value[props.entity] || !searchTypes.value[props.entity].listingRoute) {
        const module = moduleFactory.value.getModuleByEntityName(props.entity);

        if (module?.manifest?.routes?.index) {
            return module.manifest.routes.index.name;
        }

        if (module?.manifest?.routes?.list) {
            return module.manifest.routes.list.name;
        }

        return '';
    }

    return searchTypes.value[props.entity].listingRoute;
});
const searchTypes = computed(() => {
    return searchTypeService.getTypes();
});
const searchContent = computed(() => {
    const entityName = t(`global.entities.${props.entity}`, 0);

    return t(
        'global.ct-search-more-results.labelShowResultsInModuleV2',
        {
            entityName: entityName,
            entityNameLower: entityName.toLowerCase(),
        },
        0,
    );
});

swDefinePublic({
    searchTypeService,
    moduleFactory,
    searchTypeRoute,
    searchTypes,
    searchContent,
});

defineExpose({
    searchTypeService,
    moduleFactory,
    searchTypeRoute,
    searchTypes,
    searchContent,
});
</script>
