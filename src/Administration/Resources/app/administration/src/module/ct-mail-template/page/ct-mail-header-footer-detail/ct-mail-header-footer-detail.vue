<template>
    <ct-block name="ct_mail_header_footer_detail">
        <ct-page class="ct-mail-header-footer-detail">
            <template #smart-bar-header>
                <ct-block name="ct_mail_header_footer_detail_header">
                    <h2>{{ headline }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_mail_header_footer_detail_actions">
                    <ct-block name="ct_mail_header_footer_detail_actions_abort">
                        <mt-button variant="secondary" :disabled="isLoading" @click="onCancel">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>
                    <ct-block name="ct_mail_header_footer_detail_actions_save">
                        <ct-button-process
                            variant="primary"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="!canEdit || !item || undefined"
                            @update:process-success="isSaveSuccessful = false"
                            @click="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_mail_header_footer_detail_language_switch">
                    <ct-language-switch :disabled="item?.isNew()" @on-change="load" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_mail_header_footer_detail_content">
                    <ct-card-view>
                        <template v-if="isLoading && !item">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <template v-else-if="item">
                            <ct-block name="ct_mail_header_footer_detail_content_language_info">
                                <ct-language-info :entity-description="headline" />
                            </ct-block>

                            <ct-block name="ct_mail_header_footer_detail_basic_info">
                                <mt-card
                                    position-identifier="ct-mail-header-footer-detail-basic-info"
                                    :title="$t('ct-mail-header-footer.detail.basic.titleCard')"
                                >
                                    <mt-text-field
                                        v-model="item.name"
                                        :label="$t('ct-mail-header-footer.detail.basic.labelName')"
                                        :placeholder="$t('ct-mail-header-footer.detail.basic.placeholderName')"
                                        :disabled="!canEdit || undefined"
                                        required
                                    />
                                    <mt-textarea
                                        v-model="item.description"
                                        :label="$t('ct-mail-header-footer.detail.basic.labelDescription')"
                                        :placeholder="$t('ct-mail-header-footer.detail.basic.placeholderDescription')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_header_footer_detail_content_header">
                                <mt-card
                                    position-identifier="ct-mail-header-footer-detail-content-header"
                                    :title="$t('ct-mail-header-footer.detail.header.titleCard')"
                                >
                                    <ct-code-editor
                                        v-model:value="item.headerPlain"
                                        identifier="header_plain"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-header-footer.detail.header.labelPlain')"
                                        :placeholder="$t('ct-mail-header-footer.detail.header.placeholderPlain')"
                                        :disabled="!canEdit || undefined"
                                    />
                                    <ct-code-editor
                                        v-model:value="item.headerHtml"
                                        identifier="header_html"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-header-footer.detail.header.labelHtml')"
                                        :placeholder="$t('ct-mail-header-footer.detail.header.placeholderHtml')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_header_footer_detail_content_footer">
                                <mt-card
                                    position-identifier="ct-mail-header-footer-detail-content-footer"
                                    :title="$t('ct-mail-header-footer.detail.footer.titleCard')"
                                >
                                    <ct-code-editor
                                        v-model:value="item.footerPlain"
                                        identifier="footer_plain"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-header-footer.detail.footer.labelPlain')"
                                        :placeholder="$t('ct-mail-header-footer.detail.footer.placeholderPlain')"
                                        :disabled="!canEdit || undefined"
                                    />
                                    <ct-code-editor
                                        v-model:value="item.footerHtml"
                                        identifier="footer_html"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-header-footer.detail.footer.labelHtml')"
                                        :placeholder="$t('ct-mail-header-footer.detail.footer.placeholderHtml')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </mt-card>
                            </ct-block>
                        </template>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useNotification } from 'src/app/composables/use-notification';

defineProps({});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) {
    throw new Error('Required Administration services are unavailable.');
}

const repository = repositoryFactory.create('mail_header_footer');
const item = ref<Entity<'mail_header_footer'> | null>(null);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const routeId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const headline = computed(
    () =>
        item.value?.name ||
        t(routeId.value ? 'ct-mail-header-footer.detail.titleResult' : 'ct-mail-header-footer.detail.textHeadline'),
);
const canEdit = computed(() => acl.can(routeId.value ? 'mail_templates.editor' : 'mail_templates.creator'));

async function load(): Promise<void> {
    isLoading.value = true;
    try {
        if (routeId.value) {
            item.value = await repository.get(routeId.value);
        } else if (!item.value) {
            item.value = repository.create(Contena.Context.api);
            item.value.systemDefault = false;
        }
    } finally {
        isLoading.value = false;
    }
}

async function onSave(): Promise<void> {
    if (!item.value) {
        return;
    }

    isLoading.value = true;
    try {
        await repository.save(item.value);
        isSaveSuccessful.value = true;
        if (!routeId.value) {
            await router.replace({ name: 'ct.mail.template.detail_head_foot', params: { id: item.value.id } });
        }
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('global.notification.notificationSaveErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

function onCancel(): void {
    void router.push({ name: 'ct.mail.template.index' });
}

void load();

ctDefinePublic({
    item,
    isLoading,
    isSaveSuccessful,
    headline,
    canEdit,
    load,
    onSave,
    onCancel,
});

defineExpose({ item, isLoading, isSaveSuccessful, headline, canEdit, load, onSave, onCancel });
</script>

<style>
.ct-mail-header-footer-detail .ct-card-view__content {
    max-width: 960px;
    margin: 0 auto;
}
</style>
