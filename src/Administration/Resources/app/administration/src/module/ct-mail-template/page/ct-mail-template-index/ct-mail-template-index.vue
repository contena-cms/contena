<template>
    <ct-block name="ct_mail_template_list">
        <ct-page class="ct-mail-template-index">
            <template #search-bar>
                <ct-block name="ct_mail_templates_list_search_bar">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_mail_template_list_smart_bar_header">
                    <h2>{{ $t('ct-mail-template.list.textMailTemplateOverview') }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_mail_template_list_smart_bar_actions">
                    <ct-button-group split-button>
                        <mt-button variant="primary" :disabled="!canCreate || undefined" @click="onCreateTemplate">
                            {{ $t('ct-mail-template.list.buttonAddMailTemplate') }}
                        </mt-button>

                        <ct-context-button>
                            <template #button>
                                <mt-button variant="primary" square :disabled="!canCreate || undefined">
                                    <mt-icon name="regular-chevron-down-xs" size="16px" />
                                </mt-button>
                            </template>

                            <ct-context-menu-item :disabled="!canCreate || undefined" @click="onCreateHeaderFooter">
                                {{ $t('ct-mail-header-footer.list.buttonAddMailHeaderFooter') }}
                            </ct-context-menu-item>
                        </ct-context-button>
                    </ct-button-group>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_mail_template_list_language_switch">
                    <ct-language-switch @on-change="onLanguageChange" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_mail_template_list_content">
                    <ct-card-view>
                        <ct-block name="ct_mail_template_list_tabs">
                            <mt-tabs
                                class="ct-mail-template-list__tabs"
                                position-identifier="ct-mail-template-index"
                                :default-item="$route.name"
                                :items="mailTemplateTabs"
                                :small="true"
                            />
                        </ct-block>

                        <ct-block name="ct_mail_template_list_router_view">
                            <router-view v-slot="{ Component }">
                                <component :is="Component" :key="languageKey" ref="tabContent" />
                            </router-view>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';

defineProps({});
const route = useRoute();
const router = useRouter();

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is unavailable.');
}

const languageKey = ref(0);
const term = ref(typeof route.query.term === 'string' ? route.query.term : '');
const tabContent = ref<{ getList?: () => void } | null>(null);
const searchType = computed(() =>
    route.name === 'ct.mail.template.index.header_footer' ? 'mail_header_footer' : 'mail_template',
);
const canCreate = computed(() => acl.can('mail_templates.creator'));
const mailTemplateTabs = computed(() => [
    {
        label: 'ct-mail-template.list.tabMailTemplates',
        name: 'ct.mail.template.index.templates',
        onClick: () => router.push({ name: 'ct.mail.template.index.templates' }),
    },
    {
        label: 'ct-mail-template.list.tabHeaderFooter',
        name: 'ct.mail.template.index.header_footer',
        onClick: () => router.push({ name: 'ct.mail.template.index.header_footer' }),
    },
]);

function onCreateTemplate(): void {
    void router.push({ name: 'ct.mail.template.create' });
}

function onCreateHeaderFooter(): void {
    void router.push({ name: 'ct.mail.template.create_head_foot' });
}

function onSearch(searchTerm: string): void {
    term.value = searchTerm;
    void router.replace({
        query: {
            ...route.query,
            term: searchTerm || undefined,
            page: undefined,
        },
    });
}

function onLanguageChange(languageId: string): void {
    Contena.Store.get('context').setApiLanguageId(languageId);
    languageKey.value += 1;
    tabContent.value?.getList?.();
}

ctDefinePublic({
    languageKey,
    term,
    searchType,
    canCreate,
    mailTemplateTabs,
    onSearch,
    onCreateTemplate,
    onCreateHeaderFooter,
    onLanguageChange,
});

defineExpose({
    languageKey,
    term,
    searchType,
    canCreate,
    mailTemplateTabs,
    onSearch,
    onCreateTemplate,
    onCreateHeaderFooter,
    onLanguageChange,
});
</script>

<style>
.ct-mail-template-index .ct-card-view__content {
    max-width: 960px;
    margin: 0 auto;
}
</style>
