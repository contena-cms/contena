<template>
    <ct-block name="sw_admin">
        <a-config-provider :locale="antLocale" :theme="themeConfig">
            <ct-skip-link />

            <div id="app" :style="cssVariables">
                <router-view />
            </div>
        </a-config-provider>
    </ct-block>
</template>

<script setup lang="ts">
defineOptions({
    metaInfo() {
        return {
            title: this.$t('global.ct-admin-menu.textContenaAdmin'),
        };
    },
});

defineProps({});

import { computed } from 'vue';
import enGB from 'ant-design-vue/es/locale/en_GB';
import zhCN from 'ant-design-vue/es/locale/zh_CN';
import useSession from 'src/app/composables/use-session';
import useTheme from 'src/app/composables/use-theme';

const { currentLocale } = useSession();
const { themeConfig, cssVariables } = useTheme();

const antLocale = computed(() => (currentLocale.value === 'zh-CN' ? zhCN : enGB));

swDefinePublic({
    antLocale,
    themeConfig,
    cssVariables,
});

defineExpose({
    antLocale,
    themeConfig,
    cssVariables,
});
</script>
