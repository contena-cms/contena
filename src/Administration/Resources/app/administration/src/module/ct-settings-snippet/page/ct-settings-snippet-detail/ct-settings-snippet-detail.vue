<template>
    <ct-block name="sw_settings_snippet_detail">
        <ct-page class="ct-settings-snippet-detail">
            <template #smart-bar-back>
                <router-link v-slot="{ href, navigate }" :to="backPath" custom>
                    <mt-button
                        is="a"
                        class="smart-bar__back-btn"
                        variant="secondary"
                        square
                        :href="href"
                        :aria-label="t('global.ct-page.backButton')"
                        :title="t('global.ct-page.backButton')"
                        @click="navigate"
                    >
                        <mt-icon name="regular-long-arrow-left" size="16px" />
                    </mt-button>
                </router-link>
            </template>

            <template #smart-bar-header>
                <h2>{{ translationKey || t('ct-settings-snippet.detail.textHeadline') }}</h2>
            </template>

            <template #smart-bar-actions>
                <mt-button variant="secondary" :disabled="isLoading || undefined" @click="onCancel">
                    {{ t('global.default.cancel') }}
                </mt-button>
                <mt-button
                    class="ct-snippet-detail__save-action"
                    variant="primary"
                    :is-loading="isLoading"
                    :disabled="!acl.can('snippet.editor') || isLoading || !translationKey || !isSaveable || undefined"
                    @click="onSave"
                >
                    {{ t('global.default.save') }}
                </mt-button>
            </template>

            <template #content>
                <ct-block name="sw_settings_snippet_detail_content">
                    <div class="ct-settings-snippet-set-detail-card">
                        <ct-block name="sw_settings_snippet_set_detail_card_information">
                            <mt-card
                                position-identifier="ct-settings-snippet-detail-information"
                                :title="t('ct-settings-snippet.detail.cardGeneralTitle')"
                                :is-loading="isLoading"
                            >
                                <mt-text-field
                                    v-model="translationKey"
                                    name="ct-field--translationKey"
                                    :label="t('ct-settings-snippet.detail.labelName')"
                                    :placeholder="t('ct-settings-snippet.detail.labelNamePlaceholder')"
                                    :disabled="!(isCreate || isAddedSnippet) || undefined"
                                    :error="invalidKeyError"
                                    @update:model-value="onChange(null, $event)"
                                />
                            </mt-card>
                        </ct-block>

                        <ct-block name="sw_settings_snippet_set_detail_card_snippets">
                            <mt-card
                                position-identifier="ct-settings-snippet-detail-snippets"
                                :title="t('ct-settings-snippet.detail.cardSnippetSetsTitle')"
                                :is-loading="isLoading || isLoadingSnippets"
                            >
                                <div class="snippet-overview-card">
                                    <ct-block name="sw_settings_snippet_set_detail_card_snippets_fields">
                                        <mt-text-field
                                            v-for="(snippet, index) in snippets"
                                            :key="snippet.setId"
                                            :model-value="
                                                snippetStates[snippet.setId] === 'inherited' ? null : snippet.value
                                            "
                                            name="ct-field--snippet-value"
                                            :class="`ct-settings-snippet-detail__translation-field--${index}`"
                                            :disabled="!acl.can('snippet.editor') || isLoadingSnippets || undefined"
                                            :label="
                                                t('ct-settings-snippet.detail.labelContent', {
                                                    name: getSetName(snippet.setId),
                                                })
                                            "
                                            :placeholder="getPlaceholder(snippet)"
                                            :is-inherited="snippetStates[snippet.setId] === 'inherited'"
                                            :is-inheritance-field="snippet._hasFileValue"
                                            @inheritance-restore="onResetSnippet(snippet)"
                                            @inheritance-remove="onRemoveInheritance(snippet)"
                                            @update:model-value="onChange(snippet, $event)"
                                        >
                                            <template
                                                v-if="
                                                    snippetStates[snippet.setId] === 'overridden' ||
                                                    snippetStates[snippet.setId] === 'overriding'
                                                "
                                                #hint
                                            >
                                                <span class="ct-settings-snippet-detail__translation-original-label">
                                                    {{ t('ct-settings-snippet.detail.labelOriginal') }}
                                                </span>
                                                <span class="ct-settings-snippet-detail__translation-original-value">
                                                    {{ snippet.resetTo || snippet.origin }}
                                                </span>
                                            </template>
                                        </mt-text-field>
                                    </ct-block>
                                </div>
                            </mt-card>
                        </ct-block>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, $TSFixMe */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type SnippetSetApiService from 'src/core/service/api/snippet-set.api.service';
import type { SnippetListItem } from 'src/core/service/api/snippet-set.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-snippet-detail.scss';

type EditableSnippet = Entity<'snippet'> &
    SnippetListItem & {
        _isNew?: boolean;
        _overriding: boolean;
        _savedValue: string | null;
        _pendingDelete: boolean;
        _hasFileValue: boolean;
    };
type SnippetState = 'custom' | 'empty' | 'inherited' | 'overridden' | 'overriding';

defineOptions({
    metaInfo() {
        return { title: this.$createTitle(this.translationKey) };
    },
});

defineProps({});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const snippetSetService = inject<SnippetSetApiService>('snippetSetService');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!snippetSetService || !repositoryFactory || !acl) {
    throw new Error('The snippet detail services are unavailable.');
}

const snippetRepository = computed(() => repositoryFactory.create('snippet'));
const snippetSetRepository = computed(() => repositoryFactory.create('snippet_set'));
const isLoading = ref(true);
const isLoadingSnippets = ref(true);
const isCreate = ref(route.name === 'ct.settings.snippet.create');
const isAddedSnippet = ref(false);
const isSaveable = ref(true);
const isInvalidKey = ref(false);
const queryIds = ref(route.query.ids);
const page = ref(route.query.page);
const limit = ref(route.query.limit);
const translationKey = ref('');
const translationKeyOrigin = ref('');
const snippets = ref<EditableSnippet[]>([]);
const sets = ref<$TSFixMe>([]);
const isSaveSuccessful = ref(false);
const pushParams = ref<Record<string, string> | null>(null);

const snippetSetCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, null);
    criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
    return criteria;
});
const backPath = computed<RouteLocationRaw>(() => {
    if (Array.isArray(route.query.ids) ? route.query.ids.length > 0 : Boolean(route.query.ids)) {
        return {
            name: 'ct.settings.snippet.list',
            query: { ids: route.query.ids, limit: route.query.limit, page: route.query.page },
        };
    }
    return { name: 'ct.settings.snippet.index' };
});
const invalidKeyError = computed(() =>
    isInvalidKey.value
        ? new Contena.Classes.ContenaError({
              code: 'DUPLICATED_SNIPPET_KEY',
              parameters: { key: translationKey.value },
          })
        : null,
);
const currentAuthor = computed(() => `user/${Contena.Store.get('session').currentUser?.username ?? 'system'}`);
const snippetStates = computed<Record<string, SnippetState>>(() =>
    Object.fromEntries(
        snippets.value.map((snippet) => [
            snippet.setId,
            getSnippetState(snippet),
        ]),
    ),
);

const getSetName = (setId: string): string => sets.value.get?.(setId)?.name ?? '';
const prepareContent = async (): Promise<void> => {
    isLoading.value = true;
    isSaveable.value = true;
    if (!route.params.key && !isCreate.value) onNewKeyRedirect();
    translationKey.value = String(route.params.key ?? '');
    translationKeyOrigin.value = translationKey.value;
    try {
        sets.value = await snippetSetRepository.value.search(snippetSetCriteria.value, Contena.Context.api);
        isLoadingSnippets.value = true;
        await initializeSnippet();
    } finally {
        isLoading.value = false;
    }
};
const initializeSnippet = async (): Promise<void> => {
    snippets.value = createSnippetDummy();
    try {
        const response = await getCustomList();
        if (!response.total) {
            isAddedSnippet.value = true;
            return;
        }
        applySnippetsToDummies(response.data[translationKey.value]);
    } finally {
        isLoadingSnippets.value = false;
    }
};
const applySnippetsToDummies = (realSnippets: SnippetListItem[]): void => {
    snippets.value.forEach((dummySnippet) => {
        const realSnippet = realSnippets.find((snippet) => dummySnippet.setId === snippet.setId);
        if (!realSnippet) return;
        Object.assign(dummySnippet, {
            author: realSnippet.author,
            id: realSnippet.id,
            value: realSnippet.value,
            origin: realSnippet.origin,
            resetTo: realSnippet.resetTo,
            _overriding: false,
            _savedValue: null,
            _pendingDelete: false,
            _hasFileValue: realSnippet.hasFileValue,
            translationKey: realSnippet.translationKey,
            setId: realSnippet.setId,
        });
        if (realSnippet.id) dummySnippet._isNew = false;
    });
    isAddedSnippet.value = realSnippets.some((snippet) => snippet.author.startsWith('user/') || snippet.author === '');
};
const createSnippetDummy = (): EditableSnippet[] => {
    const dummies: EditableSnippet[] = [];
    sets.value.forEach((set: Entity<'snippet_set'>) => {
        const snippet = snippetRepository.value.create(Contena.Context.api) as EditableSnippet;
        Object.assign(snippet, {
            author: currentAuthor.value,
            id: null,
            value: null,
            origin: null,
            resetTo: null,
            _overriding: false,
            _savedValue: null,
            _pendingDelete: false,
            _hasFileValue: false,
            translationKey: translationKey.value,
            setId: set.id,
        });
        dummies.push(snippet);
    });
    return dummies;
};
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
    void router.push({
        name: 'ct.settings.snippet.detail',
        params: pushParams.value ?? { key: translationKey.value },
        query: { ids: queryIds.value, page: page.value, limit: limit.value },
    });
};
const onSave = async (): Promise<void> => {
    const responses: Promise<unknown>[] = [];
    isSaveSuccessful.value = false;
    isLoading.value = true;
    isSaveable.value = checkIsSaveable();

    if (!Number.isNaN(Number(translationKey.value))) {
        isLoading.value = false;
        createNotificationError({ message: t('ct-settings-snippet.detail.messageSaveErrorNumericKey') });
        return;
    }
    if (!isSaveable.value) {
        isLoading.value = false;
        createNotificationError({
            message: t('ct-settings-snippet.detail.messageSaveError', { key: translationKey.value }),
        });
        return;
    }

    snippets.value.forEach((snippet) => {
        if (!snippet.author) snippet.author = currentAuthor.value;
        if (snippet._pendingDelete) {
            responses.push(snippetRepository.value.delete(snippet.id, Contena.Context.api));
            return;
        }
        if (!Object.hasOwn(snippet, 'value') || snippet.value === null) {
            if (snippet.origin === null) return;
            snippet.value = snippet.origin;
        }
        if (snippet.translationKey !== translationKey.value) {
            snippet.translationKey = translationKey.value;
            translationKeyOrigin.value = translationKey.value;
            responses.push(snippetRepository.value.save(snippet, Contena.Context.api));
        } else if (snippet.origin !== snippet.value) {
            responses.push(snippetRepository.value.save(snippet, Contena.Context.api));
        } else if (snippet.id !== null) {
            responses.push(snippetRepository.value.delete(snippet.id, Contena.Context.api));
        }
    });

    snippets.value = [];
    try {
        await Promise.all(responses);
        onNewKeyRedirect(true);
        isSaveSuccessful.value = true;
        await prepareContent();
        saveFinish();
    } catch (error: $TSFixMe) {
        const detail = error?.response?.data?.errors?.[0]?.detail;
        createNotificationError({
            message:
                t('ct-settings-snippet.detail.messageSaveError', { key: translationKey.value }) +
                (detail ? ` ${detail}` : ''),
        });
        await prepareContent();
    } finally {
        isLoading.value = false;
    }
};
const doChange = Contena.Utils.debounce(async (): Promise<void> => {
    const response = await getCustomList();
    isSaveable.value = false;
    if (!response.total || Object.keys(response.data)[0] === translationKeyOrigin.value) {
        isSaveable.value = checkIsSaveable();
    } else {
        isInvalidKey.value = true;
    }
    if (isSaveable.value && (isCreate.value || isAddedSnippet.value)) {
        translationKey.value = translationKey.value.trim();
    }
}, 1000);
const onChange = (snippet: EditableSnippet | null, value?: string): void => {
    if (snippet) snippet.value = value ?? '';
    if (!translationKey.value || !translationKey.value.trim()) {
        isSaveable.value = false;
        isInvalidKey.value = true;
        return;
    }
    isInvalidKey.value = false;
    void doChange();
};
const onNewKeyRedirect = (isNewOrigin = false): void => {
    isSaveSuccessful.value = true;
    const params: Record<string, string> = { key: translationKey.value };
    if (isNewOrigin) params.origin = translationKey.value;
    isCreate.value = false;
    pushParams.value = params;
};
const getCustomList = () => snippetSetService.getCustomList(1, 25, { translationKey: [translationKey.value] });
const checkIsSaveable = (): boolean =>
    snippets.value.some((snippet) => {
        if (snippet._pendingDelete) return true;
        if (snippet.value === null) return false;
        return translationKey.value.trim() !== translationKeyOrigin.value || snippet.value.trim().length >= 0;
    });
const getPlaceholder = (snippet: EditableSnippet): string => {
    const emptyPlaceholder = t('ct-settings-snippet.general.placeholderValue');
    if (snippetStates.value[snippet.setId] === 'empty') return emptyPlaceholder;
    return snippet.resetTo || snippet.origin || emptyPlaceholder;
};
const getSnippetState = (snippet: EditableSnippet): SnippetState => {
    if (snippet.id !== null) {
        if (snippet._pendingDelete) return 'inherited';
        if (!snippet._hasFileValue) return snippet.value ? 'custom' : 'empty';
        return snippet.value !== null ? 'overridden' : 'inherited';
    }
    const hasFileValue = snippet._hasFileValue ?? Boolean(snippet.origin);
    if (!hasFileValue && !snippet.value) return 'empty';
    if (snippet._overriding) return 'overriding';
    if (!hasFileValue) return 'custom';
    return 'inherited';
};
const onRemoveInheritance = (snippet: EditableSnippet): void => {
    if (snippet._pendingDelete) {
        snippet.value = snippet._savedValue;
        snippet._savedValue = null;
        snippet._pendingDelete = false;
    } else {
        snippet._overriding = true;
    }
    isSaveable.value = checkIsSaveable();
};
const onResetSnippet = (snippet: EditableSnippet): void => {
    if (snippet._overriding) {
        snippet.value = snippet.origin;
        snippet._overriding = false;
    } else {
        snippet._savedValue = snippet.value;
        snippet.value = null;
        snippet._pendingDelete = true;
    }
    isSaveable.value = checkIsSaveable();
};
const onCancel = (): void => {
    void router.push(backPath.value);
};

void prepareContent();

swDefinePublic({
    acl,
    isLoading,
    isLoadingSnippets,
    isCreate,
    isAddedSnippet,
    isSaveable,
    isInvalidKey,
    queryIds,
    page,
    limit,
    translationKey,
    translationKeyOrigin,
    snippets,
    sets,
    isSaveSuccessful,
    pushParams,
    snippetRepository,
    snippetSetRepository,
    snippetSetCriteria,
    backPath,
    invalidKeyError,
    currentAuthor,
    snippetStates,
    prepareContent,
    initializeSnippet,
    applySnippetsToDummies,
    createSnippetDummy,
    saveFinish,
    onSave,
    onChange,
    doChange,
    onNewKeyRedirect,
    getCustomList,
    checkIsSaveable,
    getPlaceholder,
    getSnippetState,
    onRemoveInheritance,
    onResetSnippet,
    getSetName,
    onCancel,
});

defineExpose({
    acl,
    isLoading,
    isLoadingSnippets,
    isCreate,
    isAddedSnippet,
    isSaveable,
    isInvalidKey,
    queryIds,
    page,
    limit,
    translationKey,
    translationKeyOrigin,
    snippets,
    sets,
    isSaveSuccessful,
    pushParams,
    snippetRepository,
    snippetSetRepository,
    snippetSetCriteria,
    backPath,
    invalidKeyError,
    currentAuthor,
    snippetStates,
    prepareContent,
    initializeSnippet,
    applySnippetsToDummies,
    createSnippetDummy,
    saveFinish,
    onSave,
    onChange,
    doChange,
    onNewKeyRedirect,
    getCustomList,
    checkIsSaveable,
    getPlaceholder,
    getSnippetState,
    onRemoveInheritance,
    onResetSnippet,
    getSetName,
    onCancel,
});
</script>
