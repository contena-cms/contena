<template>
    <div class="ct-permissions-role-view-general">
        <ct-block name="sw_permissions_role_role_view_general_card_view_basic_information">
            <mt-card
                :title="$t('ct-permissions.roles.detail.basicInformation')"
                position-identifier="ct-permissions-role-view-general"
                :is-loading="isLoading"
            >
                <template v-if="role">
                    <ct-block name="sw_permissions_role_role_view_general_mcp_hint">
                        <mt-banner
                            v-if="shouldShowMcpHint"
                            variant="info"
                            class="ct-permissions-role-view-general__mcp-hint"
                        >
                            <span>{{ $t('ct-permissions.roles.mcpHint.text', { count: mcpIntegrations.length }) }}</span>

                            <mt-link
                                as="a"
                                class="ct-permissions-role-view-general__mcp-hint-link"
                                @click.prevent="onOpenMcpModal"
                            >
                                {{ $t('ct-permissions.roles.mcpHint.linkText') }}
                            </mt-link>
                        </mt-banner>
                    </ct-block>

                    <ct-block name="sw_permissions_role_role_view_general_card_view_basic_information_name">
                        <mt-text-field
                            v-model="role.name"
                            name="ct-field--role-name"
                            :error="roleNameError"
                            :disabled="isReadOnly"
                            :label="$t('ct-permissions.roles.detail.labelName')"
                            required
                        />
                    </ct-block>

                    <ct-block name="sw_permissions_role_role_view_general_card_view_basic_information_description">
                        <mt-textarea
                            v-model="role.description"
                            name="ct-field--role-description"
                            :error="roleDescriptionError"
                            :disabled="isReadOnly"
                            :label="$t('ct-permissions.roles.detail.labelDescription')"
                        />
                    </ct-block>
                </template>
            </mt-card>
        </ct-block>

        <ct-block name="sw_permissions_role_role_view_general_card_view_permissions">
            <ct-permissions-role-access
                v-if="role || isLoading"
                :role="role"
                :is-loading="isLoading"
                :disabled="isReadOnly"
            />
        </ct-block>

        <ct-block name="sw_permissions_role_role_view_general_card_view_additional_permissions"> </ct-block>

        <ct-block name="sw_permissions_role_role_view_general_mcp_modal">
            <ct-permissions-role-mcp-reference-modal
                v-if="showMcpModal"
                :role="role"
                :mcp-integrations="mcpIntegrations"
                @modal-close="onCloseMcpModal"
            />
        </ct-block>
    </div>
</template>

<script setup>
const { Criteria } = Contena.Data;

const props = defineProps({
    role: {
        type: Object,
        required: false,
        default: null,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { ref, computed, inject, watch } from 'vue';

const role = computed(() => props.role);

const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');

const mcpIntegrations = ref([]);
const showMcpModal = ref(false);

const roleNameError = computed(() => {
    const entity = props.role;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'name');
});
const roleDescriptionError = computed(() => {
    const entity = props.role;
    const isEntity = entity && typeof entity.getEntityName === 'function';

    if (!isEntity) {
        return null;
    }

    return Contena.Store.get('error').getApiError(entity, 'description');
});
const roleId = computed(() => {
    if (!props.role || props.role.isNew()) {
        return null;
    }

    return props.role.id;
});
const integrationRepository = computed(() => {
    return repositoryFactory.create('integration');
});
const isReadOnly = computed(() => {
    return !acl.can('users_and_permissions.editor') || undefined;
});
const shouldShowMcpHint = computed(() => {
    if (!props.role) {
        return false;
    }

    return !props.role.isNew() && mcpIntegrations.value.length > 0;
});

const loadMcpIntegrations = async () => {
    if (!roleId.value) {
        mcpIntegrations.value = [];
        return;
    }

    const criteria = new Criteria(1, 500);
    const currentRoleId = roleId.value;

    criteria.addFilter(Criteria.equals('admin', false));
    criteria.addFilter(Criteria.not('AND', [Criteria.equals('mcpAllowlist', null)]));
    criteria.addFilter(Criteria.equals('aclRoles.id', currentRoleId));
    criteria.addAssociation('aclRoles');

    try {
        const result = await integrationRepository.value.search(criteria);

        if (currentRoleId !== roleId.value) {
            return;
        }

        const elements = result.getElements ? Object.values(result.getElements()) : [...result];

        mcpIntegrations.value = elements.filter((integration) => {
            const allowlist = integration.mcpAllowlist;

            if (!allowlist) {
                return false;
            }

            return Array.isArray(allowlist.tools) || Array.isArray(allowlist.resources) || Array.isArray(allowlist.prompts);
        });
    } catch {
        if (currentRoleId !== roleId.value) {
            return;
        }

        mcpIntegrations.value = [];
    }
};
const onOpenMcpModal = () => {
    showMcpModal.value = true;
};
const onCloseMcpModal = () => {
    showMcpModal.value = false;
};

watch(
    () => roleId.value,
    () => {
        void loadMcpIntegrations();
    },
    { immediate: true },
);

swDefinePublic({
    acl,
    repositoryFactory,
    mcpIntegrations,
    showMcpModal,
    roleNameError,
    roleDescriptionError,
    roleId,
    integrationRepository,
    isReadOnly,
    shouldShowMcpHint,
    loadMcpIntegrations,
    onOpenMcpModal,
    onCloseMcpModal,
});

defineExpose({
    acl,
    repositoryFactory,
    mcpIntegrations,
    showMcpModal,
    roleNameError,
    roleDescriptionError,
    roleId,
    integrationRepository,
    isReadOnly,
    shouldShowMcpHint,
    loadMcpIntegrations,
    onOpenMcpModal,
    onCloseMcpModal,
});
</script>
