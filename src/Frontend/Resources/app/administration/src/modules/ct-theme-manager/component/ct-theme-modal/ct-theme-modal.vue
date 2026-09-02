<template>
    <ct-block name="ct_theme_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal
                class="ct-theme-modal"
                width="l"
                :title="t('ct-theme-manager.themeModal.modalTitle')"
                :subtitle="t('ct-theme-manager.themeModal.modalSubtitle')"
            >
                <ct-block name="ct_theme_modal_header">
                    <div class="ct-theme-modal__header">
                        <mt-search
                            v-model="searchTerm"
                            class="ct-theme-modal__header-search"
                            :placeholder="t('ct-theme-manager.general.placeholderSearchBar')"
                            @update:model-value="onSearch"
                        />
                    </div>
                </ct-block>

                <ct-block name="ct_theme_modal_content">
                    <div class="ct-theme-modal__content">
                        <ct-block name="ct_theme_modal_loader">
                            <mt-loader v-if="isLoading" />
                        </ct-block>

                        <div v-if="!isLoading" class="ct-theme-modal__content-grid">
                            <ct-block name="ct_theme_modal_content_listing">
                                <div
                                    v-for="theme in themes"
                                    :key="theme.id"
                                    class="ct-theme-modal__content-item"
                                    :class="{
                                        'is--selected': theme.id === selected,
                                    }"
                                >
                                    <ct-block name="ct_theme_modal_content_listing_item">
                                        <ct-block name="ct_theme_modal_content_listing_item_checkbox">
                                            <mt-checkbox
                                                :checked="theme.id === selected"
                                                @update:checked="onSelection(theme.id)"
                                            />
                                        </ct-block>

                                        <ct-block name="ct_theme_modal_content_listing_item_inner">
                                            <ct-theme-list-item
                                                :key="theme.id"
                                                :theme="theme"
                                                @item-click="selectItem(theme.id)"
                                            />
                                        </ct-block>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_theme_modal_footer">
                        <mt-button variant="secondary" @click="closeModal">
                            {{ t('global.default.cancel') }}
                        </mt-button>
                        <mt-button variant="primary" @click="selectLayout">
                            {{ t('ct-theme-manager.themeModal.actionConfirm') }}
                        </mt-button>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import { computed, inject, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { useListing } from 'src/app/composables/use-listing';
import './ct-theme-modal.scss';

const Criteria = Contena.Data.Criteria;

const props = defineProps({
    selectedThemeId: {
        type: String as PropType<string | null>,
        default: null,
        required: false,
    },
});
const emit = defineEmits<{
    'modal-theme-select': [themeId: string | null];
    'modal-close': [];
}>();
const { t } = useI18n();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) {
    throw new Error('The repositoryFactory service is required.');
}

const selected = ref<string | null>(null);
const isLoading = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref<'ASC' | 'DESC'>('DESC');
const term = ref<string | null>('');
const searchTerm = ref('');
const total = ref(0);
const themes = ref<EntityCollection<'theme'> | Entity<'theme'>[]>([]);
const themeRepository = computed(() => repositoryFactory.create('theme'));
const listing = useListing();
const { page, limit } = listing;

const createdComponent = (): void => {
    selected.value = props.selectedThemeId;
};
const getList = async (): Promise<EntityCollection<'theme'> | Entity<'theme'>[] | undefined> => {
    isLoading.value = true;
    const criteria = new Criteria(page.value, limit.value);
    criteria.addAssociation('previewMedia');
    criteria.addAssociation('channels');
    criteria.addFilter(Criteria.equals('active', true));
    criteria.addSorting(Criteria.sort(sortBy.value, sortDirection.value));
    criteria.setTerm(term.value ?? '');

    try {
        const searchResult = await themeRepository.value.search(criteria, Contena.Context.api);
        total.value = searchResult.total ?? 0;
        themes.value = searchResult;

        return searchResult;
    } catch {
        return undefined;
    } finally {
        isLoading.value = false;
    }
};
const closeModal = (): void => {
    emit('modal-close');
    selected.value = null;
    term.value = null;
    searchTerm.value = '';
};
const selectLayout = (): void => {
    emit('modal-theme-select', selected.value);
    closeModal();
};
const selectItem = (themeId: string): void => {
    selected.value = themeId;
};
const onSearch = (value: string): void => {
    searchTerm.value = value;
    term.value = value;
    page.value = 1;
    void getList();
};
const onSelection = (themeId: string): void => {
    selected.value = themeId;
};
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) {
        closeModal();
    }
};

createdComponent();
listing.initializeListing({
    total,
    sortBy,
    sortDirection,
    term,
    disableRouteParams: ref(true),
    getList,
});

ctDefinePublic({
    selected,
    isLoading,
    sortBy,
    sortDirection,
    term,
    searchTerm,
    total,
    themes,
    page,
    limit,
    themeRepository,
    createdComponent,
    getList,
    selectLayout,
    selectItem,
    onSearch,
    onSelection,
    closeModal,
    onModalChange,
});

defineExpose({
    selected,
    isLoading,
    sortBy,
    sortDirection,
    term,
    searchTerm,
    total,
    themes,
    page,
    limit,
    themeRepository,
    createdComponent,
    getList,
    selectLayout,
    selectItem,
    onSearch,
    onSelection,
    closeModal,
    onModalChange,
});
</script>
