<template>
    <ct-block name="sw_mail_template_preview_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal
                class="ct-mail-template-preview-modal"
                :title="$t('ct-mail-template.detail.previewModalTitle')"
                width="xl"
            >
                <ct-block name="sw_mail_template_preview_modal_content">
                    <template v-if="!isLoading">
                        <div class="ct-mail-template-preview-modal__subject">
                            <h3 class="ct-mail-template-preview-modal__subject-title">
                                {{ $t('ct-mail-template.detail.options.labelSubject') }}
                            </h3>
                            <mt-banner
                                v-if="mailPreview.subject.type === 'error'"
                                class="ct-mail-template-preview-modal__subject-error"
                                variant="critical"
                                :title="mailPreview.subject.errorTitle"
                            >
                                {{ mailPreview.subject.errorMessage }}
                            </mt-banner>
                            <div v-else class="ct-mail-template-preview-modal__subject-content">
                                {{ mailPreview.subject.content }}
                            </div>
                        </div>

                        <div class="ct-mail-template-preview-modal__sender-name">
                            <h3 class="ct-mail-template-preview-modal__sender-name-title">
                                {{ $t('ct-mail-template.detail.options.labelSenderName') }}
                            </h3>
                            <mt-banner
                                v-if="mailPreview.senderName.type === 'error'"
                                class="ct-mail-template-preview-modal__sender-name-error"
                                variant="critical"
                                :title="mailPreview.senderName.errorTitle"
                            >
                                {{ mailPreview.senderName.errorMessage }}
                            </mt-banner>
                            <div v-else class="ct-mail-template-preview-modal__sender-name-content">
                                {{ mailPreview.senderName.content }}
                            </div>
                        </div>

                        <div class="ct-mail-template-preview-modal__plain-text">
                            <h3 class="ct-mail-template-preview-modal__plain-text-title">
                                {{ $t('ct-mail-template.detail.mailText.labelContentPlain') }}
                            </h3>
                            <template v-for="part in plainParts" :key="part.name">
                                <mt-banner
                                    v-if="part.result.type === 'error'"
                                    class="ct-mail-template-preview-modal__plain-text-error"
                                    variant="critical"
                                    :title="`${$t(part.label)}: ${part.result.errorTitle}`"
                                >
                                    {{ part.result.errorMessage }}
                                </mt-banner>
                                <pre v-else class="ct-mail-template-preview-modal__plain-text-content">{{
                                    part.result.content
                                }}</pre>
                            </template>
                        </div>

                        <div class="ct-mail-template-preview-modal__html">
                            <h3 class="ct-mail-template-preview-modal__html-title">
                                {{ $t('ct-mail-template.detail.mailText.labelContentHtml') }}
                            </h3>
                            <template v-for="part in htmlParts" :key="part.name">
                                <mt-banner
                                    v-if="part.result.type === 'error'"
                                    class="ct-mail-template-preview-modal__html-error"
                                    variant="critical"
                                    :title="`${$t(part.label)}: ${part.result.errorTitle}`"
                                >
                                    {{ part.result.errorMessage }}
                                </mt-banner>
                                <div
                                    v-else
                                    class="ct-mail-template-preview-modal__html-content"
                                    v-html="part.result.content"
                                ></div>
                            </template>
                        </div>
                    </template>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_mail_template_detail_preview_modal_footer">
                        <ct-block name="sw_mail_template_detail_preview_modal_footer_cancel">
                            <mt-button size="small" variant="secondary" @click="onClose">
                                {{ $t('global.default.close') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue';

interface PreviewResult {
    type: 'success' | 'error';
    content?: string;
    errorTitle?: string;
    errorMessage?: string;
}

interface MailPreview {
    subject: PreviewResult;
    senderName: PreviewResult;
    headerPlain: PreviewResult;
    contentPlain: PreviewResult;
    footerPlain: PreviewResult;
    headerHtml: PreviewResult;
    contentHtml: PreviewResult;
    footerHtml: PreviewResult;
}

const props = defineProps({
    mailPreview: { type: Object as PropType<MailPreview>, required: true },
    isLoading: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'modal-close': [] }>();

const plainParts = computed(() => [
    {
        name: 'headerPlain',
        label: 'ct-mail-template.detail.previewModalMailHeaderPlainLabel',
        result: props.mailPreview.headerPlain,
    },
    {
        name: 'contentPlain',
        label: 'ct-mail-template.detail.previewModalMailContentPlainLabel',
        result: props.mailPreview.contentPlain,
    },
    {
        name: 'footerPlain',
        label: 'ct-mail-template.detail.previewModalMailFooterPlainLabel',
        result: props.mailPreview.footerPlain,
    },
]);
const htmlParts = computed(() => [
    {
        name: 'headerHtml',
        label: 'ct-mail-template.detail.previewModalMailHeaderHtmlLabel',
        result: props.mailPreview.headerHtml,
    },
    {
        name: 'contentHtml',
        label: 'ct-mail-template.detail.previewModalMailContentHtmlLabel',
        result: props.mailPreview.contentHtml,
    },
    {
        name: 'footerHtml',
        label: 'ct-mail-template.detail.previewModalMailFooterHtmlLabel',
        result: props.mailPreview.footerHtml,
    },
]);

function onClose(): void {
    emit('modal-close');
}

function onModalChange(isOpen: boolean): void {
    if (!isOpen) {
        onClose();
    }
}

swDefinePublic({
    plainParts,
    htmlParts,
    onClose,
    onModalChange,
});

defineExpose({ plainParts, htmlParts, onClose, onModalChange });
</script>
