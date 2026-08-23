<template>
    <ct-block name="sw_settings_list">
        <ct-block name="sw_settings_language_index">
            <ct-page class="ct-settings-language-list">
                <template #search-bar>
                    <ct-block name="sw_settings_language_list_search_bar">
                        <mt-search
                            :placeholder="$t('ct-settings-language.general.placeholderSearchBar')"
                            :model-value="term"
                            @change="onSearch"
                        />
                    </ct-block>
                </template>

                <template #smart-bar-header>
                    <ct-block name="sw_settings_language_list_smart_bar_header">
                        <ct-block name="sw_settings_language_list_smart_bar_header_title">
                            <h2>
                                <ct-block name="sw_settings_language_list_smart_bar_header_title_text">
                                    <span>{{ $t('ct-settings.index.title') }}</span>

                                    <mt-icon name="regular-chevron-right-xs" size="12px" />

                                    <span>{{ $t('ct-settings-language.list.textHeadline') }}</span>
                                </ct-block>
                            </h2>
                        </ct-block>
                    </ct-block>
                </template>

                <template #smart-bar-actions>
                    <ct-block name="sw_settings_language_list_smart_bar_actions">
                        <ct-block name="sw_settings_language_list_smart_bar_actions_update_snippets">
                            <mt-button
                                v-if="updatableLocales.length"
                                class="ct-settings-language-list__button-update-snippets"
                                variant="secondary"
                                size="default"
                                :is-loading="isUpdatingSnippets"
                                @click="onUpdateAllSnippets"
                            >
                                {{ $t('ct-settings-language.list.buttonUpdateAllSnippets') }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="sw_settings_language_list_smart_bar_actions_add">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: allowCreate,
                                    showOnDisabledElements: true,
                                }"
                                class="ct-settings-language-list__button-create"
                                variant="primary"
                                :disabled="!allowCreate || undefined"
                                size="default"
                                @click="showAddLanguageModal = true"
                            >
                                {{ $t('ct-settings-language.list.buttonAddLanguage') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>

                <template #content>
                    <ct-block name="sw_settings_language_list_content">
                        <ct-card-view>
                            <div class="ct-settings-language-list__content">
                                <div class="ct-settings-language-list__snippet-link-wrapper">
                                    <mt-link
                                        class="ct-settings-language-list__snippet-link"
                                        :to="{ name: 'ct.settings.snippet.index' }"
                                        type="internal"
                                    >
                                        {{ $t('ct-settings-language.list.manageSnippets') }}
                                    </mt-link>
                                </div>

                                <mt-card
                                    class="ct-settings-language-list__card"
                                    position-identifier="ct-settings-language-list"
                                    :title="cardTitle"
                                    :subtitle="$t('ct-settings-language.list.cardSubtitle')"
                                    large
                                >
                                    <template #grid>
                                        <ct-entity-listing
                                            key="language-listing"
                                            ref="languageGrid"
                                            class="ct-settings-language-list-grid"
                                            identifier="ct-settings-language-list"
                                            detail-route="ct.settings.language.detail"
                                            :is-loading="isLoading"
                                            :repository="languageRepository"
                                            :columns="getColumns"
                                            :data-source="languages"
                                            :sort-by="sortBy"
                                            :sort-direction="sortDirection"
                                            :disable-data-fetching="true"
                                            :allow-view="allowView || undefined"
                                            :allow-edit="allowEdit || undefined"
                                            :allow-inline-edit="allowInlineEdit || undefined"
                                            :allow-delete="false"
                                            :full-page="false"
                                            @column-sort="onSortColumn"
                                            @page-change="onPageChange"
                                            @inline-edit-save="onInlineEditSave"
                                            @selection-change="onSelectionChange"
                                        >
                                            <!-- ct-block preserves the complete listing slot scope for extensions. -->
                                            <!-- eslint-disable vue/no-unused-vars -->
                                            <template #column-name="{ item, column, compact, isInlineEdit }">
                                                <ct-block name="sw_settings_language_list_content_list_column_name">
                                                    <mt-text-field
                                                        v-if="isInlineEdit"
                                                        v-model="item.name"
                                                        :size="compact ? 'small' : 'default'"
                                                    />

                                                    <mt-link
                                                        v-else
                                                        :to="{
                                                            name: 'ct.settings.language.detail',
                                                            params: { id: item.id },
                                                        }"
                                                    >
                                                        {{ item.name }}
                                                    </mt-link>

                                                    <mt-badge
                                                        v-if="isDefault(item.id) && !isInlineEdit"
                                                        variant="neutral"
                                                        size="s"
                                                        class="ct-settings-language-list__default-label"
                                                    >
                                                        {{ $t('ct-settings-language.list.defaultLabel') }}
                                                    </mt-badge>
                                                </ct-block>
                                            </template>

                                            <template #column-active="{ item, isInlineEdit }">
                                                <template v-if="isInlineEdit && !isDefault(item.id)">
                                                    <mt-checkbox v-model:checked="item.active" />
                                                </template>

                                                <template v-else>
                                                    <mt-icon
                                                        v-if="item.active"
                                                        name="regular-checkmark-xs"
                                                        size="12px"
                                                        class="is--active"
                                                    />

                                                    <mt-icon
                                                        v-else
                                                        name="regular-times-s"
                                                        size="12px"
                                                        class="is--inactive"
                                                    />
                                                </template>
                                            </template>

                                            <template #column-locale="{ item, column, compact, isInlineEdit }">
                                                <ct-block name="sw_settings_language_list_content_list_column_locale">
                                                    {{ item.locale.translated.name }}, {{ item.locale.translated.territory }}
                                                </ct-block>
                                            </template>

                                            <template #column-channels="{ item }">
                                                <ct-block name="sw_settings_language_list_content_list_column_channels">
                                                    {{ channelLabel(item) }}
                                                </ct-block>
                                            </template>

                                            <template #column-snippetStatus="{ item }">
                                                <ct-block
                                                    name="sw_settings_language_list_content_list_column_snippet_status"
                                                >
                                                    <mt-badge
                                                        v-if="getSnippetStatus(item)"
                                                        class="ct-settings-language-list__snippet-status"
                                                        :variant="snippetStatusConfig[getSnippetStatus(item)].variant"
                                                        :status-indicator="
                                                            snippetStatusConfig[getSnippetStatus(item)].statusIndicator
                                                        "
                                                        size="s"
                                                    >
                                                        {{ $t(snippetStatusConfig[getSnippetStatus(item)].label) }}
                                                    </mt-badge>
                                                </ct-block>
                                            </template>

                                            <template #column-parent="{ item, column, compact, isInlineEdit }">
                                                <ct-block name="sw_settings_language_list_content_list_column_parent">
                                                    {{ getParentName(item) }}
                                                </ct-block>
                                            </template>

                                            <template #more-actions="{ item }">
                                                <ct-block
                                                    name="sw_settings_language_list_content_list_update_snippets_action"
                                                >
                                                    <ct-context-menu-item
                                                        v-if="getSnippetStatus(item) === 'updateAvailable'"
                                                        class="ct-settings-language-list__update-snippets-action"
                                                        :disabled="!allowEdit || undefined"
                                                        @click="onUpdateSnippets(item)"
                                                    >
                                                        {{ $t('ct-settings-language.list.contextMenuUpdateSnippets') }}
                                                    </ct-context-menu-item>
                                                </ct-block>
                                            </template>

                                            <template #delete-action="{ item }">
                                                <ct-block name="sw_settings_language_list_content_list_delete_action">
                                                    <ct-context-menu-item
                                                        v-tooltip.bottom="tooltipDelete(item.id)"
                                                        class="ct-settings-language-list__delete-action"
                                                        variant="danger"
                                                        :disabled="isDefault(item.id) || !allowDelete || undefined"
                                                        @click="openDeleteModal([item])"
                                                    >
                                                        {{ $t('global.default.delete') }}
                                                    </ct-context-menu-item>
                                                </ct-block>
                                            </template>

                                            <template #bulk-additional>
                                                <ct-block name="sw_settings_language_list_content_list_bulk">
                                                    <a
                                                        v-if="selectedUpdatableLocales.length"
                                                        class="ct-settings-language-list__bulk-update-snippets link link-primary"
                                                        role="button"
                                                        tabindex="0"
                                                        @click="onUpdateSelectedSnippets"
                                                        @keydown.enter="onUpdateSelectedSnippets"
                                                    >
                                                        {{ $t('ct-settings-language.list.buttonUpdateSelectedSnippets') }}
                                                    </a>

                                                    <a
                                                        v-if="allowDelete && bulkDeleteLanguages.length"
                                                        class="ct-settings-language-list__bulk-delete link link-danger"
                                                        role="button"
                                                        tabindex="0"
                                                        @click="openDeleteModal(bulkDeleteLanguages)"
                                                        @keydown.enter="openDeleteModal(bulkDeleteLanguages)"
                                                    >
                                                        {{ $t('global.default.delete') }}
                                                    </a>
                                                </ct-block>
                                            </template>
                                            <!-- eslint-enable vue/no-unused-vars -->
                                        </ct-entity-listing>
                                    </template>
                                </mt-card>
                            </div>
                        </ct-card-view>

                        <ct-block name="sw_settings_language_list_add_modal">
                            <ct-settings-language-add-modal
                                v-if="showAddLanguageModal"
                                @language-added="onLanguageAdded"
                                @close="showAddLanguageModal = false"
                            />
                        </ct-block>

                        <ct-block name="sw_settings_language_list_delete_modal">
                            <ct-modal
                                v-if="showDeleteModal"
                                class="ct-settings-language-list__delete-modal"
                                :title="$t('ct-settings-language.list.deleteModalTitle')"
                                variant="default"
                                @modal-close="closeDeleteModal"
                            >
                                <ct-block name="sw_settings_language_list_delete_modal_content">
                                    <p class="ct-settings-language-list__delete-modal-intro">
                                        {{ $t('ct-settings-language.list.deleteModalIntro') }}
                                    </p>

                                    <ul class="ct-settings-language-list__delete-modal-list">
                                        <li v-for="language in sortedDeleteCandidates" :key="language.id">
                                            {{ language.name }}
                                        </li>
                                    </ul>

                                    <template v-if="deleteCandidateInstalledLocales.length">
                                        <p class="ct-settings-language-list__delete-modal-note">
                                            <strong>{{ $t('ct-settings-language.list.deleteFilesNoteLabel') }}</strong>
                                            {{ $t('ct-settings-language.list.deleteFilesNote') }}
                                        </p>

                                        <mt-checkbox
                                            v-model:checked="deleteTranslationFiles"
                                            class="ct-settings-language-list__delete-files-option"
                                            :label="$t('ct-settings-language.list.deleteTranslationFiles')"
                                        />
                                    </template>
                                </ct-block>

                                <template #modal-footer>
                                    <ct-block name="sw_settings_language_list_delete_modal_footer">
                                        <mt-button size="small" variant="secondary" @click="closeDeleteModal">
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>

                                        <mt-button
                                            size="small"
                                            variant="critical"
                                            :is-loading="isDeleting"
                                            @click="confirmDelete"
                                        >
                                            {{ $t('global.default.delete') }}
                                        </mt-button>
                                    </ct-block>
                                </template>
                            </ct-modal>
                        </ct-block>
                    </ct-block>
                </template>

                <template #sidebar>
                    <ct-block name="sw_settings_language_list_grid_sidebar">
                        <ct-sidebar>
                            <ct-block name="sw_settings_language_list_grid_sidebar_refresh">
                                <ct-sidebar-item
                                    icon="regular-undo"
                                    :title="$t('ct-settings-language.list.titleSidebarItemRefresh')"
                                    @click="onRefresh"
                                />
                            </ct-block>
                        </ct-sidebar>
                    </ct-block>
                </template>
            </ct-page>
        </ct-block>
    </ct-block>
</template>

<script setup>
import { useSnackbar } from '@contena/meteor-component-library';
import './ct-settings-language-list.scss';
const { Criteria } = Contena.Data;

defineProps({});

import { ref, computed, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

const router = useRouter();
const { t } = useI18n();
const { page, limit, term, onPageChange, onSearch, onSortColumn, initializeListing } = useListing();
const { createNotificationSuccess, createNotificationError } = useNotification();

const $t = t;

const languageGrid = ref(null);

const repositoryFactory = inject('repositoryFactory');
const translationService = inject('translationService');
const acl = inject('acl');

const languages = ref(null);
const parentLanguages = ref(null);
const translationMetadata = ref({});
const total = ref(0);
const isLoading = ref(true);
const sortBy = ref('active');
const sortDirection = ref('DESC');
const showAddLanguageModal = ref(false);
const updatingLocales = ref([]);
const snippetSelection = ref({});
const builtInLocales = ref([]);
const showDeleteModal = ref(false);
const deleteCandidates = ref([]);
const deleteTranslationFiles = ref(true);
const isDeleting = ref(false);

const snackbar = computed(() => {
    return useSnackbar();
});
const selectedUpdatableLocales = computed(() => {
    return Object.values(snippetSelection.value)
        .map((language) => language.locale?.code)
        .filter((localeCode) => translationMetadata.value[localeCode]?.updateAvailable);
});
const selectedLanguages = computed(() => {
    return Object.values(snippetSelection.value);
});
const bulkDeleteLanguages = computed(() => {
    return selectedLanguages.value.filter((language) => !isDefault(language.id));
});
const deleteCandidateInstalledLocales = computed(() => {
    return deleteCandidates.value
        .map((language) => language.locale?.code)
        .filter((localeCode) => isLocaleInstalled(localeCode));
});
const sortedDeleteCandidates = computed(() => {
    return [...deleteCandidates.value].sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
});
const updatableLocales = computed(() => {
    return Object.values(translationMetadata.value)
        .filter((entry) => entry.updateAvailable)
        .map((entry) => entry.locale);
});
const isUpdatingSnippets = computed(() => {
    return updatingLocales.value.length > 0;
});
const listingCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);
    criteria.addAssociation('locale');
    criteria.addAssociation('translationCode');
    criteria.addAssociation('channels');

    if (sortBy.value) {
        // eslint-disable-next-line vue/no-side-effects-in-computed-properties
        criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));
    }

    if (sortBy.value !== 'name') {
        // eslint-disable-next-line vue/no-side-effects-in-computed-properties
        criteria.addSorting(Criteria.sort('name', 'ASC'));
    }

    return criteria;
});
const languageRepository = computed(() => {
    return repositoryFactory.create('language');
});
const getColumns = computed(() => {
    return [
        {
            property: 'name',
            label: 'ct-settings-language.list.columnName',
            dataIndex: 'name',
            inlineEdit: true,
        },
        {
            property: 'parent',
            label: 'ct-settings-language.list.columnInherit',
            sortable: false,
            visible: false,
        },
        {
            property: 'locale',
            dataIndex: 'locale.id',
            label: 'ct-settings-language.list.columnLocaleName',
        },
        {
            property: 'translationCode.code',
            label: 'ct-settings-language.list.columnIsoCode',
        },
        {
            property: 'channels',
            label: 'ct-settings-language.list.columnChannels',
            sortable: false,
        },
        {
            property: 'snippetStatus',
            label: 'ct-settings-language.list.columnSnippetStatus',
            sortable: false,
        },
        {
            property: 'active',
            dataIndex: 'active',
            label: 'ct-settings-language.list.columnActive',
            inlineEdit: 'boolean',
            align: 'center',
        },
    ];
});
const allowCreate = computed(() => {
    return acl.can('language.creator');
});
const allowView = computed(() => {
    return acl.can('language.viewer');
});
const allowEdit = computed(() => {
    return acl.can('language.editor');
});
const allowInlineEdit = computed(() => {
    return acl.can('language.editor');
});
const allowDelete = computed(() => {
    return acl.can('language.deleter');
});
const cardTitle = computed(() => {
    return `${t('ct-settings-language.list.cardTitle')} (${total.value})`;
});
const snippetStatusConfig = computed(() => {
    return {
        updateAvailable: {
            variant: 'info',
            statusIndicator: true,
            label: 'ct-settings-language.list.snippetStatus.updateAvailable',
        },
        updating: {
            variant: 'info',
            statusIndicator: true,
            label: 'ct-settings-language.list.snippetStatus.updating',
        },
    };
});

const createdComponent = () => {
    void loadTranslationMetadata();
};
const onRefresh = () => {
    getList();
    void loadTranslationMetadata();
};
const getList = () => {
    isLoading.value = true;
    return languageRepository.value.search(listingCriteria.value).then((languageResult) => {
        total.value = languageResult.total || total.value;

        const parentCriteria = new Criteria(1, limit.value);
        const parentIds = {};

        languageResult.forEach((language) => {
            if (language.parentId) {
                parentIds[language.parentId] = true;
            }
        });

        parentCriteria.setIds(Object.keys(parentIds));
        return languageRepository.value.search(parentCriteria).then((parentResult) => {
            languages.value = languageResult;
            parentLanguages.value = parentResult;
            isLoading.value = false;
        });
    });
};
const loadTranslationMetadata = async () => {
    const [
        listResponse,
        metaResponse,
    ] = await Promise.all([
        translationService.getList().catch(() => null),
        translationService.getMeta().catch(() => null),
    ]);

    if (listResponse === null || metaResponse === null) {
        createNotificationError({
            message: t('ct-settings-language.list.snippetStatusLoadError'),
        });
    }

    translationMetadata.value = (listResponse?.items ?? []).reduce((map, entry) => {
        map[entry.locale] = entry;
        return map;
    }, {});

    builtInLocales.value = metaResponse?.builtInLocales ?? builtInLocales.value;
};
const channelLabel = (item) => {
    const count = item.channels?.length ?? 0;

    if (count === 0) {
        return '';
    }

    return t('ct-settings-language.list.channelCount', count);
};
const getSnippetStatus = (item) => {
    const localeCode = item.locale?.code;

    // Built-in languages ship with Contena and never receive snippet updates.
    if (builtInLocales.value.includes(localeCode)) {
        return null;
    }

    if (!translationMetadata.value[localeCode]?.updateAvailable) {
        return null;
    }

    if (updatingLocales.value.includes(localeCode)) {
        return 'updating';
    }

    return 'updateAvailable';
};
const onLanguageAdded = async (locale) => {
    showAddLanguageModal.value = false;

    const criteria = new Criteria(1, 1);
    criteria.addAssociation('locale');
    criteria.addFilter(Criteria.equals('locale.code', locale));

    const language = (await languageRepository.value.search(criteria)).first();

    if (language) {
        void router.push({
            name: 'ct.settings.language.detail',
            params: { id: language.id },
            query: { languageCreated: 'true' },
        });

        return;
    }

    await Promise.all([
        getList(),
        loadTranslationMetadata(),
    ]);
};
const onUpdateAllSnippets = async () => {
    // Re-check the current server state first so languages removed in the meantime are not re-created.
    await loadTranslationMetadata();

    return runSnippetUpdate(updatableLocales.value);
};
const onUpdateSnippets = async (item) => {
    const localeCode = item.locale?.code;

    if (!localeCode) {
        return;
    }

    updatingLocales.value.push(localeCode);

    try {
        await translationService.install({
            locales: [localeCode],
            activate: true,
        });
        createNotificationSuccess({
            message: t('ct-settings-language.list.updateSnippetsSuccess'),
        });
        await loadTranslationMetadata();
    } catch {
        createNotificationError({
            message: t('ct-settings-language.list.updateSnippetsError'),
        });
    } finally {
        updatingLocales.value = updatingLocales.value.filter((code) => code !== localeCode);
    }
};
const onSelectionChange = (selection) => {
    snippetSelection.value = selection;
};
const buildSnippetProgressSnackbar = (processed, total) => {
    return {
        message: t('ct-settings-language.list.updateSnippetsProgress', { processed, total }),
        variant: 'progress',
        progressPercentage: Math.round((processed / total) * 100),
        duration: 0,
    };
};
const onUpdateSelectedSnippets = () => {
    return runSnippetUpdate(selectedUpdatableLocales.value);
};
const runSnippetUpdate = async (locales) => {
    if (!locales.length) {
        return;
    }
    const total = locales.length;
    const failed = [];
    let processed = 0;
    updatingLocales.value.push(...locales);
    const snackbarValue = snackbar.value.addSnackbar(buildSnippetProgressSnackbar(processed, total));
    for (const locale of locales) {
        try {
            await translationService.install({ locales: [locale], activate: true });
        } catch {
            failed.push(locale);
        }

        processed += 1;
        Object.assign(snackbarValue, buildSnippetProgressSnackbar(processed, total));
    }
    Object.assign(snackbarValue, {
        uploadState: failed.length ? 'error' : 'success',
        successMessage: t('ct-settings-language.list.updateSnippetsSuccess'),
        errorMessage: failed.length ? t('ct-settings-language.list.updateSnippetsError') : undefined,
    });
    updatingLocales.value = updatingLocales.value.filter((localeCode) => !locales.includes(localeCode));
    snippetSelection.value = {};
    languageGrid.value?.resetSelection();
    await loadTranslationMetadata();
};
const getParentName = (item) => {
    if (!item.parentId) {
        return '';
    }

    return parentLanguages.value?.get(item.parentId)?.name ?? '';
};
const isDefault = (languageId) => {
    return Contena.Context.api.systemLanguageId ? Contena.Context.api.systemLanguageId.includes(languageId) : false;
};
const tooltipDelete = (languageId) => {
    if (!acl.can('language.deleter') && !isDefault(languageId)) {
        return {
            message: t('ct-privileges.tooltip.warning'),
            disabled: acl.can('language.deleter'),
            showOnDisabledElements: true,
        };
    }

    return {
        message: '',
        disabled: true,
    };
};
const onInlineEditSave = (promise) => {
    promise.then(() => {
        invalidateLanguageCaches();
    });
};
const invalidateLanguageCaches = () => {
    Contena.Service('cacheService').invalidateCaches({
        cacheKey: [
            'shared-data',
            'active-languages',
        ],
    });
};
const isLocaleInstalled = (localeCode) => {
    return Boolean(localeCode && translationMetadata.value[localeCode]?.lastUpdate);
};
const openDeleteModal = (languages) => {
    deleteCandidates.value = languages;
    deleteTranslationFiles.value = true;
    showDeleteModal.value = true;
};
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteCandidates.value = [];
};
const confirmDelete = async () => {
    const ids = deleteCandidates.value.map((language) => language.id);

    if (ids.length === 0) {
        closeDeleteModal();

        return;
    }

    const locales = deleteTranslationFiles.value ? deleteCandidateInstalledLocales.value : [];

    isDeleting.value = true;

    try {
        await languageRepository.value.syncDeleted(ids, Contena.Context.api);
        await removeTranslationFiles(locales);
    } catch {
        createNotificationError({
            message: t('ct-settings-language.list.deleteError'),
        });
    } finally {
        isDeleting.value = false;
        showDeleteModal.value = false;
        deleteCandidates.value = [];
        snippetSelection.value = {};
        languageGrid.value?.resetSelection();

        invalidateLanguageCaches();
        await getList();
        await loadTranslationMetadata();
    }
};
const removeTranslationFiles = async (locales) => {
    for (const localeCode of locales) {
        await translationService.deleteTranslation(localeCode).catch(() => {
            createNotificationError({
                message: t('ct-settings-language.list.deleteTranslationFilesError'),
            });
        });
    }
};

initializeListing({
    getList,
    total,
    sortBy,
    sortDirection,
});

createdComponent();

swDefinePublic({
    repositoryFactory,
    translationService,
    acl,
    languages,
    parentLanguages,
    translationMetadata,
    total,
    isLoading,
    sortBy,
    sortDirection,
    showAddLanguageModal,
    updatingLocales,
    snippetSelection,
    builtInLocales,
    showDeleteModal,
    deleteCandidates,
    deleteTranslationFiles,
    isDeleting,
    snackbar,
    selectedUpdatableLocales,
    selectedLanguages,
    bulkDeleteLanguages,
    deleteCandidateInstalledLocales,
    sortedDeleteCandidates,
    updatableLocales,
    isUpdatingSnippets,
    listingCriteria,
    languageRepository,
    getColumns,
    allowCreate,
    allowView,
    allowEdit,
    allowInlineEdit,
    allowDelete,
    cardTitle,
    snippetStatusConfig,
    createdComponent,
    onRefresh,
    getList,
    loadTranslationMetadata,
    channelLabel,
    getSnippetStatus,
    onLanguageAdded,
    onUpdateAllSnippets,
    onUpdateSnippets,
    onSelectionChange,
    buildSnippetProgressSnackbar,
    onUpdateSelectedSnippets,
    runSnippetUpdate,
    getParentName,
    isDefault,
    tooltipDelete,
    onInlineEditSave,
    invalidateLanguageCaches,
    isLocaleInstalled,
    openDeleteModal,
    closeDeleteModal,
    confirmDelete,
    removeTranslationFiles,
});
usePageTitle();

defineExpose({
    repositoryFactory,
    translationService,
    acl,
    languages,
    parentLanguages,
    translationMetadata,
    total,
    isLoading,
    sortBy,
    sortDirection,
    showAddLanguageModal,
    updatingLocales,
    snippetSelection,
    builtInLocales,
    showDeleteModal,
    deleteCandidates,
    deleteTranslationFiles,
    isDeleting,
    snackbar,
    selectedUpdatableLocales,
    selectedLanguages,
    bulkDeleteLanguages,
    deleteCandidateInstalledLocales,
    sortedDeleteCandidates,
    updatableLocales,
    isUpdatingSnippets,
    listingCriteria,
    languageRepository,
    getColumns,
    allowCreate,
    allowView,
    allowEdit,
    allowInlineEdit,
    allowDelete,
    cardTitle,
    snippetStatusConfig,
    createdComponent,
    onRefresh,
    getList,
    loadTranslationMetadata,
    channelLabel,
    getSnippetStatus,
    onLanguageAdded,
    onUpdateAllSnippets,
    onUpdateSnippets,
    onSelectionChange,
    buildSnippetProgressSnackbar,
    onUpdateSelectedSnippets,
    runSnippetUpdate,
    getParentName,
    isDefault,
    tooltipDelete,
    onInlineEditSave,
    invalidateLanguageCaches,
    isLocaleInstalled,
    openDeleteModal,
    closeDeleteModal,
    confirmDelete,
    removeTranslationFiles,
});
</script>
