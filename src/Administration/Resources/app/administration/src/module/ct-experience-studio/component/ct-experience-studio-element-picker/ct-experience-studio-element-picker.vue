<template>
    <ct-block name="ct_experience_studio_element_picker">
        <div
            v-if="open"
            v-click-outside="() => $emit('close')"
            class="ct-experience-studio-element-picker"
            :style="flyoutStyle()"
        >
            <div class="ct-experience-studio-element-picker__header">
                <span>{{ title }}</span>

                <button class="ct-experience-studio-element-picker__close" type="button" @click="$emit('close')">
                    <mt-icon name="regular-times-xs" size="12px" />
                </button>
            </div>

            <div class="ct-experience-studio-element-picker__content">
                <mt-empty-state
                    v-if="elements.length === 0"
                    icon="regular-puzzle-piece"
                    :description="$t('ct-experience-studio.detail.elementPicker.noElements')"
                />

                <div v-else class="ct-experience-studio-element-picker__sections">
                    <div
                        v-for="group in groupedElements"
                        :key="group.key"
                        class="ct-experience-studio-element-picker__section"
                    >
                        <div class="ct-experience-studio-element-picker__section-title">
                            <span>{{ $t(group.headlineSnippetKey) }}</span>
                        </div>

                        <div class="ct-experience-studio-element-picker__grid">
                            <button
                                v-for="element in group.elements"
                                :key="element.name"
                                v-tooltip="{ message: element.label }"
                                class="ct-experience-studio-element-picker__item"
                                type="button"
                                @click="onSelect(element.name)"
                            >
                                <div class="ct-experience-studio-element-picker__icon-square">
                                    <mt-icon :name="element.icon || 'regular-square'" size="20px" />
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-experience-studio-element-picker.scss';

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    elements: {
        type: Array,
        required: false,
        default: () => [],
    },
    top: {
        type: Number,
        required: false,
        default: 0,
    },
    left: {
        type: Number,
        required: false,
        default: 0,
    },
});
const emit = defineEmits([
    'close',
    'select',
]);

import { ref, computed } from 'vue';

const categoryOrder = ref([
    'layout',
    'content',
    'media',
]);
const fallbackCategoryKey = ref('other');

const groupedElements = computed(() => {
    type Group = {
        key: string;
        headlineSnippetKey: string;
        elements: Array<{ name: string; label: string; icon: string | null }>;
        firstSeenIndex: number;
    };

    const groups = props.elements.reduce<Group[]>((result, element, index) => {
        const categoryKey = normalizeCategoryKey((element as { category?: string | null }).category ?? null);
        const existingGroup = result.find((group) => group.key === categoryKey);

        if (existingGroup) {
            existingGroup.elements.push(element as { name: string; label: string; icon: string | null });

            return result;
        }

        result.push({
            key: categoryKey,
            headlineSnippetKey: categoryHeadlineSnippetKey(categoryKey),
            elements: [element as { name: string; label: string; icon: string | null }],
            firstSeenIndex: index,
        });

        return result;
    }, []);

    return groups
        .sort((a, b) => {
            const categoryOrderValue = categoryOrder.value;
            const aPriority = categoryOrderValue.indexOf(a.key);
            const bPriority = categoryOrderValue.indexOf(b.key);
            const resolvedAPriority = aPriority === -1 ? Number.MAX_SAFE_INTEGER : aPriority;
            const resolvedBPriority = bPriority === -1 ? Number.MAX_SAFE_INTEGER : bPriority;

            if (resolvedAPriority !== resolvedBPriority) {
                return resolvedAPriority - resolvedBPriority;
            }

            return a.firstSeenIndex - b.firstSeenIndex;
        })
        .map((group) => ({
            key: group.key,
            headlineSnippetKey: group.headlineSnippetKey,
            elements: group.elements,
        }));
});

const flyoutStyle = () => {
    return {
        top: `${props.top}px`,
        left: `${props.left}px`,
    };
};
const normalizeCategoryKey = (category: string | null) => {
    if (!category) {
        return fallbackCategoryKey.value;
    }

    const normalizedCategory = category
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return normalizedCategory.length > 0 ? normalizedCategory : fallbackCategoryKey.value;
};
const categoryHeadlineSnippetKey = (categoryKey: string) => {
    return `ct-experience-studio.detail.elementPicker.categoryHeadlines.${categoryKey}`;
};
const onSelect = (component: string) => {
    emit('select', component);
};

ctDefinePublic({
    categoryOrder,
    fallbackCategoryKey,
    groupedElements,
    flyoutStyle,
    normalizeCategoryKey,
    categoryHeadlineSnippetKey,
    onSelect,
});

defineExpose({
    categoryOrder,
    fallbackCategoryKey,
    groupedElements,
    flyoutStyle,
    normalizeCategoryKey,
    categoryHeadlineSnippetKey,
    onSelect,
});
</script>
