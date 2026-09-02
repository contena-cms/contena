<template>
    <ct-block name="ct_mail_template_detail">
        <ct-page class="ct-mail-template-detail">
            <template #smart-bar-header>
                <ct-block name="ct_mail_template_detail_header">
                    <h2>{{ headline }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_mail_template_detail_actions">
                    <ct-block name="ct_mail_template_detail_actions_abort">
                        <mt-button variant="secondary" :disabled="isLoading" @click="onCancel">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>
                    <ct-block name="ct_mail_template_detail_actions_save">
                        <ct-button-process
                            variant="primary"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="!canEdit || !mailTemplate || undefined"
                            @update:process-success="isSaveSuccessful = false"
                            @click="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_mail_template_detail_language_switch">
                    <ct-language-switch :disabled="mailTemplate?.isNew()" @on-change="load" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_mail_template_detail_content">
                    <ct-card-view sidebar>
                        <template v-if="isLoading && !mailTemplate">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <template v-else-if="mailTemplate">
                            <ct-block name="ct_mail_template_detail_content_language_info">
                                <ct-language-info :entity-description="headline" />
                            </ct-block>

                            <ct-block name="ct_mail_template_detail_basic_info">
                                <mt-card
                                    position-identifier="ct-mail-template-detail-basic-info"
                                    :title="$t('ct-mail-template.detail.basic.titleCard')"
                                >
                                    <ct-entity-single-select
                                        v-model:value="mailTemplate.mailTemplateTypeId"
                                        entity="mail_template_type"
                                        :label="$t('ct-mail-template.detail.basic.labelMailType')"
                                        :disabled="!canEdit || undefined"
                                        required
                                    />
                                    <mt-textarea
                                        v-model="mailTemplate.description"
                                        :label="$t('ct-mail-template.list.columnDescription')"
                                        :placeholder="$t('ct-mail-template.detail.basic.placeholderDescription')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_template_detail_options_info">
                                <mt-card
                                    position-identifier="ct-mail-template-detail-options-info"
                                    :title="$t('ct-mail-template.detail.options.titleCard')"
                                >
                                    <ct-container columns="repeat(2, minmax(0, 1fr))" gap="0 24px">
                                        <mt-text-field
                                            v-model="mailTemplate.subject"
                                            :label="$t('ct-mail-template.detail.options.labelSubject')"
                                            :placeholder="$t('ct-mail-template.detail.options.placeholderSubject')"
                                            :disabled="!canEdit || undefined"
                                            required
                                        />
                                        <mt-text-field
                                            v-model="mailTemplate.senderName"
                                            :label="$t('ct-mail-template.detail.options.labelSenderName')"
                                            :placeholder="$t('ct-mail-template.detail.options.placeholderSenderName')"
                                            :disabled="!canEdit || undefined"
                                        />
                                    </ct-container>
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_template_detail_attachments_info">
                                <mt-card
                                    v-if="!mailTemplate.isNew()"
                                    position-identifier="ct-mail-template-detail-attachments-info"
                                    :title="$t('ct-mail-template.detail.media.titleCard')"
                                >
                                    <ct-upload-listener
                                        v-if="mailTemplate.id"
                                        auto-upload
                                        :upload-tag="mailTemplate.id"
                                        @media-upload-finish="successfulUpload"
                                    />

                                    <ct-media-upload-v2
                                        v-if="mailTemplate.id"
                                        variant="regular"
                                        default-folder="mail_template"
                                        :upload-tag="mailTemplate.id"
                                        :file-accept="fileAccept"
                                        :disabled="!canEdit || undefined"
                                        @media-drop="onMediaDrop"
                                        @media-upload-sidebar-open="openMediaSidebar"
                                    />

                                    <!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->
                                    <ct-data-grid
                                        v-if="attachedMedia.length"
                                        class="ct-mail-template-detail__attachments-info-grid"
                                        :data-source="attachedMedia"
                                        :columns="mediaColumns"
                                        :full-page="false"
                                        :show-settings="false"
                                        :allow-column-edit="false"
                                        :allow-inline-edit="false"
                                        :compact-mode="false"
                                        :show-selection="canEdit || undefined"
                                        @selection-change="onSelectionChanged"
                                    >
                                        <template #preview-fileName="{ item }">
                                            <ct-media-preview :source="item.id" />
                                        </template>

                                        <template #actions="{ item }">
                                            <ct-context-menu-item
                                                variant="danger"
                                                :disabled="!canEdit || undefined"
                                                @click="onRemoveMedia(item.id)"
                                            >
                                                {{ $t('global.default.delete') }}
                                            </ct-context-menu-item>
                                        </template>

                                        <template #bulk>
                                            <mt-link
                                                as="button"
                                                variant="critical"
                                                @click="onDeleteSelectedMedia"
                                                @keydown.enter="onDeleteSelectedMedia"
                                            >
                                                {{ $t('global.default.delete') }}
                                            </mt-link>
                                        </template>
                                    </ct-data-grid>
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_template_detail_mail_text_info">
                                <mt-card
                                    position-identifier="ct-mail-template-detail-mail-text-info"
                                    :title="$t('ct-mail-template.detail.mailText.titleCard')"
                                >
                                    <ct-code-editor
                                        v-model:value="mailTemplate.contentPlain"
                                        identifier="content_plain"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-template.detail.mailText.labelContentPlain')"
                                        :completer-function="completerFunction"
                                        :editor-config="editorConfig"
                                        :disabled="!canEdit || undefined"
                                        required
                                    />
                                    <ct-code-editor
                                        v-model:value="mailTemplate.contentHtml"
                                        identifier="content_html"
                                        completion-mode="entity"
                                        :label="$t('ct-mail-template.detail.mailText.labelContentHtml')"
                                        :completer-function="completerFunction"
                                        :editor-config="editorConfig"
                                        :disabled="!canEdit || undefined"
                                        required
                                    />
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_mail_template_detail_preview_modal">
                                <ct-mail-template-preview-modal
                                    v-if="mailPreview"
                                    :mail-preview="mailPreview"
                                    :is-loading="isLoading"
                                    @modal-close="onCancelShowPreview"
                                />
                            </ct-block>
                        </template>
                    </ct-card-view>
                </ct-block>
            </template>

            <template #sidebar>
                <ct-block name="ct_mail_template_detail_sidebar">
                    <ct-sidebar>
                        <ct-block name="ct_mail_template_detail_sidebar_inner_test_mail">
                            <ct-sidebar-item
                                icon="regular-paper-plane"
                                :title="$t('ct-mail-template.detail.sidebar.titleTestMail')"
                                class="ct-mail-template-detail__test-mail-sidebar"
                            >
                                <div class="ct-mail-template-detail__test-mail-sidebar-container">
                                    <mt-text-field
                                        v-model="testerMail"
                                        :label="$t('ct-mail-template.detail.sidebar.labelTestMail')"
                                        :placeholder="$t('ct-mail-template.detail.sidebar.placeholderTestMail')"
                                    />

                                    <mt-select
                                        value-property="name"
                                        :label="$t('ct-mail-template.detail.sidebar.selectTriggerEventLabel')"
                                        :model-value="triggerEvent?.name"
                                        :options="triggerEvents"
                                        @update:model-value="onTriggerEventChange"
                                    >
                                        <template #hint>
                                            <mt-icon size="12" name="solid-info-circle" />
                                            {{ $t('ct-mail-template.detail.sidebar.selectTriggerEventHint') }}
                                        </template>
                                    </mt-select>

                                    <mt-button
                                        class="ct-mail-template-detail__send-test-mail"
                                        variant="secondary"
                                        :disabled="isSendButtonDisabled"
                                        @click="onClickTestMailTemplate"
                                    >
                                        {{ $t('ct-mail-template.detail.sidebar.buttonTestMail') }}
                                    </mt-button>
                                </div>
                            </ct-sidebar-item>
                        </ct-block>

                        <ct-block name="ct_mail_template_detail_sidebar_inner_variables">
                            <ct-sidebar-item
                                icon="regular-code"
                                :title="$t('ct-mail-template.detail.sidebar.titleShowAvailableVariables')"
                                :disabled="isLoading"
                                class="ct-mail-template-detail__show-available-variables"
                            >
                                <div class="ct-mail-template-detail__available-variables-sidebar-container">
                                    <mt-select
                                        class="ct-mail-template-detail__available-variables-sidebar-trigger-select"
                                        value-property="name"
                                        :label="$t('ct-mail-template.detail.sidebar.selectTriggerEventLabel')"
                                        :model-value="triggerEvent?.name"
                                        :options="triggerEvents"
                                        @update:model-value="onTriggerEventChange"
                                    >
                                        <template #hint>
                                            <mt-icon size="12" name="solid-info-circle" />
                                            {{ $t('ct-mail-template.detail.sidebar.selectTriggerEventHint') }}
                                        </template>
                                    </mt-select>

                                    <ct-tree
                                        class="ct-mail-template-detail__available-variables-sidebar-container__tree"
                                        :searchable="false"
                                        :allow-create-categories="false"
                                        :disable-context-menu="true"
                                        :on-change-route="() => false"
                                        :items="loadedAvailableVariables"
                                        :sortable="false"
                                        @get-tree-items="onGetTreeItems"
                                    >
                                        <template #headline><span /></template>
                                        <template #items="{ treeItems, disableContextMenu, onChangeRoute }">
                                            <ct-tree-item
                                                v-for="item in treeItems"
                                                :key="item.id"
                                                :item="item"
                                                :disable-context-menu="disableContextMenu"
                                                :on-change-route="onChangeRoute"
                                                :sortable="false"
                                                :display-checkbox="false"
                                            >
                                                <template #grip><span /></template>
                                                <template #actions="{ item: treeItem }">
                                                    <mt-icon
                                                        v-if="treeItem.childCount === 0"
                                                        v-tooltip="{
                                                            message: $t('ct-mail-template.detail.sidebar.copyTooltip'),
                                                            width: 150,
                                                            position: 'bottom',
                                                        }"
                                                        name="regular-products-s"
                                                        class="ct-mail-template-detail__copy_icon"
                                                        @click="onCopyVariable(treeItem.schema)"
                                                    />
                                                </template>
                                            </ct-tree-item>
                                        </template>
                                    </ct-tree>
                                </div>
                            </ct-sidebar-item>
                        </ct-block>

                        <ct-block name="ct_mail_template_detail_sidebar_inner_preview">
                            <ct-sidebar-item
                                icon="regular-eye"
                                :title="$t('ct-mail-template.detail.sidebar.titleShowPreview')"
                                :disabled="!previewAllowed"
                                class="ct-mail-template-detail__show-preview-sidebar"
                            >
                                <div class="ct-mail-template-detail__show-preview-sidebar-container">
                                    <mt-select
                                        value-property="name"
                                        :label="$t('ct-mail-template.detail.sidebar.selectTriggerEventLabel')"
                                        :model-value="triggerEvent?.name"
                                        :options="triggerEvents"
                                        @update:model-value="onTriggerEventChange"
                                    >
                                        <template #hint>
                                            <mt-icon size="12" name="solid-info-circle" />
                                            {{ $t('ct-mail-template.detail.sidebar.selectTriggerEventHint') }}
                                        </template>
                                    </mt-select>

                                    <mt-button
                                        variant="secondary"
                                        :disabled="!triggerEvent || !canEdit"
                                        @click="onClickShowPreview"
                                    >
                                        {{ $t('ct-mail-template.detail.previewModalTitle') }}
                                    </mt-button>
                                </div>
                            </ct-sidebar-item>
                        </ct-block>

                        <ct-sidebar-media-item ref="mediaSidebarItem" :disabled="!canEdit || undefined">
                            <template #context-menu-items="{ mediaItem }">
                                <mt-button
                                    block
                                    size="small"
                                    variant="secondary"
                                    :disabled="!canEdit || undefined"
                                    @click="onAddMedia(mediaItem)"
                                >
                                    {{ $t('ct-mail-template.detail.sidebar.labelContextMenuAddToMailTemplate') }}
                                </mt-button>
                            </template>
                        </ct-sidebar-media-item>
                    </ct-sidebar>
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
import camelCase from 'lodash-es/camelCase';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type BusinessEventsApiService from 'src/core/service/api/business-events.api.service';
import type MailApiService from 'src/core/service/api/mail.api.service';
import { useNotification } from 'src/app/composables/use-notification';

import { dom } from 'src/core/service/util.service';

interface MailPreviewResult {
    type: 'success' | 'error';
    content?: string;
    errorTitle?: string;
    errorMessage?: string;
}

interface MailPreview {
    subject: MailPreviewResult;
    senderName: MailPreviewResult;
    headerPlain: MailPreviewResult;
    contentPlain: MailPreviewResult;
    footerPlain: MailPreviewResult;
    headerHtml: MailPreviewResult;
    contentHtml: MailPreviewResult;
    footerHtml: MailPreviewResult;
}

interface TriggerEvent {
    name: string;
    aware: string[];
    label?: string;
    data?: Record<string, unknown>;
}

interface AvailableVariableResponse {
    fieldName: string;
    hasChildren: boolean;
}

interface AvailableVariable {
    id: string;
    schema: string;
    name: string;
    childCount: number;
    parentId: string | null;
    afterId: null;
}

interface EntityMappingService {
    getEntityMapping(entityName?: string, entityNameMapping?: Record<string, string>): Record<string, unknown>;
}

interface ApiError {
    response?: {
        data?: {
            errors?: Array<{ detail?: string }>;
        };
    };
}

defineProps({});
const route = useRoute();
const router = useRouter();
// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const entityMappingService = inject<EntityMappingService | null>('entityMappingService', null);
const mailService = inject<MailApiService>('mailService');
const businessEventService = inject<BusinessEventsApiService>('businessEventService');
if (!repositoryFactory || !acl || !mailService || !businessEventService) {
    throw new Error('Required Administration services are unavailable.');
}

const repository = repositoryFactory.create('mail_template');
const mediaRepository = repositoryFactory.create('media');
const mailTemplateMediaRepository = repositoryFactory.create('mail_template_media');
const mailTemplate = ref<Entity<'mail_template'> | null>(null);
const testerMail = ref('');
const mailPreview = ref<MailPreview | null>(null);
const triggerEvent = ref<TriggerEvent | null>(null);
const triggerEvents = ref<TriggerEvent[]>([]);
const availableVariables = ref<Record<string, AvailableVariable>>({});
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const fileAccept = 'application/pdf, image/*';
const selectedMedia = ref<Record<string, Entity<'media'>>>({});
const mediaSidebarItem = ref<{ openContent: () => void } | null>(null);
const routeId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const headline = computed(
    () =>
        mailTemplate.value?.description ||
        t(routeId.value ? 'ct-mail-template.detail.textHeadlineEdit' : 'ct-mail-template.detail.textHeadline'),
);
const canEdit = computed(() => acl.can(routeId.value ? 'mail_templates.editor' : 'mail_templates.creator'));
const attachedMedia = computed(() => {
    if (!mailTemplate.value?.media) {
        return [];
    }

    return mailTemplate.value.media.reduce<Array<Entity<'media'>>>((mediaItems, association) => {
        if (association.languageId === Contena.Context.api.languageId && association.media) {
            mediaItems.push(association.media);
        }

        return mediaItems;
    }, []);
});
const mediaColumns = computed(() => [
    {
        property: 'fileName',
        label: t('ct-mail-template.list.columnFilename'),
    },
]);
const editorConfig = {
    enableBasicAutocompletion: true,
};
const completerFunction = (prefix: string): Array<{ value: string }> => {
    if (!entityMappingService || !mailTemplate.value?.mailTemplateType?.availableEntities) {
        return [];
    }

    return Object.keys(
        entityMappingService.getEntityMapping(prefix, mailTemplate.value.mailTemplateType.availableEntities),
    ).map((value) => ({ value }));
};
const loadedAvailableVariables = computed(() => {
    if (!triggerEvent.value) {
        return [];
    }

    if (Object.values(availableVariables.value).length === 0) {
        void loadInitialAvailableVariables();
    }

    return Object.values(availableVariables.value);
});
const testMailRequirementsMet = computed(
    () =>
        Boolean(testerMail.value) &&
        Boolean(mailTemplate.value?.subject ?? mailTemplate.value?.translated?.subject) &&
        Boolean(mailTemplate.value?.contentPlain ?? mailTemplate.value?.translated?.contentPlain) &&
        Boolean(mailTemplate.value?.contentHtml ?? mailTemplate.value?.translated?.contentHtml) &&
        Boolean(mailTemplate.value?.senderName ?? mailTemplate.value?.translated?.senderName),
);
const isSendButtonDisabled = computed(
    () => isLoading.value || !testMailRequirementsMet.value || !acl.can('api_send_email') || !triggerEvent.value,
);
const previewAllowed = computed(
    () => !isLoading.value && Boolean(mailTemplate.value?.contentHtml) && acl.can('mail_templates.editor'),
);

async function loadTriggerEvents(): Promise<void> {
    const events = (await businessEventService.getBusinessEvents()) as TriggerEvent[];
    triggerEvents.value = events
        .filter((event) => event.aware.includes('mailAware'))
        .map((event) => ({
            ...event,
            label: event.name
                .split('.')
                .map((eventName) => getTriggerEventNameTranslated(eventName))
                .join(' / '),
        }));
}

function getTriggerEventNameTranslated(eventName: string): string {
    const eventNameCamelCase = camelCase(eventName);
    const translatedEventName = [
        `ct-flow-app.triggers-app.${eventNameCamelCase}`,
        `ct-flow-custom-event.event-tree.${eventNameCamelCase}`,
        `ct-flow.triggers.${eventNameCamelCase}`,
    ].find((key) => te(key));

    return translatedEventName ? t(translatedEventName) : eventName.replace(/_|-/g, ' ');
}

function onTriggerEventChange(eventName: string): void {
    triggerEvent.value = triggerEvents.value.find((event) => event.name === eventName) ?? null;
    availableVariables.value = {};
    mailPreview.value = null;
}

function hasPreviewErrors(preview: MailPreview | null = mailPreview.value): boolean {
    const previewParts: Array<keyof MailPreview> = [
        'subject',
        'senderName',
        'headerHtml',
        'contentHtml',
        'footerHtml',
        'headerPlain',
        'contentPlain',
        'footerPlain',
    ];

    return previewParts.some((key) => preview?.[key]?.type === 'error');
}

async function simulateMailPreview(): Promise<MailPreview | null> {
    isLoading.value = true;

    if (!triggerEvent.value || !mailTemplate.value) {
        isLoading.value = false;
        return null;
    }

    try {
        return (await mailService.simulateMailTemplate(
            {
                subject: mailTemplate.value.subject ?? mailTemplate.value.translated?.subject,
                senderName: mailTemplate.value.senderName ?? mailTemplate.value.translated?.senderName,
                contentHtml: mailTemplate.value.contentHtml ?? mailTemplate.value.translated?.contentHtml,
                contentPlain: mailTemplate.value.contentPlain ?? mailTemplate.value.translated?.contentPlain,
                headerHtml: '',
                footerHtml: '',
                headerPlain: '',
                footerPlain: '',
            },
            triggerEvent.value.name,
        )) as MailPreview;
    } catch (error) {
        const detail = (error as ApiError).response?.data?.errors?.[0]?.detail;
        createNotificationError({
            message: detail
                ? t('ct-mail-template.general.notificationSyntaxValidationErrorMessage', { errorMsg: detail })
                : t('ct-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
        });

        return null;
    } finally {
        isLoading.value = false;
    }
}

async function onClickShowPreview(): Promise<void> {
    mailPreview.value = await simulateMailPreview();
}

function onCancelShowPreview(): void {
    mailPreview.value = null;
}

async function onClickTestMailTemplate(): Promise<void> {
    const simulatedMailPreview = await simulateMailPreview();
    if (!simulatedMailPreview) {
        return;
    }

    if (hasPreviewErrors(simulatedMailPreview)) {
        createNotificationError({
            message: t('ct-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
        });
        return;
    }

    try {
        const response = (await mailService.sendMailTemplate(
            testerMail.value,
            testerMail.value,
            {
                subject: simulatedMailPreview.subject.content,
                senderName: simulatedMailPreview.senderName.content,
                contentHtml: simulatedMailPreview.contentHtml.content,
                contentPlain: simulatedMailPreview.contentPlain.content,
            },
            {
                getIds: () => attachedMedia.value.map((mediaItem) => mediaItem.id),
            },
            true,
            {},
            mailTemplate.value?.id ?? null,
        )) as { size?: number } | null;

        if (response?.size === 0) {
            createNotificationError({
                message: t('ct-mail-template.general.notificationGeneralSyntaxValidationErrorMessage'),
            });
            return;
        }

        createNotificationSuccess({
            message: t('ct-mail-template.general.notificationTestMailSuccessMessage'),
        });
    } catch {
        createNotificationError({
            message: t('ct-mail-template.general.notificationTestMailErrorMessage'),
        });
    }
}

async function onCopyVariable(variable: string): Promise<void> {
    try {
        await dom.copyStringToClipboard(variable);
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : '',
        });
    }
}

function addVariables(variables: AvailableVariable[]): void {
    variables.forEach((variable) => {
        availableVariables.value[variable.id] = variable;
    });
}

async function loadAvailableVariables(variable: string): Promise<void> {
    if (!triggerEvent.value) {
        return;
    }

    const response = (await mailService.loadAvailableVariables(
        triggerEvent.value.name,
        variable,
    )) as AvailableVariableResponse[];
    response
        .sort((left, right) => left.fieldName.localeCompare(right.fieldName))
        .forEach((value) => {
            addVariables([
                {
                    id: `${variable}.${value.fieldName}`,
                    schema: `${variable}.${value.fieldName}`,
                    name: value.fieldName,
                    childCount: value.hasChildren ? 1 : 0,
                    parentId: variable,
                    afterId: null,
                },
            ]);
        });
}

function onGetTreeItems(parent: string): void {
    void loadAvailableVariables(parent);
}

async function loadInitialAvailableVariables(): Promise<void> {
    if (!triggerEvent.value) {
        return;
    }

    const response = (await mailService.loadAvailableVariables(triggerEvent.value.name)) as AvailableVariableResponse[];
    response
        .sort((left, right) => left.fieldName.localeCompare(right.fieldName))
        .forEach((value) => {
            addVariables([
                {
                    id: value.fieldName,
                    schema: value.fieldName,
                    name: value.fieldName,
                    childCount: value.hasChildren ? 1 : 0,
                    parentId: null,
                    afterId: null,
                },
            ]);
        });
}

async function load(): Promise<void> {
    isLoading.value = true;
    try {
        if (routeId.value) {
            const criteria = new Contena.Data.Criteria(1, 1);
            criteria.addAssociation('mailTemplateType');
            criteria.addAssociation('media.media');
            mailTemplate.value = await repository.get(routeId.value, Contena.Context.api, criteria);
        } else if (!mailTemplate.value) {
            mailTemplate.value = repository.create(Contena.Context.api);
            mailTemplate.value.systemDefault = false;
        }
    } finally {
        isLoading.value = false;
    }
}

function onAddMedia(mediaItem: Entity<'media'>): boolean {
    const associations = mailTemplate.value?.media;
    if (!associations || associations.some((association) => association.mediaId === mediaItem.id)) {
        return false;
    }

    const association = mailTemplateMediaRepository.create(Contena.Context.api);
    association.mailTemplateId = mailTemplate.value?.id ?? '';
    association.languageId = Contena.Context.api.languageId;
    association.mediaId = mediaItem.id;
    association.media = mediaItem;
    association.position = associations.length;
    associations.push(association);

    return true;
}

function onRemoveMedia(mediaId: string): void {
    const associations = mailTemplate.value?.media;
    const association = associations?.find(
        (item) => item.mediaId === mediaId && item.languageId === Contena.Context.api.languageId,
    );

    if (association) {
        associations?.remove(association.id);
    }
}

async function successfulUpload({ targetId }: { targetId: string }): Promise<void> {
    if (attachedMedia.value.some((mediaItem) => mediaItem.id === targetId)) {
        return;
    }

    const mediaItem = await mediaRepository.get(targetId);
    onAddMedia(mediaItem);
}

async function onMediaDrop(mediaItem: Entity<'media'>): Promise<void> {
    await successfulUpload({ targetId: mediaItem.id });
}

function openMediaSidebar(): void {
    mediaSidebarItem.value?.openContent();
}

function onSelectionChanged(selection: Record<string, Entity<'media'>>): void {
    selectedMedia.value = selection;
}

function onDeleteSelectedMedia(): void {
    Object.keys(selectedMedia.value).forEach(onRemoveMedia);
    selectedMedia.value = {};
}

async function onSave(): Promise<void> {
    if (!mailTemplate.value) {
        return;
    }

    isLoading.value = true;
    try {
        await repository.save(mailTemplate.value);
        isSaveSuccessful.value = true;
        if (!routeId.value) {
            await router.replace({ name: 'ct.mail.template.detail', params: { id: mailTemplate.value.id } });
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

void Promise.all([
    load(),
    loadTriggerEvents(),
]);

ctDefinePublic({
    mailTemplate,
    attachedMedia,
    isLoading,
    isSaveSuccessful,
    headline,
    canEdit,
    fileAccept,
    mediaColumns,
    editorConfig,
    completerFunction,
    testerMail,
    mailPreview,
    triggerEvent,
    triggerEvents,
    loadedAvailableVariables,
    isSendButtonDisabled,
    previewAllowed,
    load,
    loadTriggerEvents,
    getTriggerEventNameTranslated,
    onTriggerEventChange,
    onClickTestMailTemplate,
    hasPreviewErrors,
    simulateMailPreview,
    onClickShowPreview,
    onCancelShowPreview,
    onCopyVariable,
    loadAvailableVariables,
    onGetTreeItems,
    loadInitialAvailableVariables,
    onAddMedia,
    onRemoveMedia,
    successfulUpload,
    onMediaDrop,
    openMediaSidebar,
    onSelectionChanged,
    onDeleteSelectedMedia,
    onSave,
    onCancel,
});

defineExpose({
    mailTemplate,
    attachedMedia,
    isLoading,
    isSaveSuccessful,
    headline,
    canEdit,
    fileAccept,
    mediaColumns,
    editorConfig,
    completerFunction,
    testerMail,
    mailPreview,
    triggerEvent,
    triggerEvents,
    loadedAvailableVariables,
    isSendButtonDisabled,
    previewAllowed,
    load,
    loadTriggerEvents,
    getTriggerEventNameTranslated,
    onTriggerEventChange,
    onClickTestMailTemplate,
    hasPreviewErrors,
    simulateMailPreview,
    onClickShowPreview,
    onCancelShowPreview,
    onCopyVariable,
    loadAvailableVariables,
    onGetTreeItems,
    loadInitialAvailableVariables,
    onAddMedia,
    onRemoveMedia,
    successfulUpload,
    onMediaDrop,
    openMediaSidebar,
    onSelectionChanged,
    onDeleteSelectedMedia,
    onSave,
    onCancel,
});
</script>

<style lang="scss">
.ct-mail-template-detail {
    .ct-card-view__content {
        max-width: 960px;
        margin: 0 auto;
    }

    &__show-available-variables {
        .ct-sidebar-item__content {
            border-top: 1px solid var(--color-border-secondary-default);

            .ct-sidebar-item__scrollable-container {
                overflow-x: auto;
            }

            .ct-mail-template-detail__available-variables-sidebar-trigger-select {
                padding: var(--scale-size-20);
                margin-bottom: 0;
            }
        }
    }

    .ct-tree {
        .ct-tree__content {
            max-height: 100%;
            overflow: visible;
        }

        .icon--regular-circle-xxs,
        .ct-mail-template-detail__copy_icon,
        .icon--regular-folder,
        .icon--regular-folder-open {
            display: none;
        }

        .ct-mail-template-detail__copy_icon {
            display: block;
            max-width: var(--scale-size-14);
            color: var(--color-icon-secondary-default);
            cursor: pointer;

            &:hover {
                color: var(--color-icon-brand-default);
            }

            &:active {
                color: var(--color-icon-brand-hover);
            }
        }
    }

    &__test-mail-sidebar-container,
    &__show-preview-sidebar-container {
        padding: var(--scale-size-20);
        border-top: 1px solid var(--color-border-secondary-default);
    }

    &__attachments-info-grid {
        margin-top: var(--scale-size-20);
        border: 1px solid var(--color-border-secondary-default);
        border-radius: var(--border-radius-xs);
    }
}
</style>
