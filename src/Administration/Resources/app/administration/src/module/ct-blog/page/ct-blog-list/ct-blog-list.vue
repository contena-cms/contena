<template>
    <ct-block name="ct_blog_list">
        <ct-page class="ct-blog-list">
            <template #search-bar>
                <ct-block name="ct_blog_list_search">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_blog_list_header">
                    <h2>
                        {{ $t('ct-blog.list.textHeadline') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_blog_list_actions">
                    <div
                        v-tooltip="{
                            message: $t('ct-privileges.tooltip.warning'),
                            disabled: allowCreate,
                            showOnDisabledElements: true,
                        }"
                        class="ct-blog-list__add-button-group"
                    >
                        <ct-block name="ct_blog_list_actions_add_post">
                            <mt-button
                                class="ct-blog-list__add-post-button"
                                variant="primary"
                                :disabled="!allowCreate || undefined"
                                @click="onCreateBlog('post')"
                            >
                                {{ $t('ct-blog.list.buttonAddBlog') }}
                            </mt-button>
                        </ct-block>

                        <mt-dropdown-menu-root>
                            <mt-dropdown-menu-trigger as-child>
                                <mt-button
                                    class="ct-blog-list__button-context-menu"
                                    square
                                    variant="primary"
                                    :disabled="!allowCreate || undefined"
                                    :aria-label="$t('ct-blog.list.buttonAddMediaBlog')"
                                >
                                    <mt-icon name="regular-chevron-down-xs" size="16" />
                                </mt-button>
                            </mt-dropdown-menu-trigger>
                            <mt-dropdown-menu-portal>
                                <mt-action-menu>
                                    <ct-block name="ct_blog_list_actions_add_media">
                                        <mt-action-menu-item @select="onCreateBlog('media')">
                                            {{ $t('ct-blog.list.buttonAddMediaBlog') }}
                                        </mt-action-menu-item>
                                    </ct-block>
                                </mt-action-menu>
                            </mt-dropdown-menu-portal>
                        </mt-dropdown-menu-root>
                    </div>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-language-switch @on-change="onChangeLanguage" />
            </template>

            <template #content>
                <ct-block name="ct_blog_list_content">
                    <div class="ct-blog-list__content">
                        <mt-data-table
                            v-if="blogs?.length"
                            :caption="$t('ct-blog.list.textHeadline')"
                            :columns="columns"
                            :data-source="blogs"
                            :is-loading="isLoading"
                            :current-page="page"
                            :pagination-limit="limit"
                            :pagination-total-items="total"
                            :sort-by="sortBy"
                            :sort-direction="sortDirection"
                            :disable-edit="!allowEdit"
                            :disable-delete="!allowDelete"
                            :additional-context-buttons="additionalContextButtons"
                            disable-search
                            @pagination-current-page-change="onTablePageChange"
                            @pagination-limit-change="onTableLimitChange"
                            @sort-change="onTableSortChange"
                            @open-details="onOpenDetails"
                            @item-delete="onDelete"
                            @context-select="onContextSelect"
                        />

                        <mt-empty-state
                            v-else-if="!isLoading"
                            icon="regular-file-text"
                            :headline="$t(term ? 'ct-empty-state.messageNoResultTitle' : 'ct-blog.list.emptyState')"
                            :description="term ? $t('ct-empty-state.messageNoResultSubline') : undefined"
                        />

                        <mt-loader v-else />
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */

import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

import './ct-blog-list.scss';

interface ColumnConfig {
    property: string;
    label: string;
    renderer: 'text' | 'boolean' | 'number';
    position: number;
    sortable: boolean;
    clickable?: boolean;
    allowResize: boolean;
}

interface ContextAction {
    key: string;
    label: string;
    type?: string;
}

defineProps({});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const { page, limit, total, term, onPageChange, onSearch, onSort, initializeListing } = useListing();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const blogs = ref<EntityCollection<'blog'> | null>(null);
const isLoading = ref(false);
const sortBy = ref('updatedAt');
const sortDirection = ref<'ASC' | 'DESC'>('DESC');
const blogRepository = computed(() => repositoryFactory.create('blog'));
const columns = computed<ColumnConfig[]>(() => [
    {
        property: 'name',
        label: t('ct-blog.list.columnName'),
        renderer: 'text',
        position: 100,
        sortable: true,
        clickable: true,
        allowResize: true,
    },
    {
        property: 'active',
        label: t('ct-blog.list.columnActive'),
        renderer: 'boolean',
        position: 200,
        sortable: true,
        allowResize: true,
    },
    {
        property: 'releaseDate',
        label: t('ct-blog.list.columnReleaseDate'),
        renderer: 'text',
        position: 300,
        sortable: true,
        allowResize: true,
    },
    {
        property: 'updatedAt',
        label: t('ct-blog.list.columnUpdatedAt'),
        renderer: 'text',
        position: 400,
        sortable: true,
        allowResize: true,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addAssociation('cover.media');
    query.addAssociation('categories');
    query.addAssociation('tags');
    query.addAssociation('visibilities.channel');
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));

    if (term.value) {
        query.setTerm(term.value);
    }

    return query;
});
const allowCreate = computed(() => acl.can('blog.creator'));
const allowEdit = computed(() => acl.can('blog.editor'));
const allowDelete = computed(() => acl.can('blog.deleter'));
const additionalContextButtons = computed<ContextAction[]>(() =>
    allowCreate.value ? [{ key: 'duplicate', label: t('global.default.duplicate') }] : [],
);

const getList = async (): Promise<void> => {
    isLoading.value = true;

    try {
        blogs.value = await blogRepository.value.search(criteria.value, Contena.Context.api);
        total.value = blogs.value.total ?? 0;
    } finally {
        isLoading.value = false;
    }
};
const onCreateBlog = (creationType: 'post' | 'media' = 'post'): void => {
    void router.push({ name: 'ct.blog.create.base', query: { creationType } });
};
const onOpenDetails = (blog: Entity<'blog'>): void => {
    void router.push({ name: 'ct.blog.detail.base', params: { id: blog.id } });
};
const onDuplicate = async (blog: Entity<'blog'>): Promise<void> => {
    isLoading.value = true;

    try {
        const duplicate = (await blogRepository.value.clone(blog.id, {}, Contena.Context.api)) as Entity<'blog'>;
        await router.push({ name: 'ct.blog.detail.base', params: { id: duplicate.id } });
    } finally {
        isLoading.value = false;
    }
};
const onDelete = async (blog: Entity<'blog'>): Promise<void> => {
    try {
        await blogRepository.value.delete(blog.id, Contena.Context.api);
        await getList();
    } catch {
        createNotificationError({ message: t('global.notification.notificationDeleteErrorMessage') });
    }
};
const onContextSelect = (event: { key: string; data: Entity<'blog'> }): void => {
    if (event.key === 'duplicate') {
        void onDuplicate(event.data);
    }
};
const onChangeLanguage = (languageId: string): void => {
    Contena.Store.get('context').setApiLanguageId(languageId);
    void getList();
};
const onTablePageChange = (nextPage: number): void => {
    onPageChange({ page: nextPage, limit: limit.value });
};
const onTableLimitChange = (nextLimit: number): void => {
    onPageChange({ page: 1, limit: nextLimit });
};
const onTableSortChange = (property: string, direction: 'ASC' | 'DESC'): void => {
    onSort({ sortBy: property, sortDirection: direction });
};

initializeListing({ getList, sortBy, sortDirection });
void getList();

ctDefinePublic({
    acl,
    blogs,
    isLoading,
    sortBy,
    sortDirection,
    blogRepository,
    columns,
    criteria,
    allowCreate,
    allowEdit,
    allowDelete,
    additionalContextButtons,
    getList,
    onCreateBlog,
    onOpenDetails,
    onDuplicate,
    onDelete,
    onContextSelect,
    onChangeLanguage,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
});

usePageTitle();

defineExpose({
    acl,
    blogs,
    isLoading,
    sortBy,
    sortDirection,
    blogRepository,
    columns,
    criteria,
    allowCreate,
    allowEdit,
    allowDelete,
    additionalContextButtons,
    getList,
    onCreateBlog,
    onOpenDetails,
    onDuplicate,
    onDelete,
    onContextSelect,
    onChangeLanguage,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
});
</script>
