<template>
    <ct-block name="sw_organization_tree">
        <div class="mt-organization-tree">
            <ct-block name="sw_organization_tree_toolbar">
                <div v-if="checkedCount > 0" class="mt-organization-tree__toolbar">
                    <span>{{ translate('ct-settings-organization.tree.selected', { count: checkedCount }) }}</span>
                    <mt-button variant="critical" size="small" @click="onBatchDelete">
                        {{ translate('global.default.delete') }}
                    </mt-button>
                </div>
            </ct-block>

            <ct-block name="sw_organization_tree_items">
                <div class="mt-organization-tree__items" role="tree">
                    <div
                        v-for="item in visibleItems"
                        :key="item.data.id"
                        class="mt-organization-tree__row"
                        :class="{ 'is--selected': item.data.id === selectedOrganizationId }"
                        :style="{ '--organization-tree-depth': item.depth }"
                        role="treeitem"
                        :aria-level="item.depth + 1"
                        :aria-expanded="hasChildren(item.data) ? isExpanded(item.data.id) : undefined"
                    >
                        <mt-button
                            v-if="hasChildren(item.data)"
                            class="mt-organization-tree__expand"
                            variant="tertiary"
                            size="small"
                            square
                            :aria-label="
                                translate(
                                    isExpanded(item.data.id)
                                        ? 'ct-settings-organization.tree.collapse'
                                        : 'ct-settings-organization.tree.expand',
                                )
                            "
                            @click.stop="toggleExpanded(item.data.id)"
                        >
                            <mt-icon
                                :name="isExpanded(item.data.id) ? 'regular-chevron-down-xs' : 'regular-chevron-right-xs'"
                                size="12px"
                            />
                        </mt-button>
                        <span v-else class="mt-organization-tree__expand-placeholder" aria-hidden="true"></span>

                        <mt-checkbox
                            v-if="canDelete"
                            class="mt-organization-tree__checkbox"
                            :model-value="isChecked(item.data.id)"
                            :aria-label="getOrganizationName(item.data)"
                            @update:model-value="onChecked(item.data.id, $event)"
                            @click.stop
                        />

                        <button class="mt-organization-tree__content" type="button" @click="onSelect(item.data)">
                            <span>{{ getOrganizationName(item.data) }}</span>
                            <small>{{ getOrganizationMeta(item.data) }}</small>
                        </button>

                        <div class="mt-organization-tree__actions">
                            <mt-button
                                v-if="canCreate"
                                class="mt-organization-tree__add-child"
                                variant="tertiary"
                                size="small"
                                square
                                :aria-label="translate('ct-settings-organization.tree.addChild')"
                                @click.stop="onAddChild(item.data)"
                            >
                                <mt-icon name="regular-plus-circle-s" size="14px" />
                            </mt-button>
                            <mt-button
                                v-if="canEdit"
                                class="mt-organization-tree__edit"
                                variant="tertiary"
                                size="small"
                                square
                                :aria-label="translate('global.default.edit')"
                                @click.stop="onSelect(item.data)"
                            >
                                <mt-icon name="regular-pencil-s" size="14px" />
                            </mt-button>
                            <mt-button
                                v-if="canDelete"
                                class="mt-organization-tree__delete"
                                variant="tertiary"
                                size="small"
                                square
                                :aria-label="translate('global.default.delete')"
                                @click.stop="onDelete(item.data)"
                            >
                                <mt-icon name="regular-trash" size="14px" />
                            </mt-button>
                        </div>
                    </div>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import type { PropType } from 'vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type Organization = Entity<'organization'>;
type TreeItem = Organization & { afterId: string | null; childCount: number };
type VisibleTreeItem = { data: TreeItem; depth: number };

const props = defineProps({
    items: {
        type: Array as PropType<TreeItem[]>,
        required: true,
    },
    selectedOrganizationId: {
        type: String as PropType<string | null>,
        default: null,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits<{
    'load-children': [parentId: string];
    'select-organization': [organization: Organization];
    'add-child': [organization: Organization];
    'delete-organization': [organization: Organization];
    'batch-delete': [ids: string[]];
}>();
const { t } = useI18n();
const translate = t;

const expandedIds = ref(new Set<string>());
const checkedIds = ref(new Set<string>());
const visibleItems = computed<VisibleTreeItem[]>(() => {
    const byParent = new Map<string | null, TreeItem[]>();
    props.items.forEach((item) => {
        const parentId = item.parentId ?? null;
        byParent.set(parentId, [
            ...(byParent.get(parentId) ?? []),
            item,
        ]);
    });

    const result: VisibleTreeItem[] = [];
    const append = (parentId: string | null, depth: number): void => {
        (byParent.get(parentId) ?? []).forEach((item) => {
            result.push({ data: item, depth });
            if (expandedIds.value.has(item.id)) append(item.id, depth + 1);
        });
    };
    append(null, 0);

    return result;
});
const checkedCount = computed(() => checkedIds.value.size);
const getOrganizationName = (organization: Organization): string =>
    organization.translated?.name ?? organization.name ?? organization.code ?? '';
const getOrganizationMeta = (organization: Organization): string => {
    const unitName = organization.organizationUnit?.translated?.name ?? organization.organizationUnit?.name;
    return [
        organization.code,
        unitName,
    ]
        .filter(Boolean)
        .join(' · ');
};
const hasChildren = (organization: Organization): boolean => Number(organization.childCount ?? 0) > 0;
const isExpanded = (id: string): boolean => expandedIds.value.has(id);
const isChecked = (id: string): boolean => checkedIds.value.has(id);
const toggleExpanded = (id: string): void => {
    const next = new Set(expandedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
        emit('load-children', id);
    }
    expandedIds.value = next;
};
const onChecked = (id: string, checked: boolean): void => {
    const next = new Set(checkedIds.value);
    if (checked) next.add(id);
    else next.delete(id);
    checkedIds.value = next;
};
const onSelect = (organization: Organization): void => emit('select-organization', organization);
const onAddChild = (organization: Organization): void => emit('add-child', organization);
const onDelete = (organization: Organization): void => emit('delete-organization', organization);
const onBatchDelete = (): void => {
    emit('batch-delete', Array.from(checkedIds.value));
    checkedIds.value = new Set<string>();
};

swDefinePublic({
    expandedIds,
    checkedIds,
    visibleItems,
    checkedCount,
    getOrganizationName,
    getOrganizationMeta,
    hasChildren,
    isExpanded,
    isChecked,
    toggleExpanded,
    onChecked,
    onSelect,
    onAddChild,
    onDelete,
    onBatchDelete,
});

defineExpose({
    expandedIds,
    checkedIds,
    visibleItems,
    checkedCount,
    getOrganizationName,
    getOrganizationMeta,
    hasChildren,
    isExpanded,
    isChecked,
    toggleExpanded,
    onChecked,
    onSelect,
    onAddChild,
    onDelete,
    onBatchDelete,
});
</script>

<style scoped lang="scss">
.mt-organization-tree {
    min-height: 520px;

    &__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 52px;
        padding: var(--scale-size-8) var(--scale-size-16);
        border-bottom: 1px solid var(--color-border-primary-default);
    }

    &__items {
        padding: var(--scale-size-8);
    }

    &__row {
        display: flex;
        align-items: center;
        gap: var(--scale-size-8);
        min-height: 52px;
        padding: var(--scale-size-4) var(--scale-size-8) var(--scale-size-4)
            calc(var(--scale-size-8) + var(--organization-tree-depth) * var(--scale-size-24));
        border-radius: var(--border-radius-xs);

        &:hover,
        &.is--selected {
            background: var(--color-background-brand-default);
        }

        &.is--selected .mt-organization-tree__content {
            color: var(--color-text-brand-default);
        }
    }

    &__expand,
    &__expand-placeholder {
        width: 28px;
        min-width: 28px;
    }

    &__expand,
    &__actions :deep(.mt-button) {
        min-height: 28px;
        height: 28px;
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;

        &:hover,
        &:focus-visible {
            outline: none;
            background: var(--color-interaction-secondary-hover);
        }
    }

    &__delete :deep(.mt-icon) {
        color: var(--color-icon-critical-default);
    }

    &__delete:hover :deep(.mt-icon) {
        color: var(--color-icon-critical-hover);
    }

    &__checkbox {
        flex: 0 0 auto;
        margin: 0;
    }

    &__content {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
        padding: var(--scale-size-4) 0;
        border: 0;
        background: transparent;
        color: var(--color-text-primary-default);
        cursor: pointer;
        text-align: left;

        span,
        small {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        span {
            font-weight: var(--mt-font-weight-semibold);
        }

        small {
            color: var(--color-text-secondary-default);
        }
    }

    &__actions {
        display: flex;
        gap: var(--scale-size-4);
        opacity: 0;
        transition: opacity 0.12s ease;
    }

    &__row:hover &__actions,
    &__row:focus-within &__actions,
    &__row.is--selected &__actions {
        opacity: 1;
    }
}
</style>
