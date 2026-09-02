<template>
    <ct-block name="ct_settings_language_add_modal">
        <ct-modal
            class="ct-settings-language-add-modal"
            :title="$t('ct-settings-language.addModal.title')"
            @modal-close="onClose"
        >
            <ct-block name="ct_settings_language_add_modal_select">
                <ct-single-select
                    v-model:value="selectedLocale"
                    class="ct-settings-language-add-modal__language-select"
                    :label="$t('ct-settings-language.addModal.labelLanguage')"
                    :placeholder="$t('ct-settings-language.addModal.placeholderLanguage')"
                    :options="languageOptions"
                    :is-loading="isLoading"
                    :disabled="isLoading || undefined"
                    label-property="label"
                    value-property="value"
                />
            </ct-block>

            <ct-block name="ct_settings_language_add_modal_selection_info">
                <ul v-if="selectedTranslation" class="ct-settings-language-add-modal__selection-info">
                    <li class="ct-settings-language-add-modal__selection-info-item">
                        {{ $t(translationsHintTextKey) }}
                    </li>

                    <li class="ct-settings-language-add-modal__selection-info-item">
                        {{ $t('ct-settings-language.addModal.docsHint') }}
                        <mt-link
                            v-if="documentationUrlSnippetKey"
                            type="external"
                            as="a"
                            :href="$t(documentationUrlSnippetKey)"
                            target="_blank"
                            rel="noopener"
                        >
                            {{ $t('ct-settings-language.addModal.docsLinkLabel') }}
                        </mt-link>
                    </li>
                </ul>
            </ct-block>

            <ct-block name="ct_settings_language_add_modal_hint">
                <div class="ct-settings-language-add-modal__hint">
                    <i18n-t tag="span" keypath="ct-settings-language.addModal.extensionStoreHint">
                        <template #extensionStoreLink>
                            <mt-link :to="{ name: 'ct.extension.store.landing-page' }" type="internal">
                                {{ $t('ct-settings-language.addModal.extensionStoreLink') }}
                            </mt-link>
                        </template>
                    </i18n-t>
                </div>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_settings_language_add_modal_footer">
                    <ct-block name="ct_settings_language_add_modal_footer_create_custom">
                        <mt-link
                            class="ct-settings-language-add-modal__create-custom"
                            :to="{ name: 'ct.settings.language.create' }"
                            type="internal"
                        >
                            {{ $t('ct-settings-language.addModal.createCustomLanguage') }}
                        </mt-link>
                    </ct-block>

                    <ct-block name="ct_settings_language_add_modal_footer_actions">
                        <div class="ct-settings-language-add-modal__footer-actions">
                            <ct-block name="ct_settings_language_add_modal_footer_cancel">
                                <mt-button variant="secondary" size="small" @click="onClose">
                                    {{ $t('global.default.cancel') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="ct_settings_language_add_modal_footer_add">
                                <mt-button
                                    class="ct-settings-language-add-modal__add-action"
                                    variant="primary"
                                    size="small"
                                    :disabled="!selectedLocale || isSaving || undefined"
                                    :is-loading="isSaving"
                                    @click="onAddLanguage"
                                >
                                    {{ $t('ct-settings-language.addModal.buttonAdd') }}
                                </mt-button>
                            </ct-block>
                        </div>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-settings-language-add-modal.scss';
const { Criteria } = Contena.Data;

defineProps({});
const emit = defineEmits([
    'close',
    'language-added',
]);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const $t = t;
const repositoryFactory = inject('repositoryFactory');
const translationService = inject('translationService');

const translations = ref([]);
const documentationUrlSnippetKey = ref(null);
const completenessThreshold = ref(null);
const existingLanguageLocales = ref([]);
const selectedLocale = ref(null);
const isLoading = ref(false);
const isSaving = ref(false);

const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const languageOptions = computed(() => {
    return translations.value
        .map((translation) => {
            const isLinked = translation.lastUpdate !== null;
            const existsAsLanguage = existingLanguageLocales.value.includes(translation.locale);

            return {
                value: translation.locale,
                label: translation.name,
                disabled: isLinked || existsAsLanguage,
                isPseudoLanguage: translation.isPseudoLanguage === true,
            };
        })
        .sort((a, b) => {
            if (a.isPseudoLanguage !== b.isPseudoLanguage) {
                return a.isPseudoLanguage ? 1 : -1;
            }

            return a.label.localeCompare(b.label);
        });
});
const selectedTranslation = computed(() => {
    return translations.value.find((translation) => translation.locale === selectedLocale.value) ?? null;
});
const translationsSufficient = computed(() => {
    const progress = selectedTranslation.value?.progress;

    if (typeof progress !== 'number' || completenessThreshold.value === null) {
        return true;
    }

    return progress >= completenessThreshold.value;
});
const translationsHintTextKey = computed(() => {
    return translationsSufficient.value
        ? 'ct-settings-language.addModal.translationsAvailable'
        : 'ct-settings-language.addModal.translationsIncomplete';
});

const createdComponent = async () => {
    isLoading.value = true;

    const [
        listResponse,
        metaResponse,
    ] = await Promise.all([
        translationService.getList().catch(() => null),
        translationService.getMeta().catch(() => null),
        loadExistingLanguageLocales(),
    ]);

    if (listResponse === null || metaResponse === null) {
        createNotificationError({
            message: t('ct-settings-language.addModal.messageTranslationsLoadError'),
        });
    }

    translations.value = listResponse?.items ?? [];
    documentationUrlSnippetKey.value = metaResponse?.documentationUrlSnippetKey ?? null;
    completenessThreshold.value = metaResponse?.completenessThreshold ?? null;

    isLoading.value = false;
};
const loadExistingLanguageLocales = async () => {
    const criteria = new Criteria(1, 500);
    criteria.addAssociation('locale');

    const languages = await languageRepository.value.search(criteria).catch(() => {
        createNotificationError({
            message: t('ct-settings-language.addModal.messageLanguagesLoadError'),
        });
        return [];
    });
    existingLanguageLocales.value = languages.map((language) => language.locale?.code).filter((code) => code);
};
const onAddLanguage = async () => {
    if (!selectedLocale.value) {
        return;
    }

    isSaving.value = true;

    try {
        await translationService.install({
            locales: [selectedLocale.value],
            activate: true,
        });

        createNotificationSuccess({
            message: t('ct-settings-language.addModal.messageAddSuccess'),
        });

        emit('language-added', selectedLocale.value);
    } catch {
        createNotificationError({
            message: t('ct-settings-language.addModal.messageAddError'),
        });
    } finally {
        isSaving.value = false;
    }
};
const onClose = () => {
    emit('close');
};

void createdComponent();

ctDefinePublic({
    repositoryFactory,
    translationService,
    translations,
    documentationUrlSnippetKey,
    completenessThreshold,
    existingLanguageLocales,
    selectedLocale,
    isLoading,
    isSaving,
    languageRepository,
    languageOptions,
    selectedTranslation,
    translationsSufficient,
    translationsHintTextKey,
    createdComponent,
    loadExistingLanguageLocales,
    onAddLanguage,
    onClose,
});

defineExpose({
    repositoryFactory,
    translationService,
    translations,
    documentationUrlSnippetKey,
    completenessThreshold,
    existingLanguageLocales,
    selectedLocale,
    isLoading,
    isSaving,
    languageRepository,
    languageOptions,
    selectedTranslation,
    translationsSufficient,
    translationsHintTextKey,
    createdComponent,
    loadExistingLanguageLocales,
    onAddLanguage,
    onClose,
});
</script>
