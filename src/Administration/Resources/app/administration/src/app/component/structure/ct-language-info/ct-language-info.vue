<template>
    <ct-block name="sw_language_info">
        <p v-if="infoText" class="ct-language-info">
            <ct-block name="sw_language_info_text">
                <span class="sw_language-info__info" v-html="$sanitize(infoText)"></span>
                <span
                    v-if="infoParent"
                    class="ct-language-info__link-parent"
                    role="link"
                    tabindex="0"
                    @click="onClickParentLanguage"
                    @keydown.enter="onClickParentLanguage"
                >
                    "{{ infoParent }}".
                </span>
            </ct-block>
        </p>
    </ct-block>
</template>

<script setup>
import './ct-language-info.scss';
const { warn } = Contena.Utils.debug;

const props = defineProps({
    entityDescription: {
        type: String,
        required: false,
        default: '',
    },
    isNewEntity: {
        type: Boolean,
        required: false,
        default: false,
    },
    changeLanguageOnParentClick: {
        type: Boolean,
        required: false,
        default: true,
    },
});

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const repositoryFactory = inject('repositoryFactory');

const parentLanguage = ref({ name: '' });

const languageId = computed(() => {
    return Contena.Store.get('context').api.languageId;
});
const systemLanguageId = computed(() => {
    return Contena.Store.get('context').api.systemLanguageId;
});
const language = computed(() => {
    return Contena.Store.get('context').api.language;
});
const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const infoParent = computed(() => {
    return parentLanguage.value.name;
});
const infoText = computed(() => {
    // Actual language is system default, because we are creating a new entity
    if (props.isNewEntity) {
        return t(
            'ct-language-info.infoTextNewEntity',
            {
                entityDescription: props.entityDescription,
            },
            0,
        );
    }

    if (language.value === null) {
        return '';
    }

    // Actual language is a child language with the root language as fallback
    if (language.value.parentId !== null && language.value.parentId.length > 0) {
        return t(
            'ct-language-info.infoTextChildLanguage',
            {
                entityDescription: props.entityDescription,
                language: language.value.name,
            },
            0,
        );
    }

    // Actual language is the system default language
    if (isDefaultLanguage.value) {
        return '';
    }

    // Actual language is a root language with the system default language as fallback
    return t(
        'ct-language-info.infoTextRootLanguage',
        {
            entityDescription: props.entityDescription,
            language: language.value.name,
        },
        0,
    );
});
const isDefaultLanguage = computed(() => {
    return languageId.value === systemLanguageId.value;
});

const refreshParentLanguage = async () => {
    if (language.value.id.length < 1 || isDefaultLanguage.value) {
        parentLanguage.value = { name: '' };
        return;
    }

    if (language.value.parentId !== null && language.value.parentId.length > 0) {
        parentLanguage.value = await languageRepository.value.get(language.value.parentId, Contena.Context.api);
        return;
    }

    parentLanguage.value = await languageRepository.value.get(systemLanguageId.value, Contena.Context.api);
};
const onClickParentLanguage = () => {
    if (!props.changeLanguageOnParentClick) {
        return;
    }

    Contena.Utils.EventBus.emit('on-change-language-clicked', parentLanguage.value.id);
};

watch(
    () => language.value.name,
    () => {
        refreshParentLanguage().catch((error) => warn(error));
    },
);

swDefinePublic({
    repositoryFactory,
    parentLanguage,
    languageId,
    systemLanguageId,
    language,
    languageRepository,
    infoParent,
    infoText,
    isDefaultLanguage,
    refreshParentLanguage,
    onClickParentLanguage,
});

defineExpose({
    repositoryFactory,
    parentLanguage,
    languageId,
    systemLanguageId,
    language,
    languageRepository,
    infoParent,
    infoText,
    isDefaultLanguage,
    refreshParentLanguage,
    onClickParentLanguage,
});
</script>
