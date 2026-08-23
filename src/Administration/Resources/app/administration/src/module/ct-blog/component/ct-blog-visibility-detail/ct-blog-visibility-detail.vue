<template>
    <ct-block name="sw_blog_visibility_detail">
        <ct-grid class="ct-blog-visibility-detail" table :items="items" :selectable="false">
            <template #columns="{ item }">
                <ct-block name="sw_blog_visibility_detail_columns">
                    <ct-block name="sw_blog_visibility_detail_columns_channel">
                        <ct-grid-column
                            :label="$t('ct-blog.visibility.columnChannel')"
                            class="ct-blog-visibility-detail__column-channel"
                            flex="0.5fr"
                            align="left"
                        >
                            <ct-block name="sw_blog_visibility_detail_columns_channel_label">
                                <span
                                    v-tooltip="{
                                        message: names[item.id],
                                        disabled: !names[item.id] || names[item.id].length < 10,
                                    }"
                                    class="ct-blog-visibility-detail__name"
                                >
                                    {{ truncateFilter(names[item.id], 30) }}
                                </span>
                            </ct-block>
                        </ct-grid-column>
                    </ct-block>

                    <ct-block name="sw_blog_visibility_detail_columns_all">
                        <ct-grid-column
                            :label="$t('ct-blog.visibility.columnAll')"
                            class="ct-blog-visibility-detail__column-all"
                            flex="0.3fr"
                            align="left"
                        >
                            <ct-radio-field
                                :disabled="disabled"
                                :value="item.visibility"
                                :name="'visibility' + item.id"
                                :options="[{ value: 30 }]"
                                @update:value="changeVisibilityValue($event, item)"
                            />
                        </ct-grid-column>
                    </ct-block>

                    <ct-block name="sw_blog_visibility_detail_columns_search_only">
                        <ct-grid-column
                            :label="$t('ct-blog.visibility.columnSearchOnly')"
                            class="ct-blog-visibility-detail__search-only"
                            flex="0.7fr"
                            align="left"
                        >
                            <ct-radio-field
                                :disabled="disabled"
                                :value="item.visibility"
                                :name="'visibility' + item.id"
                                :options="[{ value: 20 }]"
                                @update:value="changeVisibilityValue($event, item)"
                            />
                        </ct-grid-column>
                    </ct-block>

                    <ct-block name="sw_blog_visibility_detail_columns_link_only">
                        <ct-grid-column
                            :label="$t('ct-blog.visibility.columnLinkOnly')"
                            class="ct-blog-visibility-detail__link-only"
                            flex="1fr"
                            align="left"
                        >
                            <ct-radio-field
                                :disabled="disabled"
                                :value="item.visibility"
                                :name="'visibility' + item.id"
                                :options="[{ value: 10 }]"
                                @update:value="changeVisibilityValue($event, item)"
                            />
                        </ct-grid-column>
                    </ct-block>
                </ct-block>
            </template>

            <template #pagination>
                <ct-block name="sw_blog_visibility_detail_pagination">
                    <ct-pagination
                        :page="page"
                        :limit="limit"
                        :total="total"
                        :total-visible="10"
                        :steps="[10]"
                        @page-change="onPageChange"
                    />
                </ct-block>
            </template>
        </ct-grid>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import { computed, ref } from 'vue';

import './ct-blog-visibility-detail.scss';

const { Filter } = Contena;

defineProps({
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const items = ref<Entity<'blog_visibility'>[]>([]);
const page = ref(1);
const limit = ref(10);
const total = ref(0);

const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const truncateFilter = computed(() => Filter.getByName('truncate'));
const filteredItems = computed(() => blog.value.visibilities.filter((item: Entity<'blog_visibility'>) => !item.isDeleted));
const names = computed<Record<string, string>>(() => {
    const visibilityNames: Record<string, string> = {};

    filteredItems.value.forEach((item) => {
        visibilityNames[item.id] = item.channel?.translated?.name ?? item.channel?.name ?? '';
    });

    return visibilityNames;
});

const createdComponent = (): void => {
    onPageChange({ page: page.value, limit: limit.value });
};
const onPageChange = (params: { page: number; limit: number }): void => {
    const offset = (params.page - 1) * params.limit;

    total.value = filteredItems.value.length;
    items.value = filteredItems.value.slice(offset, offset + params.limit);
};
const changeVisibilityValue = (event: string | number, item: Entity<'blog_visibility'>): void => {
    item.visibility = Number(event);
};

createdComponent();

swDefinePublic({
    items,
    page,
    limit,
    total,
    blog,
    truncateFilter,
    filteredItems,
    names,
    createdComponent,
    onPageChange,
    changeVisibilityValue,
});

defineExpose({
    items,
    page,
    limit,
    total,
    blog,
    truncateFilter,
    filteredItems,
    names,
    createdComponent,
    onPageChange,
    changeVisibilityValue,
});
</script>
