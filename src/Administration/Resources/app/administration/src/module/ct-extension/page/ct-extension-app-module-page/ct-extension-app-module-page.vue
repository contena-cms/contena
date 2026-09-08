<template>
    <ct-block name="ct_extension_app_module_page">
        <ct-meteor-page class="ct-extension-app-module-page" :show-smart-bar="showSmartBar">
            <template #smart-bar-header>
                <h2 v-if="!suspend">{{ heading }}</h2>
            </template>
            <template #default>
                <div v-if="!suspend && signedIframeSrc" class="ct-extension-app-module-page__content">
                    <iframe
                        v-show="appLoaded"
                        class="ct-extension-app-module-page__app-content"
                        referrerpolicy="origin-when-cross-origin"
                        :src="signedIframeSrc"
                        :title="heading ?? ''"
                    />
                    <mt-loader v-if="!appLoaded && !timedOut" />
                    <ct-error v-else-if="!appLoaded && timedOut" />
                </div>
                <ct-error v-else-if="appsLoaded && suspend" />
                <mt-loader v-else />
            </template>
        </ct-meteor-page>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-extension-app-module-page.scss';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const appName = computed(() => String(route.params.appName ?? ''));
const moduleName = computed(() => (route.params.moduleName ? String(route.params.moduleName) : null));
const appLoaded = ref(false);
const timedOut = ref(false);
const signedIframeSrc = ref<string>();
let timedOutTimeout: number | null = null;

const appsStore = Contena.Store.get('contenaApps');
const appsLoaded = computed(() => appsStore.appsLoaded);
const appDefinition = computed(() => appsStore.apps.find((app) => app.name === appName.value) ?? null);
const moduleDefinition = computed(() => {
    if (!appDefinition.value) return null;
    if (!moduleName.value) return appDefinition.value.mainModule ?? null;
    return appDefinition.value.modules.find((module) => module.name === moduleName.value) ?? null;
});
const entryPoint = computed(() => moduleDefinition.value?.source ?? null);
const heading = computed(() => {
    const labels = [appDefinition.value?.label, moduleDefinition.value?.label].filter(Boolean);
    const locale = Contena.Store.get('session').currentLocale;
    const fallback = Contena.Context.app.fallbackLocale;
    return labels.map((label) => label?.[locale] ?? (fallback ? label?.[fallback] : null)).filter(Boolean).join(' - ');
});
const origin = computed(() => {
    if (!entryPoint.value) return null;
    try { return new URL(entryPoint.value).origin; } catch { return null; }
});
const suspend = computed(() => !appDefinition.value || !moduleDefinition.value);
const showSmartBar = computed(() => true);

const signEntryPoint = async () => {
    appLoaded.value = false;
    timedOut.value = false;
    signedIframeSrc.value = undefined;
    if (!entryPoint.value) return;
    const source = new URL(entryPoint.value);
    const response = await Contena.Service('extensionSdkService').signIframeSrc(appName.value, `${source.origin}${source.pathname}`);
    signedIframeSrc.value = (response as { uri?: string })?.uri;
};
const onContentLoaded = (event: MessageEvent<string>) => {
    if (event.origin === origin.value && event.data === 'ct-app-loaded') appLoaded.value = true;
};

watch(entryPoint, () => void signEntryPoint(), { immediate: true });
watch(appLoaded, (loaded) => {
    if (timedOutTimeout !== null) window.clearTimeout(timedOutTimeout);
    timedOutTimeout = loaded ? null : window.setTimeout(() => { if (!appLoaded.value) timedOut.value = true; }, 5000);
});
onMounted(() => window.addEventListener('message', onContentLoaded));
onBeforeUnmount(() => {
    window.removeEventListener('message', onContentLoaded);
    if (timedOutTimeout !== null) window.clearTimeout(timedOutTimeout);
});

if (!Contena.Service('acl').can(`app.${appName.value}`)) void router.push({ name: 'ct-privilege-error.index' });

ctDefinePublic({ appLoaded, timedOut, signedIframeSrc, heading, showSmartBar, suspend, appsLoaded, signEntryPoint });
</script>
