<template>
    <ct-block name="ct_permissions_role_mcp_reference_modal">
        <ct-modal
            class="ct-permissions-role-mcp-reference-modal"
            :title="translate('ct-permissions.roles.mcpModal.title')"
            @modal-close="closeModal"
        >
            <ct-block name="ct_permissions_role_mcp_reference_modal_content">
                <div class="ct-permissions-role-mcp-reference-modal__content">
                    <ct-block name="ct_permissions_role_mcp_reference_modal_save_reminder">
                        <mt-banner
                            v-if="hasPreselected"
                            variant="info"
                            class="ct-permissions-role-mcp-reference-modal__save-reminder"
                        >
                            {{ translate('ct-permissions.roles.mcpModal.saveReminder') }}
                        </mt-banner>
                    </ct-block>

                    <ct-block name="ct_permissions_role_mcp_reference_modal_toolbar">
                        <div class="ct-permissions-role-mcp-reference-modal__toolbar">
                            <mt-select
                                v-model="viewMode"
                                class="ct-permissions-role-mcp-reference-modal__toolbar-viewmode-select"
                                :options="filterOptions"
                                :small="true"
                            />

                            <mt-button
                                v-if="allMissingStatic.length > 0"
                                size="small"
                                variant="secondary"
                                @click="grantAllMissing"
                            >
                                {{ translate('ct-permissions.roles.mcpModal.grantAllMissing') }}
                            </mt-button>
                        </div>
                    </ct-block>

                    <ct-block name="ct_permissions_role_mcp_reference_modal_legend">
                        <div class="ct-permissions-role-mcp-reference-modal__legend">
                            <span class="ct-permissions-role-mcp-reference-modal__legend-label">
                                {{ translate('ct-permissions.roles.mcpModal.legendLabel') }}
                            </span>

                            <mt-badge
                                v-tooltip="translate('ct-permissions.roles.mcpModal.legendGrantedHint')"
                                class="ct-permissions-role-mcp-reference-modal__privilege-chip is--granted"
                                variant="positive"
                            >
                                {{ translate('ct-permissions.roles.mcpModal.legendGranted') }}
                            </mt-badge>

                            <mt-badge
                                v-tooltip="translate('ct-permissions.roles.mcpModal.legendMissingHint')"
                                class="ct-permissions-role-mcp-reference-modal__privilege-chip is--missing"
                                variant="critical"
                            >
                                {{ translate('ct-permissions.roles.mcpModal.legendMissing') }}
                            </mt-badge>

                            <mt-badge
                                v-tooltip="translate('ct-permissions.roles.mcpModal.legendDynamicHint')"
                                class="ct-permissions-role-mcp-reference-modal__privilege-chip is--dynamic"
                                variant="neutral"
                            >
                                {{ translate('ct-permissions.roles.mcpModal.legendDynamic') }}
                            </mt-badge>
                        </div>
                    </ct-block>
                </div>

                <ct-block name="ct_permissions_role_mcp_reference_modal_loading">
                    <mt-loader v-if="isLoading" />
                </ct-block>

                <ct-block name="ct_permissions_role_mcp_reference_modal_rows">
                    <template v-if="!isLoading">
                        <template v-if="displayRows.length > 0">
                            <div
                                v-for="row in displayRows"
                                :key="row.label"
                                class="ct-permissions-role-mcp-reference-modal__row"
                            >
                                <ct-block name="ct_permissions_role_mcp_reference_modal_row_label">
                                    <span class="ct-permissions-role-mcp-reference-modal__row-label">
                                        {{ row.label }}
                                    </span>
                                </ct-block>

                                <ct-block name="ct_permissions_role_mcp_reference_modal_row_chips">
                                    <div class="ct-permissions-role-mcp-reference-modal__row-chips">
                                        <mt-badge
                                            v-for="chip in row.chips"
                                            :key="chip.text"
                                            v-tooltip="
                                                chip.isDynamic
                                                    ? translate('ct-permissions.roles.mcpModal.dynamicPrivilegeHint')
                                                    : ''
                                            "
                                            class="ct-permissions-role-mcp-reference-modal__privilege-chip"
                                            :class="{
                                                'is--granted': !chip.isDynamic && chip.isGranted,
                                                'is--missing': !chip.isDynamic && !chip.isGranted,
                                                'is--dynamic': chip.isDynamic,
                                            }"
                                            :variant="getBadgeVariant(chip)"
                                        >
                                            {{ chip.text }}
                                        </mt-badge>
                                    </div>
                                </ct-block>

                                <ct-block name="ct_permissions_role_mcp_reference_modal_row_grant">
                                    <mt-button
                                        v-if="row.hasMissingStatic"
                                        size="small"
                                        variant="primary"
                                        ghost
                                        class="ct-permissions-role-mcp-reference-modal__row-grant"
                                        @click="grantRow(row)"
                                    >
                                        {{ translate('ct-permissions.roles.mcpModal.grant') }}
                                    </mt-button>
                                </ct-block>
                            </div>
                        </template>

                        <ct-block name="ct_permissions_role_mcp_reference_modal_empty">
                            <template v-if="displayRows.length > 0"
                                ><!-- Keeps the conditional chain connected across ct-block. --></template
                            >
                            <mt-empty-state
                                v-else
                                :icon="$route.meta.$module.icon"
                                :headline="translate('ct-permissions.roles.mcpModal.noToolsHeadline')"
                                :description="translate('ct-permissions.roles.mcpModal.noToolsDescription')"
                            />
                        </ct-block>
                    </template>
                </ct-block>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_permissions_role_mcp_reference_modal_footer">
                    <mt-button variant="primary" @click="closeModal">
                        {{ translate('global.default.close') }}
                    </mt-button>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import { colonToDot, computePrivilegeChips, isPrivilegeGranted } from 'src/core/helper/mcp-privilege.helper';
import './ct-permissions-role-mcp-reference-modal.scss';

const props = defineProps({
    role: {
        type: Object,
        required: true,
    },

    mcpIntegrations: {
        type: Array,
        default: () => [],
    },
});
const emit = defineEmits(['modal-close']);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const translate = t;
const mcpToolService = inject('mcpToolService');

const availableTools = ref([]);
const isLoading = ref(false);
const viewMode = ref('permission');
const hasPreselected = ref(false);

const filterOptions = computed(() => {
    return [
        {
            value: 'permission',
            label: t('ct-permissions.roles.mcpModal.viewByPermission'),
        },
        {
            value: 'tool',
            label: t('ct-permissions.roles.mcpModal.viewByTool'),
        },
    ];
});
const anyIntegrationAllowsAllTools = computed(() => {
    return props.mcpIntegrations.some((integration) => {
        const tools = integration.mcpAllowlist?.tools;
        return tools === null || tools === undefined;
    });
});
const allowlistedToolNames = computed(() => {
    if (anyIntegrationAllowsAllTools.value) {
        return availableTools.value.map((tool) => tool.name);
    }

    const names = new Set();

    props.mcpIntegrations.forEach((integration) => {
        (integration.mcpAllowlist?.tools ?? []).forEach((name) => names.add(name));
    });

    return [...names].sort();
});
const relevantTools = computed(() => {
    return availableTools.value
        .filter((tool) => {
            if (!allowlistedToolNames.value.includes(tool.name)) {
                return false;
            }
            const reqs = tool.requiredPrivileges;
            return (reqs?.static?.length ?? 0) > 0 || reqs?.entityParam != null;
        })
        .map((tool) => {
            const reqs = tool.requiredPrivileges;
            return {
                ...tool,
                staticPrivileges: reqs?.static ?? [],
                dynamicPrivileges: computePrivilegeChips(reqs).filter((c) => c.startsWith('<')),
            };
        });
});
const rolePrivileges = computed(() => {
    return props.role.privileges ?? [];
});
const displayRows = computed(() => {
    if (viewMode.value === 'permission') {
        const map = {};

        relevantTools.value.forEach((tool) => {
            [
                ...tool.staticPrivileges,
                ...tool.dynamicPrivileges,
            ].forEach((chip) => {
                const isDynamic = chip.startsWith('<');
                const entity = isDynamic ? '<entity>' : chip.split(':')[0];

                if (!map[entity]) {
                    map[entity] = { chips: [] };
                }

                if (!map[entity].chips.find((c) => c.text === chip)) {
                    map[entity].chips.push({
                        text: chip,
                        isDynamic,
                        isGranted: !isDynamic && isPrivilegeGranted(chip, rolePrivileges.value),
                    });
                }
            });
        });

        return Object.entries(map)
            .map(
                ([
                    label,
                    { chips },
                ]) => ({
                    label,
                    chips,
                    hasMissingStatic: chips.some((c) => !c.isDynamic && !c.isGranted),
                }),
            )
            .sort((a, b) => a.label.localeCompare(b.label));
    }

    return relevantTools.value
        .map((tool) => ({
            label: tool.name,
            chips: [
                ...tool.staticPrivileges.map((chip) => ({
                    text: chip,
                    isDynamic: false,
                    isGranted: isPrivilegeGranted(chip, rolePrivileges.value),
                })),
                ...tool.dynamicPrivileges.map((chip) => ({
                    text: chip,
                    isDynamic: true,
                    isGranted: false,
                })),
            ],
            hasMissingStatic: tool.staticPrivileges.some((chip) => !isPrivilegeGranted(chip, rolePrivileges.value)),
        }))
        .sort((a, b) => a.label.localeCompare(b.label));
});
const allMissingStatic = computed(() => {
    const missing = new Set();

    relevantTools.value.forEach((tool) => {
        tool.staticPrivileges.forEach((chip) => {
            if (!isPrivilegeGranted(chip, rolePrivileges.value)) {
                missing.add(chip);
            }
        });
    });

    return [...missing];
});

const getBadgeVariant = (chip) => {
    if (!chip.isDynamic && chip.isGranted) {
        return 'positive';
    }

    if (!chip.isDynamic && !chip.isGranted) {
        return 'critical';
    }

    return 'neutral';
};
const loadTools = () => {
    isLoading.value = true;

    mcpToolService
        .getTools()
        .then((tools) => {
            availableTools.value = tools;
        })
        .catch(() => {
            availableTools.value = [];
        })
        .finally(() => {
            isLoading.value = false;
        });
};
const grantPrivilege = (chip) => {
    const dotPriv = colonToDot(chip);
    if (!dotPriv) return;

    if (!rolePrivileges.value.includes(dotPriv)) {
        rolePrivileges.value.push(dotPriv);
    }

    const [
        entity,
        rolePart,
    ] = dotPriv.split('.');
    if (
        [
            'editor',
            'creator',
            'deleter',
        ].includes(rolePart)
    ) {
        const viewerPriv = `${entity}.viewer`;
        if (!rolePrivileges.value.includes(viewerPriv)) {
            rolePrivileges.value.push(viewerPriv);
        }
    }

    hasPreselected.value = true;
};
const grantAllMissing = () => {
    allMissingStatic.value.forEach((chip) => grantPrivilege(chip));
};
const grantRow = (row) => {
    row.chips.filter((c) => !c.isDynamic && !c.isGranted).forEach((c) => grantPrivilege(c.text));
};
const closeModal = () => {
    hasPreselected.value = false;
    emit('modal-close');
};

loadTools();

ctDefinePublic({
    mcpToolService,
    availableTools,
    isLoading,
    viewMode,
    hasPreselected,
    filterOptions,
    anyIntegrationAllowsAllTools,
    allowlistedToolNames,
    relevantTools,
    rolePrivileges,
    displayRows,
    allMissingStatic,
    getBadgeVariant,
    loadTools,
    grantPrivilege,
    grantAllMissing,
    grantRow,
    closeModal,
});

defineExpose({
    mcpToolService,
    availableTools,
    isLoading,
    viewMode,
    hasPreselected,
    filterOptions,
    anyIntegrationAllowsAllTools,
    allowlistedToolNames,
    relevantTools,
    rolePrivileges,
    displayRows,
    allMissingStatic,
    getBadgeVariant,
    loadTools,
    grantPrivilege,
    grantAllMissing,
    grantRow,
    closeModal,
});
</script>
