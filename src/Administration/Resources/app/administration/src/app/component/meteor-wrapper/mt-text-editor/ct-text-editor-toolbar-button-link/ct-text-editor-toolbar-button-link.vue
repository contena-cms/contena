<template>
    <mt-text-editor-toolbar-button :button="button" :editor="editor" :disabled="disabled" @click="openLinkModal" />

    <!-- Link modal -->
    <mt-modal-root :is-open="showLinkModal" @change="($event) => (showLinkModal = $event)">
        <mt-modal
            width="s"
            :title="$t('ct-text-editor-toolbar-button-link.modalTitle')"
            class="ct-text-editor-toolbar-button-link__modal"
        >
            <template #default>
                <mt-loader v-if="isLoading" />

                <div v-else class="ct-text-editor__link-modal">
                    <mt-select
                        :label="$t('ct-text-editor-toolbar-button-link.linkType')"
                        :options="linkOptions"
                        :model-value="linkType"
                        @update:model-value="onSelectFieldChange"
                    />

                    <mt-text-field
                        v-if="linkType === 'link'"
                        v-model="linkHref"
                        :label="$t('ct-text-editor-toolbar-button-link.linkUrl')"
                        placeholder="https://example.com"
                        required
                    />

                    <mt-text-field
                        v-if="linkType === 'phone'"
                        v-model="linkHref"
                        :label="$t('ct-text-editor-toolbar-button-link.linkPhone')"
                        placeholder="+123456789"
                        required
                    >
                        <template #prefix>
                            {{ $t('ct-text-editor-toolbar-button-link.linkPhonePrefix') }}
                        </template>
                    </mt-text-field>

                    <mt-email-field
                        v-if="linkType === 'email'"
                        v-model="linkHref"
                        :label="$t('ct-text-editor-toolbar-button-link.linkEmail')"
                        :placeholder="$t('ct-text-editor-toolbar-button-link.linkEmailPlaceholder')"
                    />

                    <ct-media-field
                        v-if="linkType === 'media'"
                        v-model:value="linkHref"
                        :label="$t('ct-text-editor-toolbar-button-link.linkTo')"
                        single-select
                    />

                    <mt-switch
                        v-if="showOpenInNewTabToggle"
                        :label="$t('ct-text-editor-toolbar-button-link.openInNewTab')"
                        :model-value="linkTarget === '_blank'"
                        :aria-label="$t('ct-text-editor-toolbar-button-link.openInNewTab')"
                        class="ct-text-editor-toolbar-button-link__open-in-new-tab-switch"
                        @update:model-value="
                            (checked) => {
                                linkTarget = checked ? '_blank' : null;
                            }
                        "
                    />

                    <mt-switch
                        v-model="displayAsButton"
                        :label="$t('ct-text-editor-toolbar-button-link.displayAsButton')"
                        class="ct-text-editor-toolbar-button-link__display-as-button-switch"
                    />

                    <mt-select v-if="displayAsButton" v-model="buttonVariant" :options="buttonVariantList" />
                </div>
            </template>
            <template #footer>
                <div class="ct-text-editor__link-modal-footer">
                    <div class="ct-text-editor__link-modal-footer-left">
                        <mt-button variant="critical" :disabled="!isLink()" @click="removeLink">
                            {{ $t('ct-text-editor-toolbar-button-link.removeLink') }}
                        </mt-button>
                    </div>

                    <div class="ct-text-editor__link-modal-footer-right">
                        <mt-modal-close as="mt-button" variant="secondary">
                            {{ $t('global.default.cancel') }}
                        </mt-modal-close>

                        <mt-button variant="primary" @click="applyLink">
                            {{ $t('ct-text-editor-toolbar-button-link.applyLink') }}
                        </mt-button>
                    </div>
                </div>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3';
import type { CustomButton } from '@contena/meteor-component-library/dist/esm/MtTextEditorToolbar';
import './ct-text-editor-toolbar-button-link.scss';
type LinkCategories = 'link' | 'media' | 'email' | 'phone';

const props = defineProps({
    editor: {
        type: Object as PropType<Editor>,
        required: true,
    },
    button: {
        type: Object as PropType<CustomButton>,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

import { type PropType, ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const isLoading = ref(true);
const showLinkModal = ref(false);
const linkHref = ref('');
const linkTarget = ref(null);
const linkType = ref('link');
const displayAsButton = ref(false);
const buttonVariant = ref('primary');

const linkOptions = computed(() => {
    return [
        {
            id: 'link',
            label: t('ct-text-editor-toolbar-button-link.linkOptions.link'),
            value: 'link',
        },
        {
            id: 'media',
            label: t('ct-text-editor-toolbar-button-link.linkOptions.media'),
            value: 'media',
        },
        {
            id: 'email',
            label: t('ct-text-editor-toolbar-button-link.linkOptions.email'),
            value: 'email',
        },
        {
            id: 'phone',
            label: t('ct-text-editor-toolbar-button-link.linkOptions.phoneNumber'),
            value: 'phone',
        },
    ];
});
const buttonVariantList = computed(() => {
    return [
        {
            id: 'primary',
            value: 'primary',
            label: t('ct-text-editor-toolbar-button-link.buttonVariantPrimary'),
        },
        {
            id: 'secondary',
            value: 'secondary',
            label: t('ct-text-editor-toolbar-button-link.buttonVariantSecondary'),
        },
        {
            id: 'primary-sm',
            value: 'primary-sm',
            label: t('ct-text-editor-toolbar-button-link.buttonVariantPrimarySmall'),
        },
        {
            id: 'secondary-sm',
            value: 'secondary-sm',
            label: t('ct-text-editor-toolbar-button-link.buttonVariantSecondarySmall'),
        },
    ];
});
const seoUrlReplacePrefix = computed(() => {
    // Hardcoded SEO URL prefix ID
    return '124c71d524604ccbad6042edce3ac799';
});
const showOpenInNewTabToggle = computed(() => {
    return [
        'link',
        'media',
    ].includes(linkType.value);
});

const getEditor = () => {
    return props.editor;
};
const openLinkModal = () => {
    isLoading.value = true;
    showLinkModal.value = true;

    // Get current link from selection
    const editor = getEditor();
    linkHref.value = (editor.getAttributes('link').href as string) ?? '';
    linkTarget.value = (editor.getAttributes('link').target as string) ?? '';

    // Parse link type
    const { linkType: parsedLinkType, linkHref: parsedLinkHref } = parseLink(linkHref.value);

    // Parse link class
    displayAsButton.value = (editor.getAttributes('link').class as string)?.includes('btn');

    if (displayAsButton.value) {
        buttonVariant.value = parseButtonClass();
    }

    linkType.value = parsedLinkType;
    linkHref.value = parsedLinkHref;

    // Finish loading
    isLoading.value = false;
};
function parseLink(link: string) {
    const slicedLink = link.slice(0, -1).split('/');
    if (link.startsWith(seoUrlReplacePrefix.value) && ['mediaId'].includes(slicedLink[1])) {
        if (slicedLink[1] === 'mediaId') {
            slicedLink[1] = 'media';
        }
        return {
            linkType: slicedLink[1] as LinkCategories,
            linkHref: slicedLink[2],
        };
    }
    if (link.startsWith('mailto:')) {
        return {
            linkType: 'email',
            linkHref: link.replace('mailto:', ''),
        };
    }
    if (link.startsWith('tel:')) {
        return {
            linkType: 'phone',
            linkHref: link.replace('tel:', ''),
        };
    }

    // When nothing was found use "link" as default
    return {
        linkType: 'link',
        linkHref: link,
    };
}
function parseButtonClass() {
    // Get the correct button type from the class
    const fullButtonClass = (getEditor().getAttributes('link').class as string) ?? '';
    const buttonClasses = fullButtonClass.split(' ');
    const buttonVariant = buttonVariantList.value.find((variant) => {
        // Check if one of the button classes matches exactly the variant value
        // "includes" does not work here because it would match "primary" in "primary-sm"
        return buttonClasses.find((buttonClass: string) => {
            if (buttonClass === `btn-${variant.value}`) {
                return variant;
            }
            return false;
        });
    });
    return buttonVariant?.value ?? 'primary';
}
const applyLink = () => {
    const href = prepareLink();
    const linkClass = prepareClass();

    prepareTarget();

    getEditor()
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({
            href,
            target: linkTarget.value,
            class: linkClass,
        })
        .run();

    showLinkModal.value = false;
};
const removeLink = () => {
    getEditor().chain().focus().unsetLink().run();

    showLinkModal.value = false;
};
const isLink = () => {
    return getEditor().isActive('link');
};
function prepareLink() {
    switch (linkType.value) {
        case 'media':
            return `${seoUrlReplacePrefix.value}/mediaId/${linkHref.value}#`;
        case 'email':
            return `mailto:${linkHref.value}`;
        case 'phone':
            return `tel:${linkHref.value.replace(/\//, '')}`;
        default:
            return addProtocolToLink(linkHref.value);
    }
}
function prepareClass() {
    return displayAsButton.value ? `btn btn-${buttonVariant.value}` : undefined;
}
function prepareTarget() {
    // Remove link target "_blank" if it is not allowed
    if (!showOpenInNewTabToggle.value) {
        linkTarget.value = null;
    }
}
function addProtocolToLink(link: string) {
    if (/(^(\w+):\/\/)|(mailto:)|(fax:)|(tel:)/.test(link)) {
        return link;
    }
    const isInternal = /^\/[^\/\s]/.test(link);
    const isAnchor = link.substring(0, 1) === '#';
    const isProtocolRelative = /^\/\/[^\/\s]/.test(link);
    if (!isInternal && !isAnchor && !isProtocolRelative) {
        link = `https://${link}`;
    }
    return link;
}
const onSelectFieldChange = (selectedLinkType: LinkCategories) => {
    linkType.value = selectedLinkType;
    linkHref.value = '';
};

ctDefinePublic({
    isLoading,
    showLinkModal,
    linkHref,
    linkTarget,
    linkType,
    displayAsButton,
    buttonVariant,
    linkOptions,
    buttonVariantList,
    seoUrlReplacePrefix,
    showOpenInNewTabToggle,
    getEditor,
    openLinkModal,
    parseLink,
    parseButtonClass,
    applyLink,
    removeLink,
    isLink,
    prepareLink,
    prepareClass,
    prepareTarget,
    addProtocolToLink,
    onSelectFieldChange,
});

defineExpose({
    isLoading,
    showLinkModal,
    linkHref,
    linkTarget,
    linkType,
    displayAsButton,
    buttonVariant,
    linkOptions,
    buttonVariantList,
    seoUrlReplacePrefix,
    showOpenInNewTabToggle,
    getEditor,
    openLinkModal,
    parseLink,
    parseButtonClass,
    applyLink,
    removeLink,
    isLink,
    prepareLink,
    prepareClass,
    prepareTarget,
    addProtocolToLink,
    onSelectFieldChange,
});
</script>
