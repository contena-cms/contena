<template>
    <ct-block name="ct_meteor_navigation">
        <div v-if="hasParentRoute" class="ct-meteor-navigation">
            <ct-block name="ct_meteor_navigation_link">
                <router-link :to="parentRoute" class="ct-meteor-navigation__link">
                    <ct-block name="ct_meteor_navigation_link_icon">
                        <mt-icon class="ct-meteor-navigation__back-arrow" name="solid-long-arrow-left" size="12px" />
                    </ct-block>

                    <ct-block name="ct_meteor_navigation_link_label">
                        {{ $t('global.default.back') }}
                    </ct-block>
                </router-link>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type { RouteLocationNamedRaw } from 'vue-router';
import './ct-meteor-navigation.scss';

const props = defineProps({
    fromLink: {
        type: Object as PropType<RouteLocationNamedRaw | null>,
        required: false,
        default: null,
    },
});

import { type PropType, computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const hasParentRoute = computed(() => {
    return parentRoute.value !== null;
});
const parentRoute = computed(() => {
    if (props.fromLink && props.fromLink.name !== null) {
        return props.fromLink;
    }

    if (typeof route?.meta?.parentPath === 'string') {
        return {
            name: route.meta.parentPath,
        };
    }

    return null;
});

ctDefinePublic({
    hasParentRoute,
    parentRoute,
});

defineExpose({
    hasParentRoute,
    parentRoute,
});
</script>
