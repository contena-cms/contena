<template>
    <ct-block name="sw_integration_mcp_allowlist">
        <div class="ct-integration-mcp-allowlist">
            <ct-block name="sw_integration_mcp_allowlist_sticky">
                <div class="ct-integration-mcp-allowlist__sticky">
                    <ct-block name="sw_integration_mcp_allowlist_admin_banner">
                        <mt-banner v-if="isAdmin" variant="info" class="ct-integration-mcp-allowlist__admin-banner">
                            {{ $t('ct-integration.mcp.adminBanner') }}
                        </mt-banner>
                    </ct-block>

                    <ct-block name="sw_integration_mcp_allowlist_coverage_warning">
                        <mt-banner
                            v-if="!isAdmin && uncoveredTools.length > 0"
                            variant="attention"
                            class="ct-integration-mcp-allowlist__coverage-banner"
                        >
                            <span>{{ $t('ct-integration.mcp.coverageWarning', { count: uncoveredTools.length }) }}</span>

                            <span
                                v-if="allMissingPrivileges.length > 0"
                                class="ct-integration-mcp-allowlist__missing-privileges-list"
                            >
                                {{
                                    $t('ct-integration.mcp.missingPrivilegesList', {
                                        privileges: allMissingPrivileges.join(', '),
                                    })
                                }}
                            </span>
                        </mt-banner>
                    </ct-block>

                    <ct-block name="sw_integration_mcp_allowlist_capability_suggestions">
                        <mt-banner
                            v-if="missingCapabilitySuggestions.length > 0"
                            variant="attention"
                            class="ct-integration-mcp-allowlist__stale-banner"
                        >
                            <span>
                                {{
                                    $t('ct-integration.mcp.missingCapabilitySuggestions', {
                                        count: missingCapabilitySuggestions.length,
                                    })
                                }}
                            </span>

                            <span class="ct-integration-mcp-allowlist__missing-privileges-list">
                                {{ missingCapabilitySuggestions.map((s) => s.name).join('\n') }}
                            </span>
                        </mt-banner>
                    </ct-block>

                    <ct-block name="sw_integration_mcp_allowlist_denied_types">
                        <mt-banner
                            v-if="deniedTypes.length > 0"
                            variant="attention"
                            class="ct-integration-mcp-allowlist__stale-banner"
                        >
                            {{ $t('ct-integration.mcp.deniedTypeWarning', { types: deniedTypesLabel }) }}
                        </mt-banner>
                    </ct-block>

                    <ct-block name="sw_integration_mcp_allowlist_stale">
                        <mt-banner
                            v-if="staleEntries.length > 0"
                            variant="attention"
                            class="ct-integration-mcp-allowlist__stale-banner"
                        >
                            {{ $t('ct-integration.mcp.staleEntries', { entries: staleEntries.join(', ') }) }}
                        </mt-banner>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_integration_mcp_allowlist_all_toggle">
                <mt-switch
                    v-model="allCapabilitiesEnabled"
                    class="ct-integration-mcp-allowlist__all-tools-row"
                    :disabled="disabled"
                    :label="$t('ct-integration.mcp.allCapabilitiesLabel')"
                    :help-text="$t('ct-integration.mcp.infoText')"
                    :bordered="true"
                />
            </ct-block>

            <ct-block name="sw_integration_mcp_allowlist_types">
                <div v-if="!allCapabilitiesEnabled" class="ct-integration-mcp-allowlist__type-list">
                    <template v-for="typeConfig in typeConfigs" :key="typeConfig.key">
                        <div
                            v-if="typeConfig.available.length > 0 || isLoading"
                            class="ct-integration-mcp-allowlist__type-section"
                        >
                            <div class="ct-integration-mcp-allowlist__type-header" @click="toggleType(typeConfig.key)">
                                <mt-icon
                                    :name="
                                        isTypeExpanded(typeConfig.key) ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs'
                                    "
                                    size="10px"
                                    class="ct-integration-mcp-allowlist__type-chevron"
                                />

                                <span class="ct-integration-mcp-allowlist__type-title">
                                    {{ $t(typeConfig.titleKey) }}
                                </span>

                                <!-- flat types (single group, or all groups have one item): expand/collapse item descriptions -->
                                <mt-link
                                    v-if="
                                        isTypeExpanded(typeConfig.key) &&
                                        isFlatType(typeConfig.key) &&
                                        flatTypeHasExpandableItems(typeConfig.key)
                                    "
                                    as="a"
                                    class="ct-integration-mcp-allowlist__toggle-all"
                                    @click.stop="
                                        areAllItemsInTypeExpanded(typeConfig.key)
                                            ? collapseAllItemsInType(typeConfig.key)
                                            : expandAllItemsInType(typeConfig.key)
                                    "
                                >
                                    {{
                                        areAllItemsInTypeExpanded(typeConfig.key)
                                            ? $t('ct-integration.mcp.collapseAll')
                                            : $t('ct-integration.mcp.expandAll')
                                    }}
                                </mt-link>

                                <!-- multi-group non-flat types: expand/collapse groups -->
                                <mt-link
                                    v-else-if="isTypeExpanded(typeConfig.key) && Object.keys(typeConfig.groups).length > 1"
                                    as="a"
                                    class="ct-integration-mcp-allowlist__toggle-all"
                                    @click.stop="
                                        areAllGroupsExpanded(typeConfig.key)
                                            ? collapseAllGroups(typeConfig.key)
                                            : expandAllGroups(typeConfig.key)
                                    "
                                >
                                    {{
                                        areAllGroupsExpanded(typeConfig.key)
                                            ? $t('ct-integration.mcp.collapseAll')
                                            : $t('ct-integration.mcp.expandAll')
                                    }}
                                </mt-link>

                                <mt-badge class="ct-integration-mcp-allowlist__type-count">
                                    {{ typeSelectedCount(typeConfig.key) }}/{{ typeTotal(typeConfig.key) }}
                                </mt-badge>

                                <mt-switch
                                    :model-value="typeAllEnabled(typeConfig.key)"
                                    :disabled="disabled"
                                    :label="$t('ct-integration.mcp.allTypeLabel')"
                                    class="ct-integration-mcp-allowlist__type-all-switch"
                                    @update:model-value="onToggleTypeAll(typeConfig.key, $event)"
                                    @click.stop
                                />

                                <mt-icon
                                    v-tooltip="{ message: $t(typeConfig.guidanceKey), showDelay: 200, width: '280px' }"
                                    name="solid-question-circle-s"
                                    size="16px"
                                    class="ct-integration-mcp-allowlist__type-info"
                                    @click.stop
                                />
                            </div>

                            <template v-if="isTypeExpanded(typeConfig.key)">
                                <div
                                    v-for="(items, group) in typeConfig.groups"
                                    :key="group"
                                    class="ct-integration-mcp-allowlist__group"
                                >
                                    <div
                                        v-if="items.length > 1 && Object.keys(typeConfig.groups).length > 1"
                                        class="ct-integration-mcp-allowlist__group-header"
                                        @click="toggleGroup(typeConfig.key, group)"
                                    >
                                        <mt-checkbox
                                            :checked="isGroupAllSelected(typeConfig.key, group)"
                                            :indeterminate="isGroupPartiallySelected(typeConfig.key, group)"
                                            :disabled="disabled || typeAllEnabled(typeConfig.key)"
                                            class="ct-integration-mcp-allowlist__group-checkbox"
                                            @update:checked="onToggleGroupAll(typeConfig.key, group, $event)"
                                            @click.stop
                                        />

                                        <span class="ct-integration-mcp-allowlist__group-label">{{
                                            groupLabel(typeConfig.key, group)
                                        }}</span>

                                        <mt-link
                                            v-if="
                                                isGroupExpanded(typeConfig.key, group) &&
                                                getGroupItems(typeConfig.key, group).filter((i) => !!i.description).length >
                                                    1
                                            "
                                            as="a"
                                            class="ct-integration-mcp-allowlist__toggle-all"
                                            @click.stop="
                                                areAllItemsInGroupExpanded(typeConfig.key, group)
                                                    ? collapseAllItemsInGroup(typeConfig.key, group)
                                                    : expandAllItemsInGroup(typeConfig.key, group)
                                            "
                                        >
                                            {{
                                                areAllItemsInGroupExpanded(typeConfig.key, group)
                                                    ? $t('ct-integration.mcp.collapseAll')
                                                    : $t('ct-integration.mcp.expandAll')
                                            }}
                                        </mt-link>

                                        <mt-icon
                                            :name="
                                                isGroupExpanded(typeConfig.key, group)
                                                    ? 'regular-chevron-up-xs'
                                                    : 'regular-chevron-down-xs'
                                            "
                                            size="10px"
                                            class="ct-integration-mcp-allowlist__group-chevron"
                                        />
                                    </div>

                                    <template
                                        v-if="
                                            items.length === 1 ||
                                            Object.keys(typeConfig.groups).length === 1 ||
                                            isGroupExpanded(typeConfig.key, group)
                                        "
                                    >
                                        <div class="ct-integration-mcp-allowlist__tools">
                                            <div
                                                v-for="item in items"
                                                :key="itemKey(typeConfig.key, item)"
                                                class="ct-integration-mcp-allowlist__tool"
                                                :class="{ 'is--disabled': disabled, 'has--description': !!item.description }"
                                            >
                                                <template v-if="!item.description">
                                                    <mt-checkbox
                                                        :disabled="disabled || typeAllEnabled(typeConfig.key)"
                                                        :checked="isItemSelectedForGroup(typeConfig.key, item)"
                                                        :label="item.name"
                                                        @update:checked="onToggleItem(typeConfig.key, item, $event)"
                                                    />

                                                    <template v-if="typeConfig.key === 'tools'">
                                                        <mt-badge
                                                            v-if="item.dependencies?.length > 0"
                                                            v-tooltip="{
                                                                message: $t('ct-integration.mcp.requiresTooltip', {
                                                                    deps: item.dependencies.join(', '),
                                                                }),
                                                                showDelay: 200,
                                                            }"
                                                            variant="info"
                                                            class="ct-integration-mcp-allowlist__requires-badge"
                                                        >
                                                            {{ $t('ct-integration.mcp.requiresLabel') }}
                                                        </mt-badge>

                                                        <mt-badge
                                                            v-if="isDependency(item.name)"
                                                            v-tooltip="{
                                                                message: $t('ct-integration.mcp.dependencyOf'),
                                                                showDelay: 200,
                                                            }"
                                                            variant="neutral"
                                                            class="ct-integration-mcp-allowlist__dep-badge"
                                                        >
                                                            {{ $t('ct-integration.mcp.dep') }}
                                                        </mt-badge>

                                                        <mt-badge
                                                            v-for="chip in privilegeChips(item)"
                                                            :key="chip"
                                                            :variant="privilegeChipClass(chip)"
                                                        >
                                                            {{ chip }}
                                                        </mt-badge>
                                                    </template>
                                                </template>

                                                <template v-else>
                                                    <div
                                                        class="ct-integration-mcp-allowlist__item-header"
                                                        @click="toggleItem(typeConfig.key, item)"
                                                    >
                                                        <mt-checkbox
                                                            :disabled="disabled || typeAllEnabled(typeConfig.key)"
                                                            :checked="isItemSelectedForGroup(typeConfig.key, item)"
                                                            :label="item.name"
                                                            @update:checked="onToggleItem(typeConfig.key, item, $event)"
                                                            @click.stop
                                                        />

                                                        <template v-if="typeConfig.key === 'tools'">
                                                            <mt-badge
                                                                v-if="item.dependencies?.length > 0"
                                                                v-tooltip="{
                                                                    message: $t('ct-integration.mcp.requiresTooltip', {
                                                                        deps: item.dependencies.join(', '),
                                                                    }),
                                                                    showDelay: 200,
                                                                }"
                                                                variant="info"
                                                                class="ct-integration-mcp-allowlist__requires-badge"
                                                            >
                                                                {{ $t('ct-integration.mcp.requiresLabel') }}
                                                            </mt-badge>

                                                            <mt-badge
                                                                v-if="isDependency(item.name)"
                                                                v-tooltip="{
                                                                    message: $t('ct-integration.mcp.dependencyOf'),
                                                                    showDelay: 200,
                                                                }"
                                                                variant="neutral"
                                                                class="ct-integration-mcp-allowlist__dep-badge"
                                                            >
                                                                {{ $t('ct-integration.mcp.dep') }}
                                                            </mt-badge>

                                                            <mt-icon
                                                                v-if="uncoveredTools.some((t) => t.name === item.name)"
                                                                v-tooltip="{
                                                                    message: $t(
                                                                        'ct-integration.mcp.missingPrivilegesTooltip',
                                                                        {
                                                                            privileges: missingPrivilegesForTool(
                                                                                item.name,
                                                                            ).join(', '),
                                                                        },
                                                                    ),
                                                                    showDelay: 0,
                                                                }"
                                                                name="regular-exclamation-triangle"
                                                                size="14px"
                                                                class="ct-integration-mcp-allowlist__missing-privileges-icon"
                                                            />
                                                        </template>

                                                        <mt-icon
                                                            :name="
                                                                isItemExpanded(typeConfig.key, item)
                                                                    ? 'regular-chevron-up-xs'
                                                                    : 'regular-chevron-down-xs'
                                                            "
                                                            size="10px"
                                                            class="ct-integration-mcp-allowlist__tool-chevron"
                                                        />
                                                    </div>

                                                    <div
                                                        v-if="isItemExpanded(typeConfig.key, item)"
                                                        class="ct-integration-mcp-allowlist__item-content"
                                                    >
                                                        <p class="ct-integration-mcp-allowlist__tool-description">
                                                            {{ item.description }}
                                                        </p>

                                                        <template v-if="typeConfig.key === 'tools'">
                                                            <div
                                                                v-if="privilegeChips(item).length > 0"
                                                                class="ct-integration-mcp-allowlist__privilege-list"
                                                            >
                                                                <span class="ct-integration-mcp-allowlist__privilege-label">
                                                                    {{ $t('ct-integration.mcp.privilegesLabel') }}
                                                                </span>

                                                                <mt-badge
                                                                    v-for="chip in privilegeChips(item)"
                                                                    :key="chip"
                                                                    :variant="privilegeChipClass(chip)"
                                                                >
                                                                    {{ chip }}
                                                                </mt-badge>
                                                            </div>

                                                            <p
                                                                v-if="missingDependencies(item).length > 0"
                                                                class="ct-integration-mcp-allowlist__tool-warning"
                                                            >
                                                                <mt-icon name="regular-exclamation-triangle" size="12px" />

                                                                <span>
                                                                    {{
                                                                        $t('ct-integration.mcp.missingDeps', {
                                                                            deps: missingDependencies(item).join(', '),
                                                                        })
                                                                    }}
                                                                </span>
                                                            </p>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </ct-block>

            <ct-block name="sw_integration_mcp_allowlist_empty">
                <mt-empty-state
                    v-if="
                        !allCapabilitiesEnabled &&
                        availableTools.length === 0 &&
                        availableResources.length === 0 &&
                        availablePrompts.length === 0 &&
                        !isLoading
                    "
                    :headline="$t('ct-integration.mcp.noCapabilitiesHeadline')"
                    :description="$t('ct-integration.mcp.noCapabilitiesDescription')"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import { computePrivilegeChips, isPrivilegeGranted } from 'src/core/helper/mcp-privilege.helper';
import { buildGroups, humanizeLabel, humanizeCommonPrefix } from './mcp-allowlist.utils';
import './ct-integration-mcp-allowlist.scss';

const props = defineProps({
    /**
     * null = all capabilities unrestricted (primary toggle on)
     * {tools, resources, prompts} = per-type allowlists (null per type = unrestricted)
     */
    allowlist: {
        type: Object,
        default: null,
    },

    disabled: {
        type: Boolean,
        default: false,
    },

    isAdmin: {
        type: Boolean,
        default: false,
    },

    grantedPrivileges: {
        type: Array,
        default: () => [],
    },
});
const emit = defineEmits(['update:allowlist']);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t: $t } = useI18n();

const mcpToolService = inject('mcpToolService');

const availableTools = ref([]);
const availableResources = ref([]);
const availablePrompts = ref([]);
const isLoading = ref(false);
const openTypes = ref(new Set());
const openGroups = ref(new Set());
const openItems = ref(new Set());

const allCapabilitiesEnabled = computed({
    get: () => {
        return props.allowlist === null;
    },
    set: (enabled) => {
        emit('update:allowlist', enabled ? null : { tools: null, resources: null, prompts: null });
    },
});
const toolsAllowlist = computed(() => {
    if (props.allowlist === null) return null;
    return props.allowlist.tools ?? null;
});
const resourcesAllowlist = computed(() => {
    if (props.allowlist === null) return null;
    return props.allowlist.resources ?? null;
});
const promptsAllowlist = computed(() => {
    if (props.allowlist === null) return null;
    return props.allowlist.prompts ?? null;
});
const toolGroups = computed(() => {
    return buildGroups(availableTools.value, (tool) => tool.name.split('-')[0] ?? 'other');
});
const resourceGroups = computed(() => {
    return buildGroups(availableResources.value, (resource) => {
        const match = resource.uri.match(/^([a-zA-Z0-9_-]+):\/\//);
        return match ? match[1] : (resource.name?.split('-')[0] ?? 'other');
    });
});
const promptGroups = computed(() => {
    return buildGroups(availablePrompts.value, (prompt) => prompt.name.split('-')[0] ?? 'other');
});
const typeConfigs = computed(() => {
    return [
        {
            key: 'tools',
            titleKey: 'ct-integration.mcp.toolsSection',
            guidanceKey: 'ct-integration.mcp.toolsGuidance',
            available: availableTools.value,
            groups: toolGroups.value,
        },
        {
            key: 'resources',
            titleKey: 'ct-integration.mcp.resourcesSection',
            guidanceKey: 'ct-integration.mcp.resourcesGuidance',
            available: availableResources.value,
            groups: resourceGroups.value,
        },
        {
            key: 'prompts',
            titleKey: 'ct-integration.mcp.promptsSection',
            guidanceKey: 'ct-integration.mcp.promptsGuidance',
            available: availablePrompts.value,
            groups: promptGroups.value,
        },
    ];
});
const staleToolNames = computed(() => {
    if (toolsAllowlist.value === null) return [];
    const available = availableTools.value.map((t) => t.name);
    return toolsAllowlist.value.filter((name) => !available.includes(name));
});
const staleResourceUris = computed(() => {
    if (resourcesAllowlist.value === null) return [];
    const available = availableResources.value.map((r) => r.uri);
    return resourcesAllowlist.value.filter((uri) => !available.includes(uri));
});
const stalePromptNames = computed(() => {
    if (promptsAllowlist.value === null) return [];
    const available = availablePrompts.value.map((p) => p.name);
    return promptsAllowlist.value.filter((name) => !available.includes(name));
});
const staleEntries = computed(() => {
    return [
        ...staleToolNames.value,
        ...staleResourceUris.value,
        ...stalePromptNames.value,
    ];
});
const uncoveredTools = computed(() => {
    if (props.isAdmin || props.grantedPrivileges.length === 0) {
        return [];
    }

    const toolsToCheck =
        toolsAllowlist.value === null
            ? availableTools.value
            : toolsAllowlist.value.map((name) => availableTools.value.find((t) => t.name === name)).filter(Boolean);

    return toolsToCheck.filter((tool) => missingPrivilegesForTool(tool.name).length > 0);
});
const deniedTypes = computed(() => {
    if (allCapabilitiesEnabled.value) return [];
    return typeConfigs.value
        .filter((tc) => !typeAllEnabled(tc.key) && typeSelectedCount(tc.key) === 0 && tc.available.length > 0)
        .map((tc) => tc.key);
});
const deniedTypesLabel = computed(() => {
    const labels = deniedTypes.value.map((t) => $t(`ct-integration.mcp.${t}Section`));
    if (labels.length === 0) return '';
    if (labels.length === 1) return labels[0];
    return `${labels.slice(0, -1).join(', ')} and ${labels[labels.length - 1]}`;
});
const missingCapabilitySuggestions = computed(() => {
    if (allCapabilitiesEnabled.value) return [];

    const activeToolNames = toolsAllowlist.value === null ? availableTools.value.map((t) => t.name) : toolsAllowlist.value;

    const activePrefixes = new Set(activeToolNames.map((n) => n.split('-')[0]));
    const suggestions = [];

    // Only check partially-restricted types; fully-denied types are already covered by the deniedTypes banner
    if (resourcesAllowlist.value !== null && resourcesAllowlist.value.length > 0) {
        Object.entries(resourceGroups.value).forEach(
            ([
                prefix,
                resources,
            ]) => {
                if (!activePrefixes.has(prefix)) return;
                resources.forEach((resource) => {
                    if (!resourcesAllowlist.value.includes(resource.uri)) {
                        suggestions.push({ kind: 'resource', name: resource.uri });
                    }
                });
            },
        );
    }

    if (promptsAllowlist.value !== null && promptsAllowlist.value.length > 0) {
        Object.entries(promptGroups.value).forEach(
            ([
                prefix,
                prompts,
            ]) => {
                if (!activePrefixes.has(prefix)) return;
                prompts.forEach((prompt) => {
                    if (!promptsAllowlist.value.includes(prompt.name)) {
                        suggestions.push({ kind: 'prompt', name: prompt.name });
                    }
                });
            },
        );
    }

    return suggestions;
});
const allMissingPrivileges = computed(() => {
    const all = new Set();
    uncoveredTools.value.forEach((tool) => {
        missingPrivilegesForTool(tool.name).forEach((p) => all.add(p));
    });
    return [...all].sort();
});

const loadCapabilities = () => {
    isLoading.value = true;

    mcpToolService
        .getCapabilities()
        .then((data) => {
            availableTools.value = data.tools ?? [];
            availableResources.value = data.resources ?? [];
            availablePrompts.value = data.prompts ?? [];
        })
        .catch(() => {
            availableTools.value = [];
            availableResources.value = [];
            availablePrompts.value = [];
        })
        .finally(() => {
            isLoading.value = false;
        });
};
const emitUpdated = (patch) => {
    const current = props.allowlist ?? { tools: null, resources: null, prompts: null };
    emit('update:allowlist', { ...current, ...patch });
};
const isTypeExpanded = (type) => {
    return openTypes.value.has(type);
};
const toggleType = (type) => {
    const next = new Set(openTypes.value);
    if (next.has(type)) {
        next.delete(type);
    } else {
        next.add(type);
    }
    openTypes.value = next;
};
const isGroupExpanded = (type, group) => {
    return openGroups.value.has(`${type}:${group}`);
};
const toggleGroup = (type, group) => {
    const key = `${type}:${group}`;
    const next = new Set(openGroups.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    openGroups.value = next;
};
const expandAllGroups = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return;
    const next = new Set(openGroups.value);
    Object.keys(config.groups).forEach((group) => next.add(`${type}:${group}`));
    openGroups.value = next;
};
const collapseAllGroups = (type) => {
    const next = new Set([...openGroups.value].filter((k) => !k.startsWith(`${type}:`)));
    openGroups.value = next;
};
const areAllGroupsExpanded = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return false;
    const keys = Object.keys(config.groups);
    return keys.length > 0 && keys.every((group) => isGroupExpanded(type, group));
};
const isItemExpanded = (type, item) => {
    return openItems.value.has(`${type}:${itemKey(type, item)}`);
};
const toggleItem = (type, item) => {
    const key = `${type}:${itemKey(type, item)}`;
    const next = new Set(openItems.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    openItems.value = next;
};
const expandAllItemsInGroup = (type, group) => {
    const next = new Set(openItems.value);
    getGroupItems(type, group)
        .filter((item) => !!item.description)
        .forEach((item) => next.add(`${type}:${itemKey(type, item)}`));
    openItems.value = next;
};
const collapseAllItemsInGroup = (type, group) => {
    const keys = new Set(getGroupItems(type, group).map((item) => `${type}:${itemKey(type, item)}`));
    openItems.value = new Set([...openItems.value].filter((k) => !keys.has(k)));
};
const areAllItemsInGroupExpanded = (type, group) => {
    const expandable = getGroupItems(type, group).filter((item) => !!item.description);
    return expandable.length > 0 && expandable.every((item) => isItemExpanded(type, item));
};
const onToggleTypeAll = (type, enabled) => {
    emitUpdated({ [type]: enabled ? null : [] });
};
const isToolSelected = (toolName) => {
    if (toolsAllowlist.value === null) return true;
    return toolsAllowlist.value.includes(toolName);
};
const onToggleTool = (toolName, isSelected) => {
    const current = toolsAllowlist.value ?? [];
    let updated;

    if (isSelected) {
        const tool = availableTools.value.find((t) => t.name === toolName);
        const deps = tool?.dependencies ?? [];
        const toAdd = [
            toolName,
            ...deps,
        ].filter((n) => !current.includes(n));
        updated = [
            ...current,
            ...toAdd,
        ];
    } else {
        updated = current.filter((n) => n !== toolName);
    }

    emitUpdated({ tools: updated });
};
const isDependency = (toolName) => {
    if (toolsAllowlist.value === null) return false;
    return toolsAllowlist.value.some((selected) => {
        const tool = availableTools.value.find((t) => t.name === selected);
        return tool?.dependencies?.includes(toolName) ?? false;
    });
};
const missingDependencies = (tool) => {
    if (!isToolSelected(tool.name)) return [];
    return (tool.dependencies ?? []).filter((dep) => !isToolSelected(dep));
};
const privilegeChipClass = (chip) => {
    if (props.isAdmin || props.grantedPrivileges.length === 0 || typeof chip !== 'string' || chip.startsWith('<')) {
        return 'neutral';
    }

    return isPrivilegeGranted(chip, props.grantedPrivileges) ? 'positive' : 'critical';
};
const privilegeChips = (tool) => {
    return computePrivilegeChips(tool.requiredPrivileges);
};
const missingPrivilegesForTool = (toolName) => {
    const tool = availableTools.value.find((t) => t.name === toolName);
    const reqs = tool?.requiredPrivileges;
    if (!reqs?.static?.length) return [];
    return reqs.static.filter((priv) => !isPrivilegeGranted(priv, props.grantedPrivileges));
};
const itemKey = (type, item) => {
    return type === 'resources' ? item.uri : item.name;
};
const onToggleItem = (type, item, selected) => {
    if (type === 'tools') onToggleTool(item.name, selected);
    else if (type === 'resources') onToggleResource(item.uri, selected);
    else if (type === 'prompts') onTogglePrompt(item.name, selected);
};
const isResourceSelected = (uri) => {
    if (resourcesAllowlist.value === null) return true;
    return resourcesAllowlist.value.includes(uri);
};
const onToggleResource = (uri, isSelected) => {
    const current = resourcesAllowlist.value ?? [];
    const updated = isSelected
        ? [
              ...current,
              uri,
          ]
        : current.filter((u) => u !== uri);
    emitUpdated({ resources: updated });
};
const isPromptSelected = (name) => {
    if (promptsAllowlist.value === null) return true;
    return promptsAllowlist.value.includes(name);
};
const onTogglePrompt = (name, isSelected) => {
    const current = promptsAllowlist.value ?? [];
    const updated = isSelected
        ? [
              ...current,
              name,
          ]
        : current.filter((n) => n !== name);
    emitUpdated({ prompts: updated });
};
const getGroupItems = (type, group) => {
    const maps = {
        tools: toolGroups.value,
        resources: resourceGroups.value,
        prompts: promptGroups.value,
    };
    return maps[type]?.[group] ?? [];
};
const isItemSelectedForGroup = (type, item) => {
    if (type === 'tools') return isToolSelected(item.name);
    if (type === 'resources') return isResourceSelected(item.uri);
    if (type === 'prompts') return isPromptSelected(item.name);
    return false;
};
const isGroupAllSelected = (type, group) => {
    const items = getGroupItems(type, group);
    return items.length > 0 && items.every((item) => isItemSelectedForGroup(type, item));
};
const isGroupPartiallySelected = (type, group) => {
    const items = getGroupItems(type, group);
    const count = items.filter((item) => isItemSelectedForGroup(type, item)).length;
    return count > 0 && count < items.length;
};
const onToggleGroupAll = (type, group, checked) => {
    const items = getGroupItems(type, group);

    if (type === 'tools') {
        const current = toolsAllowlist.value ?? [];
        const names = items.map((t) => t.name);
        if (checked) {
            const deps = names.flatMap((n) => {
                const tool = availableTools.value.find((t) => t.name === n);
                return tool?.dependencies ?? [];
            });
            const toAdd = [
                ...names,
                ...deps,
            ].filter((n) => !current.includes(n));
            emitUpdated({
                tools: [
                    ...current,
                    ...toAdd,
                ],
            });
        } else {
            emitUpdated({ tools: current.filter((n) => !names.includes(n)) });
        }
        return;
    }

    if (type === 'resources') {
        const current = resourcesAllowlist.value ?? [];
        const uris = items.map((r) => r.uri);
        const updated = checked
            ? [
                  ...current,
                  ...uris.filter((u) => !current.includes(u)),
              ]
            : current.filter((u) => !uris.includes(u));
        emitUpdated({ resources: updated });
        return;
    }

    if (type === 'prompts') {
        const current = promptsAllowlist.value ?? [];
        const names = items.map((p) => p.name);
        const updated = checked
            ? [
                  ...current,
                  ...names.filter((n) => !current.includes(n)),
              ]
            : current.filter((n) => !names.includes(n));
        emitUpdated({ prompts: updated });
    }
};
const groupLabel = (type, group) => {
    if (type === 'resources') {
        return humanizeLabel(group);
    }

    const items = getGroupItems(type, group);
    return humanizeCommonPrefix(items.map((item) => item.name));
};
const typeSelectedCount = (type) => {
    if (type === 'tools') {
        if (toolsAllowlist.value === null) return availableTools.value.length;
        return toolsAllowlist.value.filter((n) => availableTools.value.some((t) => t.name === n)).length;
    }
    if (type === 'resources') {
        if (resourcesAllowlist.value === null) return availableResources.value.length;
        return resourcesAllowlist.value.filter((u) => availableResources.value.some((r) => r.uri === u)).length;
    }
    if (type === 'prompts') {
        if (promptsAllowlist.value === null) return availablePrompts.value.length;
        return promptsAllowlist.value.filter((n) => availablePrompts.value.some((p) => p.name === n)).length;
    }
    return 0;
};
const isFlatType = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return false;
    const groupKeys = Object.keys(config.groups);
    if (groupKeys.length === 0) return false;
    // Single group, or every group has exactly one item
    return groupKeys.length === 1 || groupKeys.every((g) => (config.groups[g]?.length ?? 0) === 1);
};
const flatTypeHasExpandableItems = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return false;
    return Object.keys(config.groups).some((g) => getGroupItems(type, g).filter((i) => !!i.description).length > 0);
};
const expandAllItemsInType = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return;
    Object.keys(config.groups).forEach((g) => expandAllItemsInGroup(type, g));
};
const collapseAllItemsInType = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return;
    Object.keys(config.groups).forEach((g) => collapseAllItemsInGroup(type, g));
};
const areAllItemsInTypeExpanded = (type) => {
    const config = typeConfigs.value.find((c) => c.key === type);
    if (!config) return false;
    const expandable = Object.keys(config.groups).flatMap((g) => getGroupItems(type, g).filter((i) => !!i.description));
    return expandable.length > 0 && expandable.every((item) => isItemExpanded(type, item));
};
const typeTotal = (type) => {
    if (type === 'tools') return availableTools.value.length;
    if (type === 'resources') return availableResources.value.length;
    if (type === 'prompts') return availablePrompts.value.length;
    return 0;
};
const typeAllEnabled = (type) => {
    if (type === 'tools') return toolsAllowlist.value === null;
    if (type === 'resources') return resourcesAllowlist.value === null;
    if (type === 'prompts') return promptsAllowlist.value === null;
    return true;
};

loadCapabilities();

swDefinePublic({
    mcpToolService,
    availableTools,
    availableResources,
    availablePrompts,
    isLoading,
    openTypes,
    openGroups,
    openItems,
    allCapabilitiesEnabled,
    toolsAllowlist,
    resourcesAllowlist,
    promptsAllowlist,
    toolGroups,
    resourceGroups,
    promptGroups,
    typeConfigs,
    staleToolNames,
    staleResourceUris,
    stalePromptNames,
    staleEntries,
    uncoveredTools,
    deniedTypes,
    deniedTypesLabel,
    missingCapabilitySuggestions,
    allMissingPrivileges,
    loadCapabilities,
    emitUpdated,
    isTypeExpanded,
    toggleType,
    isGroupExpanded,
    toggleGroup,
    expandAllGroups,
    collapseAllGroups,
    areAllGroupsExpanded,
    isItemExpanded,
    toggleItem,
    expandAllItemsInGroup,
    collapseAllItemsInGroup,
    areAllItemsInGroupExpanded,
    onToggleTypeAll,
    isToolSelected,
    onToggleTool,
    isDependency,
    missingDependencies,
    privilegeChipClass,
    privilegeChips,
    missingPrivilegesForTool,
    itemKey,
    onToggleItem,
    isResourceSelected,
    onToggleResource,
    isPromptSelected,
    onTogglePrompt,
    getGroupItems,
    isItemSelectedForGroup,
    isGroupAllSelected,
    isGroupPartiallySelected,
    onToggleGroupAll,
    groupLabel,
    typeSelectedCount,
    isFlatType,
    flatTypeHasExpandableItems,
    expandAllItemsInType,
    collapseAllItemsInType,
    areAllItemsInTypeExpanded,
    typeTotal,
    typeAllEnabled,
});

defineExpose({
    mcpToolService,
    availableTools,
    availableResources,
    availablePrompts,
    isLoading,
    openTypes,
    openGroups,
    openItems,
    allCapabilitiesEnabled,
    toolsAllowlist,
    resourcesAllowlist,
    promptsAllowlist,
    toolGroups,
    resourceGroups,
    promptGroups,
    typeConfigs,
    staleToolNames,
    staleResourceUris,
    stalePromptNames,
    staleEntries,
    uncoveredTools,
    deniedTypes,
    deniedTypesLabel,
    missingCapabilitySuggestions,
    allMissingPrivileges,
    loadCapabilities,
    emitUpdated,
    isTypeExpanded,
    toggleType,
    isGroupExpanded,
    toggleGroup,
    expandAllGroups,
    collapseAllGroups,
    areAllGroupsExpanded,
    isItemExpanded,
    toggleItem,
    expandAllItemsInGroup,
    collapseAllItemsInGroup,
    areAllItemsInGroupExpanded,
    onToggleTypeAll,
    isToolSelected,
    onToggleTool,
    isDependency,
    missingDependencies,
    privilegeChipClass,
    privilegeChips,
    missingPrivilegesForTool,
    itemKey,
    onToggleItem,
    isResourceSelected,
    onToggleResource,
    isPromptSelected,
    onTogglePrompt,
    getGroupItems,
    isItemSelectedForGroup,
    isGroupAllSelected,
    isGroupPartiallySelected,
    onToggleGroupAll,
    groupLabel,
    typeSelectedCount,
    isFlatType,
    flatTypeHasExpandableItems,
    expandAllItemsInType,
    collapseAllItemsInType,
    areAllItemsInTypeExpanded,
    typeTotal,
    typeAllEnabled,
});
</script>
