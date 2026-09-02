<template>
    <ct-block name="ct_admin">
        <mt-theme-provider :future="{ removeCardWidth: true }">
            <ct-skip-link />

            <div id="app">
                <div id="overrideComponents" style="display: none">
                    <component
                        :is="overrideComponent"
                        v-for="(overrideComponent, index) in overrideComponents"
                        v-once
                        :key="index"
                    />
                </div>

                <template v-if="isLoggedIn">
                    <ct-duplicated-media-v2 />
                    <ct-settings-cache-modal />
                    <ct-media-modal-renderer />
                    <ct-upload-status />
                </template>

                <ct-notifications ref="notifications" />
                <router-view />
                <mt-snackbar />
            </div>
        </mt-theme-provider>
    </ct-block>
</template>

<script setup lang="ts">
const { Component } = Contena;

defineOptions({
    metaInfo() {
        return {
            title: this.$t('global.ct-admin-menu.textContenaAdmin'),
        };
    },
});

defineProps({});

import { computed, inject } from 'vue';

const loginService = inject('loginService');

const isLoggedIn = computed(() => {
    return loginService.isLoggedIn();
});
const overrideComponents = computed(() => {
    return Component.getOverrideComponents();
});

ctDefinePublic({
    loginService,
    isLoggedIn,
    overrideComponents,
});

defineExpose({
    loginService,
    isLoggedIn,
    overrideComponents,
});
</script>
