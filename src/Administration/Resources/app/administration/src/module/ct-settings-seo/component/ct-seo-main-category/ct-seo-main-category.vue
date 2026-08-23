<template>
    <ct-block name="sw_seo_main_category">
        <div class="ct-seo-main-category">
            <ct-block name="sw_seo_main_category_select">
                <mt-select
                    :options="categories"
                    label-property="translated.name"
                    value-property="id"
                    :placeholder="$t('ct-seo-url.placeholderMainCategory')"
                    :label="overwriteLabel ? undefined : $t('ct-seo-url.labelMainCategory')"
                    :model-value="selectedCategory"
                    :disabled="currentChannelId === null || !allowEdit || undefined"
                    @update:model-value="onMainCategorySelected"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref, watch } from 'vue';

type Category = Entity<'category'>;
type BlogMainCategory = Entity<'blog_main_category'>;

const props = defineProps({
    currentChannelId: {
        type: String,
        required: false,
        default: null,
    },
    categories: {
        type: Array as PropType<Category[]>,
        required: true,
    },
    mainCategories: {
        type: Array as PropType<BlogMainCategory[]>,
        required: true,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
    allowEdit: {
        type: Boolean,
        required: false,
        default: true,
    },
    overwriteLabel: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits<{
    'main-category-add': [mainCategory: BlogMainCategory];
    'main-category-remove': [mainCategory: BlogMainCategory];
}>();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;

const mainCategoryForChannel = ref<BlogMainCategory | null>(null);

const mainCategoryRepository = computed(() => {
    return repositoryFactory.create('blog_main_category');
});
const isHeadlessChannel = computed(() => {
    if (Contena.Store.get('swSeoUrl').channelCollection === null) {
        return true;
    }

    const channel = Contena.Store.get('swSeoUrl').channelCollection.find((entry) => {
        return entry.id === props.currentChannelId;
    });

    return props.currentChannelId !== null && channel?.typeId === Contena.Defaults.apiChannelTypeId;
});
const selectedCategory = computed(() => {
    return mainCategoryForChannel.value !== null ? mainCategoryForChannel.value.categoryId : null;
});

const createdComponent = () => {
    refreshMainCategoryForChannel();
};
const onMainCategorySelected = (categoryId: string | null): void => {
    if (!categoryId) {
        if (mainCategoryForChannel.value) {
            emit('main-category-remove', mainCategoryForChannel.value);
            mainCategoryForChannel.value = null;
        }
        return;
    }

    const selectedCategory = props.categories.find((value) => {
        return value.id === categoryId;
    });

    if (!selectedCategory) {
        return;
    }

    if (mainCategoryForChannel.value !== null) {
        mainCategoryForChannel.value.category = selectedCategory;
        mainCategoryForChannel.value.categoryId = selectedCategory.id;
        return;
    }

    const mainCategory = mainCategoryRepository.value.create();
    mainCategory.channelId = props.currentChannelId;
    mainCategory.category = selectedCategory;
    mainCategory.categoryId = selectedCategory.id;
    emit('main-category-add', mainCategory);
    refreshMainCategoryForChannel();
};
const refreshMainCategoryForChannel = (): void => {
    const mainCategory = props.mainCategories.find((category) => {
        return category.channelId === props.currentChannelId;
    });

    if (mainCategory === undefined) {
        mainCategoryForChannel.value = null;
        return;
    }

    mainCategoryForChannel.value = mainCategory;
};

watch(
    () => props.currentChannelId,
    () => {
        refreshMainCategoryForChannel();
    },
);
watch(
    () => props.mainCategories,
    () => {
        refreshMainCategoryForChannel();
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    mainCategoryForChannel,
    mainCategoryRepository,
    isHeadlessChannel,
    selectedCategory,
    createdComponent,
    onMainCategorySelected,
    refreshMainCategoryForChannel,
});

defineExpose({
    repositoryFactory,
    mainCategoryForChannel,
    mainCategoryRepository,
    isHeadlessChannel,
    selectedCategory,
    createdComponent,
    onMainCategorySelected,
    refreshMainCategoryForChannel,
});
</script>
