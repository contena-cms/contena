<template>
    <ct-block name="sw_text_preview">
        <div class="ct-text-preview">
            <ct-block name="sw_text_preview_shortened_content">
                <span v-html="shortenedText"></span>
            </ct-block>

            <ct-block name="sw_text_preview_expand_button">
                <mt-button
                    v-if="shortened"
                    class="ct-text-preview__expand-button"
                    size="x-small"
                    variant="secondary"
                    @click.prevent="openModal"
                >
                    {{ $t('global.ct-text-preview.showMoreButton') }}
                </mt-button>
            </ct-block>

            <ct-block name="sw_text_preview_modal">
                <ct-modal v-if="showModal" :title="modalTitle" @modal-close="closeModal">
                    <ct-block name="sw_text_preview_modal_content">
                        <div v-html="fullText"></div>
                    </ct-block>

                    <template #modal-footer>
                        <ct-block name="sw_text_preview_modal_footer">
                            <mt-button size="small" variant="secondary" @click="closeModal">
                                {{ $t('global.default.close') }}
                            </mt-button>
                        </ct-block>
                    </template>
                </ct-modal>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-text-preview.scss';
const lineExpr = /(?:\r\n|\r|\n)/g;
const lineBreak = '<br />';

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
    maximumLength: {
        type: Number,
        required: true,
    },
    modalTitle: {
        type: String,
        required: false,
        default: '',
    },
    maximumNewLines: {
        type: Number,
        required: false,
        default: 0,
    },
});

import { ref, computed } from 'vue';

const showModal = ref(false);

const preview = computed(() => {
    let text = props.text;
    let isShortened = false;
    if (props.maximumNewLines > 0) {
        const splitted = text.split(lineExpr).filter((element) => {
            return !!element.trim();
        });
        if (splitted.length > props.maximumNewLines) {
            text = splitted.slice(0, props.maximumNewLines).join('\n');
            isShortened = true;
        }
    }
    if (text.length > props.maximumLength) {
        isShortened = true;
    }
    return {
        shortened: isShortened,
        text: text.slice(0, props.maximumLength).replace(lineExpr, lineBreak),
    };
});
const shortened = computed(() => preview.value.shortened);
const shortenedText = computed(() => preview.value.text);
const fullText = computed(() => {
    return props.text.replace(lineExpr, lineBreak);
});

const closeModal = () => {
    showModal.value = false;
};
const openModal = () => {
    showModal.value = true;
};

swDefinePublic({
    shortened,
    showModal,
    shortenedText,
    fullText,
    closeModal,
    openModal,
});

defineExpose({
    shortened,
    showModal,
    shortenedText,
    fullText,
    closeModal,
    openModal,
});
</script>
