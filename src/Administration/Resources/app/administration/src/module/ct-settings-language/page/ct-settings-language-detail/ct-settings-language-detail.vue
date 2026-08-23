<template>
    <ct-block name="sw_settings_language_detail">
        <ct-page class="ct-settings-language-detail">
            <template #smart-bar-header>
                <ct-block name="sw_settings_language_detail_header">
                    <h2 v-if="languageHasName">
                        {{ language.name }}
                    </h2>

                    <h2 v-else>
                        {{ translate('ct-settings-language.detail.textHeadline') }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_language_detail_actions">
                    <ct-block name="sw_settings_language_detail_actions_abort">
                        <mt-button variant="secondary" size="default" @click="onCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_settings_language_detail_actions_save">
                        <ct-button-process
                            v-model:process-success="isSaveSuccessful"
                            v-tooltip.bottom="tooltipSave"
                            size="default"
                            class="ct-settings-language-detail__save-action"
                            :is-loading="isLoading"
                            :disabled="isLoading || !allowSave || undefined"
                            variant="primary"
                            @click.prevent="onSave"
                        >
                            {{ translate('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_language_detail_content">
                    <ct-card-view>
                        <ct-block name="sw_settings_language_detail_content_language_info">
                            <ct-language-info
                                v-if="language"
                                :entity-description="language.name"
                                :is-new-entity="isNewLanguage"
                            />
                        </ct-block>

                        <ct-block name="sw_settings_language_detail_content_card">
                            <mt-card
                                class="ct-settings-language-detail__content-card"
                                :class="{ 'is--loading': isLoading || !language }"
                                :title="translate('ct-settings-language.detail.titleCard')"
                                :is-loading="isLoading || !language"
                                position-identifier="ct-settings-language-detail-content"
                            >
                                <ct-container v-if="language" columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0px 30px">
                                    <ct-block name="sw_settings_language_detail_content_field_name">
                                        <mt-text-field
                                            v-model="language.name"
                                            name="ct-field--language-name"
                                            class="ct-settings-language-detail__name"
                                            :disabled="!acl.can('language.editor') || undefined"
                                            :label="translate('ct-settings-language.detail.labelName')"
                                            :placeholder="translate('ct-settings-language.detail.placeholderName')"
                                            :error="languageNameError"
                                            validation="required"
                                            required
                                        />
                                    </ct-block>

                                    <mt-switch
                                        v-model="language.active"
                                        name="ct-field--language-active"
                                        class="ct-settings-language-detail__active"
                                        :disabled="isSystemDefaultLanguageId || !acl.can('language.editor') || undefined"
                                        :label="translate('ct-settings-language.detail.labelActive')"
                                        bordered
                                    />
                                </ct-container>

                                <ct-container v-if="language" columns="repeat(auto-fit, minmax(250px, 1fr))" gap="0px 30px">
                                    <ct-block name="sw_settings_language_detail_content_field_localeId">
                                        <ct-entity-single-select
                                            id="locales"
                                            v-model:value="language.localeId"
                                            name="ct-field--language-localeId"
                                            required
                                            show-clearable-button
                                            class="ct-settings-language-detail__select-locale"
                                            :disabled="!acl.can('language.editor') || undefined"
                                            :label="translate('ct-settings-language.detail.labelLocale')"
                                            entity="locale"
                                            :placeholder="translate('ct-settings-language.detail.placeholderPleaseSelect')"
                                            :error="languageLocaleIdError"
                                        >
                                            <!-- Vue's slot-scope lint does not track property access through this legacy slot. -->
                                            <!-- eslint-disable-next-line vue/no-unused-vars -->
                                            <template #selection-label-property="selection">
                                                {{ selection.getKey(selection.item, 'translated.name') }},
                                                {{ selection.getKey(selection.item, 'translated.territory') }}
                                            </template>

                                            <template
                                                #result-label-property="{ item, searchTerm, highlightSearchTerm, getKey }"
                                            >
                                                <ct-highlight-text
                                                    v-if="highlightSearchTerm"
                                                    :text="`${getKey(item, 'translated.name')}, ${getKey(item, 'translated.territory')}`"
                                                    :search-term="searchTerm"
                                                />

                                                <template v-else>
                                                    {{ getKey(item, 'translated.name') }},
                                                    {{ getKey(item, 'translated.territory') }}
                                                </template>
                                            </template>
                                        </ct-entity-single-select>
                                    </ct-block>

                                    <ct-block name="sw_settings_language_detail_content_field_iso_code">
                                        <ct-entity-single-select
                                            id="iso-codes"
                                            v-model:value="language.translationCodeId"
                                            class="ct-settings-language-detail__select-iso-code"
                                            :disabled="!acl.can('language.editor') || undefined"
                                            label-property="code"
                                            :label="translate('ct-settings-language.detail.labelIsoCode')"
                                            :required="isIsoCodeRequired"
                                            show-clearable-button
                                            :placeholder="translate('ct-settings-language.detail.placeholderPleaseSelect')"
                                            entity="locale"
                                        >
                                            <template
                                                #result-item="{
                                                    isSelected,
                                                    setValue,
                                                    item,
                                                    index,
                                                    labelProperty,
                                                    searchTerm,
                                                    highlightSearchTerm,
                                                    getKey,
                                                }"
                                            >
                                                <ct-select-result
                                                    v-tooltip="{
                                                        showDelay: 300,
                                                        message: translate('ct-settings-language.detail.textIsoCodeIsInUse'),
                                                        disabled: !isLocaleAlreadyUsed(item?.id),
                                                    }"
                                                    :selected="isSelected(item)"
                                                    v-bind="{ item, index }"
                                                    @item-select="setValue"
                                                >
                                                    <ct-highlight-text
                                                        v-if="highlightSearchTerm"
                                                        :text="
                                                            (getKey(item, labelProperty) ||
                                                                getKey(item, `translated.${labelProperty}`)) +
                                                            (isLocaleAlreadyUsed(item?.id) ? '*' : '')
                                                        "
                                                        :search-term="searchTerm"
                                                    />

                                                    <template v-else>
                                                        {{
                                                            getKey(item, labelProperty) ||
                                                            getKey(item, `translated.${labelProperty}`)
                                                        }}
                                                    </template>
                                                </ct-select-result>
                                            </template>

                                            <template #hint>
                                                <div v-if="isLocaleAlreadyUsed(language.translationCodeId)">
                                                    {{ translate('ct-settings-language.detail.textIsoCodeIsInUse') }}
                                                </div>
                                            </template>
                                        </ct-entity-single-select>
                                    </ct-block>
                                </ct-container>

                                <ct-block name="sw_settings_language_detail_content_field_parentId">
                                    <ct-entity-single-select
                                        v-if="language"
                                        id="inherit"
                                        v-model:value="language.parentId"
                                        name="ct-field--language-parentId"
                                        class="ct-settings-language-detail__select-parent"
                                        :criteria="parentLanguageCriteria"
                                        :disabled="!acl.can('language.editor') || isSystemDefaultLanguageId || undefined"
                                        :label="translate('ct-settings-language.detail.labelParent')"
                                        :placeholder="translate('ct-settings-language.detail.placeholderPleaseSelect')"
                                        :help-text="inheritanceTooltipText"
                                        entity="language"
                                        show-clearable-button
                                        @update:value="onInputLanguage"
                                    >
                                        <template
                                            #result-item="{
                                                isSelected,
                                                setValue,
                                                item,
                                                index,
                                                labelProperty,
                                                searchTerm,
                                                highlightSearchTerm,
                                                getKey,
                                            }"
                                        >
                                            <ct-select-result
                                                v-tooltip="{
                                                    showDelay: 300,
                                                    message: translate('ct-settings-language.detail.textLanguageHasParent'),
                                                    disabled: !item.parentId,
                                                }"
                                                :disabled="!!item.parentId || undefined"
                                                :selected="isSelected(item)"
                                                v-bind="{ item, index }"
                                                @item-select="setValue"
                                            >
                                                <ct-highlight-text
                                                    v-if="highlightSearchTerm"
                                                    :text="
                                                        getKey(item, labelProperty) ||
                                                        getKey(item, `translated.${labelProperty}`)
                                                    "
                                                    :search-term="searchTerm"
                                                />

                                                <template v-else>
                                                    {{
                                                        getKey(item, labelProperty) ||
                                                        getKey(item, `translated.${labelProperty}`)
                                                    }}
                                                </template>
                                            </ct-select-result>
                                        </template>
                                    </ct-entity-single-select>
                                </ct-block>

                                <ct-block name="sw_settings_language_detail_content_alert_change_parent">
                                    <mt-banner
                                        v-if="showAlertForChangeParentLanguage"
                                        class="ct-settings-language--alert-change-parent"
                                        :title="translate('global.default.warning')"
                                        variant="attention"
                                    >
                                        {{ translate('ct-settings-language.detail.textAlertChangeParent') }}
                                    </mt-banner>
                                </ct-block>
                            </mt-card>
                        </ct-block>

                        <ct-block name="sw_settings_language_detail_snippet_updates">
                            <mt-card
                                v-if="language && !isNewLanguage"
                                class="ct-settings-language-detail__snippet-updates"
                                :title="translate('ct-settings-language.detail.snippetUpdates.title')"
                                :is-loading="isSnippetMetadataLoading"
                                position-identifier="ct-settings-language-detail-snippet-updates"
                            >
                                <p class="ct-settings-language-detail__snippet-updates-status">
                                    {{ translate(snippetUpdatesLabel) }}
                                    <ct-help-text
                                        v-if="snippetUpdateState === 'notAvailable'"
                                        class="ct-settings-language-detail__snippet-updates-help"
                                        :text="translate('ct-settings-language.detail.snippetUpdates.notAvailableHelpText')"
                                        :width="280"
                                    />
                                </p>

                                <div
                                    v-if="showSnippetUpdateButton || showSnippetAutoUpdate"
                                    class="ct-settings-language-detail__snippet-updates-actions"
                                >
                                    <mt-button
                                        v-if="showSnippetUpdateButton"
                                        class="ct-settings-language-detail__snippet-updates-button"
                                        variant="primary"
                                        size="small"
                                        :disabled="isUpdatingSnippets || undefined"
                                        @click="onUpdateSnippets"
                                    >
                                        {{ translate(snippetUpdateButtonLabel) }}
                                    </mt-button>

                                    <mt-switch
                                        v-if="showSnippetAutoUpdate"
                                        v-model="language.translationAutoUpdate"
                                        :disabled="!acl.can('language.editor') || undefined"
                                        :label="translate('ct-settings-language.detail.snippetUpdates.autoUpdate')"
                                        :help-text="
                                            translate('ct-settings-language.detail.snippetUpdates.autoUpdateHelpText')
                                        "
                                    />
                                </div>
                            </mt-card>
                        </ct-block>

                        <ct-block name="sw_settings_language_detail_custom_field_sets">
                            <mt-card
                                v-if="language && showCustomFields"
                                position-identifier="ct-settings-language-detail-custom-field-sets"
                                :title="translate('ct-settings-custom-field.general.mainMenuItemGeneral')"
                                :is-loading="isLoading"
                            >
                                <ct-custom-field-set-renderer
                                    :entity="language"
                                    :disabled="!acl.can('language.editor') || undefined"
                                    :sets="customFieldSets"
                                />
                            </mt-card>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-language-detail.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    languageId: {
        type: String,
        required: false,
        default: null,
    },
});

import { ref, computed, inject, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const translate = t;
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const customFieldDataProviderService = inject('customFieldDataProviderService');
const translationService = inject('translationService');

const language = ref(null);
const usedTranslationIds = ref([]);
const showAlertForChangeParentLanguage = ref(false);
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const customFieldSets = ref(null);
const parentTranslationCodeId = ref(null);
const snippetMetadata = ref(null);
const builtInLocales = ref([]);
const isUpdatingSnippets = ref(false);
const isSnippetMetadataLoading = ref(false);

const identifier = computed(() => {
    return languageHasName.value ? language.value.name : '';
});
const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const isIsoCodeRequired = computed(() => {
    return !language.value?.parentId;
});
const languageHasName = computed(() => {
    return language.value !== null && language.value.name;
});
const isNewLanguage = computed(() => {
    return language.value && typeof language.value.isNew === 'function' ? language.value.isNew() : false;
});
const usedLocaleCriteria = computed(() => {
    return new Criteria(1, null)
        .addFilter(
            Criteria.not('and', [
                Criteria.equals('id', props.languageId),
            ]),
        )
        .addAggregation(Criteria.terms('usedTranslationIds', 'language.translationCode.id', null, null, null));
});
const allowSave = computed(() => {
    return isNewLanguage.value ? acl.can('language.creator') : acl.can('language.editor');
});
const tooltipSave = computed(() => {
    if (allowSave.value) {
        return {
            message: '',
            disabled: true,
        };
    }

    return {
        message: t('ct-privileges.tooltip.warning'),
        disabled: allowSave.value,
        showOnDisabledElements: true,
    };
});
const parentLanguageCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    if (language.value?.id) {
        criteria.addFilter(Criteria.not('and', [Criteria.equals('id', language.value.id)]));
    }

    return criteria;
});
const isSystemDefaultLanguageId = computed(() => {
    return language.value?.id === Contena.Context.api.systemLanguageId;
});
const inheritanceTooltipText = computed(() => {
    if (isSystemDefaultLanguageId.value) {
        return t('ct-settings-language.detail.tooltipInheritanceNotPossible');
    }

    return t('ct-settings-language.detail.tooltipLanguageNotChoosable');
});
const showCustomFields = computed(() => {
    return customFieldSets.value && customFieldSets.value.length > 0;
});
const languageLocaleIdError = computed(() => {
    const entity = language.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'localeId');
});
const languageNameError = computed(() => {
    const entity = language.value;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const snippetUpdateState = computed(() => {
    if (!language.value) {
        return null;
    }

    const localeCode = language.value.locale?.code;

    if (builtInLocales.value.includes(localeCode)) {
        return 'builtIn';
    }

    if (!snippetMetadata.value) {
        return 'notAvailable';
    }

    const isLinked = snippetMetadata.value.lastUpdate !== null;

    if (isUpdatingSnippets.value) {
        return isLinked ? 'updating' : 'linking';
    }

    if (!isLinked) {
        return 'notLinked';
    }

    return snippetMetadata.value.updateAvailable ? 'updateAvailable' : 'upToDate';
});
const snippetUpdatesLabel = computed(() => {
    return (
        {
            builtIn: 'ct-settings-language.detail.snippetUpdates.builtIn',
            notAvailable: 'ct-settings-language.detail.snippetUpdates.notAvailable',
            notLinked: 'ct-settings-language.detail.snippetUpdates.notLinked',
            linking: 'ct-settings-language.detail.snippetUpdates.linking',
            updating: 'ct-settings-language.detail.snippetUpdates.updating',
            updateAvailable: 'ct-settings-language.detail.snippetUpdates.updateAvailable',
            upToDate: 'ct-settings-language.detail.snippetUpdates.upToDate',
        }[snippetUpdateState.value] ?? 'ct-settings-language.detail.snippetUpdates.upToDate'
    );
});
const showSnippetUpdateButton = computed(() => {
    return [
        'notLinked',
        'linking',
        'updateAvailable',
        'updating',
    ].includes(snippetUpdateState.value);
});
const snippetUpdateButtonLabel = computed(() => {
    return (
        {
            notLinked: 'ct-settings-language.detail.snippetUpdates.linkButton',
            linking: 'ct-settings-language.detail.snippetUpdates.linkingButton',
            updateAvailable: 'ct-settings-language.detail.snippetUpdates.updateButton',
            updating: 'ct-settings-language.detail.snippetUpdates.updatingButton',
        }[snippetUpdateState.value] ?? 'ct-settings-language.detail.snippetUpdates.updateButton'
    );
});
const showSnippetAutoUpdate = computed(() => {
    return [
        'upToDate',
        'updateAvailable',
        'updating',
    ].includes(snippetUpdateState.value);
});

const createdComponent = () => {
    if (!props.languageId) {
        Contena.Store.get('context').resetLanguageToDefault();
        language.value = languageRepository.value.create();

        return;
    }

    loadEntityData()
        .then(() => {
            return loadCustomFieldSets();
        })
        .then(() => {
            languageRepository.value.search(usedLocaleCriteria.value).then((data) => {
                usedTranslationIds.value = data.aggregations.usedTranslationIds.buckets.map((item) => item.key);
            });
        });
};
const loadEntityData = () => {
    isLoading.value = true;
    const criteria = new Criteria(1, 1);
    criteria.addAssociation('locale');

    return languageRepository.value
        .get(props.languageId, Contena.Context.api, criteria)
        .then((languageValue) => {
            isLoading.value = false;
            language.value = languageValue;

            if (languageValue.parentId) {
                setParentTranslationCodeId(languageValue.parentId);
            }

            void loadSnippetMetadata();
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const loadSnippetMetadata = () => {
    const localeCode = language.value?.locale?.code;

    if (!localeCode) {
        snippetMetadata.value = null;

        return Promise.resolve();
    }

    isSnippetMetadataLoading.value = true;

    return Promise.all([
        translationService.getList(),
        translationService.getMeta(),
    ])
        .then(
            ([
                listResponse,
                metaResponse,
            ]) => {
                builtInLocales.value = metaResponse?.builtInLocales ?? builtInLocales.value;
                snippetMetadata.value = (listResponse?.items ?? []).find((item) => item.locale === localeCode) ?? null;
            },
        )
        .catch(() => {
            snippetMetadata.value = null;
            createNotificationError({
                message: t('ct-settings-language.detail.snippetUpdates.statusLoadError'),
            });
        })
        .finally(() => {
            isSnippetMetadataLoading.value = false;
        });
};
const onUpdateSnippets = () => {
    const localeCode = language.value?.locale?.code;

    if (!localeCode) {
        return;
    }

    isUpdatingSnippets.value = true;

    return translationService
        .install({ locales: [localeCode], activate: true })
        .then(() => loadSnippetMetadata())
        .catch(() => {
            createNotificationError({
                message: t('ct-settings-language.detail.snippetUpdates.updateError'),
            });
        })
        .finally(() => {
            isUpdatingSnippets.value = false;
        });
};
const loadCustomFieldSets = () => {
    return customFieldDataProviderService.getCustomFieldSets('language').then((sets) => {
        customFieldSets.value = sets;
    });
};
const checkTranslationCodeInheritance = (value) => {
    return value === parentTranslationCodeId.value;
};
const setParentTranslationCodeId = (parentId) => {
    languageRepository.value.get(parentId, Contena.Context.api).then((parentLanguage) => {
        parentTranslationCodeId.value = parentLanguage.translationCodeId;
    });
};
const onInputLanguage = (parentId) => {
    if (parentId) {
        setParentTranslationCodeId(parentId);
    }

    const origin = language.value.getOrigin();
    if (language.value.isNew() || !origin.parentId) {
        return;
    }

    showAlertForChangeParentLanguage.value = origin.parentId !== language.value.parentId;
};
const isLocaleAlreadyUsed = (itemId) => {
    return usedTranslationIds.value.some((localeId) => {
        return itemId === localeId;
    });
};
const onSave = () => {
    isLoading.value = true;

    languageRepository.value
        .save(language.value)
        .then(() => {
            isLoading.value = false;
            isSaveSuccessful.value = true;

            if (!props.languageId) {
                void router.push({
                    name: 'ct.settings.language.detail',
                    params: { id: language.value.id },
                });
            }
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const onCancel = () => {
    void router.push({ name: 'ct.settings.language.index' });
};

watch(() => props.languageId, createdComponent);

createdComponent();

swDefinePublic({
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    translationService,
    language,
    usedTranslationIds,
    showAlertForChangeParentLanguage,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    parentTranslationCodeId,
    snippetMetadata,
    builtInLocales,
    isUpdatingSnippets,
    isSnippetMetadataLoading,
    identifier,
    languageRepository,
    isIsoCodeRequired,
    languageHasName,
    isNewLanguage,
    usedLocaleCriteria,
    allowSave,
    tooltipSave,
    parentLanguageCriteria,
    isSystemDefaultLanguageId,
    inheritanceTooltipText,
    showCustomFields,
    languageLocaleIdError,
    languageNameError,
    snippetUpdateState,
    snippetUpdatesLabel,
    showSnippetUpdateButton,
    snippetUpdateButtonLabel,
    showSnippetAutoUpdate,
    createdComponent,
    loadEntityData,
    loadCustomFieldSets,
    loadSnippetMetadata,
    onUpdateSnippets,
    checkTranslationCodeInheritance,
    setParentTranslationCodeId,
    onInputLanguage,
    isLocaleAlreadyUsed,
    onSave,
    onCancel,
});
usePageTitle(() => identifier.value);

defineExpose({
    repositoryFactory,
    acl,
    customFieldDataProviderService,
    translationService,
    language,
    usedTranslationIds,
    showAlertForChangeParentLanguage,
    isLoading,
    isSaveSuccessful,
    customFieldSets,
    parentTranslationCodeId,
    snippetMetadata,
    builtInLocales,
    isUpdatingSnippets,
    isSnippetMetadataLoading,
    identifier,
    languageRepository,
    isIsoCodeRequired,
    languageHasName,
    isNewLanguage,
    usedLocaleCriteria,
    allowSave,
    tooltipSave,
    parentLanguageCriteria,
    isSystemDefaultLanguageId,
    inheritanceTooltipText,
    showCustomFields,
    languageLocaleIdError,
    languageNameError,
    snippetUpdateState,
    snippetUpdatesLabel,
    showSnippetUpdateButton,
    snippetUpdateButtonLabel,
    showSnippetAutoUpdate,
    createdComponent,
    loadEntityData,
    loadCustomFieldSets,
    loadSnippetMetadata,
    onUpdateSnippets,
    checkTranslationCodeInheritance,
    setParentTranslationCodeId,
    onInputLanguage,
    isLocaleAlreadyUsed,
    onSave,
    onCancel,
});
</script>
