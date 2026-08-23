<template>
    <ct-block name="sw_integration_list">
        <ct-page class="ct-integration-list">
            <template #search-bar>
                <ct-block name="sw_integration_list_search_bar">
                    <mt-search
                        :model-value="term"
                        :placeholder="translate('ct-integration.general.placeholderSearchBar')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_integration_list_smart_bar_header">
                    <ct-block name="sw_integration_list_smart_bar_header_title">
                        <h2>
                            <ct-block name="sw_integration_list_smart_bar_header_title_text">
                                <span>{{ translate('ct-settings.index.title') }}</span>

                                <mt-icon name="regular-chevron-right-xs" size="12px" />

                                <span>{{ translate('ct-integration.general.headlineIntegrations') }}</span>
                            </ct-block>

                            <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_integration_list_smart_bar_actions">
                    <mt-button
                        v-tooltip.bottom="{
                            message: translate('ct-privileges.tooltip.warning'),
                            disabled: acl.can('integration.creator'),
                            showOnDisabledElements: true,
                        }"
                        class="ct-integration-list__add-integration-action"
                        variant="primary"
                        :disabled="!acl.can('integration.creator')"
                        size="default"
                        @click="onCreateIntegration"
                    >
                        {{ translate('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_integration_list_content">
                    <ct-card-view class="ct-integration-list__content">
                        <ct-block name="sw_integration_list_overview">
                            <mt-card
                                class="ct-integration-list__overview"
                                position-identifier="ct-integration-list-overview"
                                :is-loading="isLoading"
                            >
                                <ct-block name="sw_integration_list_overview_inner">
                                    <ct-block name="sw_integration_list_detail_modal">
                                        <ct-modal
                                            v-if="currentIntegration"
                                            size="650px"
                                            class="ct-integration-list__detail"
                                            :is-loading="isModalLoading"
                                            :title="
                                                showSecretAccessKey
                                                    ? translate('ct-integration.detail.detailModalTitle')
                                                    : translate('ct-integration.detail.detailModalTitleEdit') +
                                                      ' ' +
                                                      currentIntegration.label
                                            "
                                            @modal-close="onCloseDetailModal"
                                        >
                                            <ct-block name="sw_integration_list_detail_modal_inner">
                                                <ct-block name="sw_integration_list_detail_modal_inner_field_label">
                                                    <ct-container columns="2fr 1fr" gap="24px">
                                                        <mt-text-field
                                                            v-model="currentIntegration.label"
                                                            name="ct-field--currentIntegration-label"
                                                            :label="translate('ct-integration.detail.labelFieldLabel')"
                                                            :disabled="!acl.can('integration.editor')"
                                                        />

                                                        <ct-block name="sw_integration_list_detail_modal_inner_acl_is_admin">
                                                            <mt-switch
                                                                v-model="currentIntegration.admin"
                                                                name="ct-field--currentIntegration-admin"
                                                                class="ct-settings-user-detail__grid-is-admin"
                                                                :label="translate('ct-users.user-detail.labelAdministrator')"
                                                                :disabled="!acl.can('admin')"
                                                                bordered
                                                            />
                                                        </ct-block>
                                                    </ct-container>
                                                </ct-block>

                                                <ct-block name="sw_integration_list_detail_modal_inner_acl_roles">
                                                    <ct-entity-multi-select
                                                        v-model:entity-collection="currentIntegration.aclRoles"
                                                        v-tooltip="{
                                                            showDelay: 300,
                                                            message: translate(
                                                                'ct-users.user-detail.disabledRoleSelectWarning',
                                                            ),
                                                            disabled:
                                                                !currentIntegration.admin || !acl.can('integration.editor'),
                                                        }"
                                                        name="ct-field--currentIntegration-aclRoles"
                                                        class="ct-settings-user-detail__grid-aclRoles"
                                                        :label="translate('ct-users.user-detail.labelRoles')"
                                                        :disabled="
                                                            currentIntegration.admin || !acl.can('integration.editor')
                                                        "
                                                        label-property="name"
                                                    />
                                                </ct-block>

                                                <ct-block name="sw_integration_list_detail_modal_inner_field_accesskey">
                                                    <mt-text-field
                                                        v-model="currentIntegration.accessKey"
                                                        name="ct-field--currentIntegration-accessKey"
                                                        :label="translate('ct-integration.detail.idFieldLabel')"
                                                        :disabled="true"
                                                        :copyable="true"
                                                        :copyable-tooltip="true"
                                                    />
                                                </ct-block>

                                                <ct-block
                                                    name="sw_integration_list_detail_modal_inner_field_secretaccesskey"
                                                >
                                                    <template v-if="showSecretAccessKey">
                                                        <mt-text-field
                                                            v-if="secretAccessKeyFieldTypeIsText"
                                                            v-model="currentIntegration.secretAccessKey"
                                                            name="ct-field--currentIntegration-secretAccessKey"
                                                            :label="translate('ct-integration.detail.secretFieldLabel')"
                                                            :disabled="true"
                                                            :password-toggle-able="false"
                                                            :copyable="showSecretAccessKey"
                                                            :copyable-tooltip="true"
                                                        />

                                                        <mt-password-field
                                                            v-if="secretAccessKeyFieldTypeIsPassword"
                                                            v-model="currentIntegration.secretAccessKey"
                                                            name="ct-field--currentIntegration-secretAccessKey"
                                                            :label="translate('ct-integration.detail.secretFieldLabel')"
                                                            :disabled="true"
                                                            :password-toggle-able="false"
                                                            :copyable="showSecretAccessKey"
                                                            :copyable-tooltip="true"
                                                        />
                                                    </template>

                                                    <mt-button
                                                        v-if="!showSecretAccessKey"
                                                        variant="critical"
                                                        :disabled="!acl.can('integration.editor')"
                                                        :block="true"
                                                        @click="onGenerateKeys"
                                                    >
                                                        {{ translate('ct-integration.detail.buttonCreateNewApiKeys') }}
                                                    </mt-button>

                                                    <ct-block name="sw_integration_list_detail_modal_inner_field_helpText">
                                                        <div
                                                            v-if="!showSecretAccessKey"
                                                            class="ct-integration-list__help-text"
                                                        >
                                                            {{ translate('ct-integration.detail.hintCreateNewApiKeys') }}
                                                        </div>
                                                    </ct-block>
                                                </ct-block>

                                                <ct-block name="sw_integration_list_detail_modal_inner_helptext">
                                                    <mt-banner
                                                        v-if="showSecretAccessKey"
                                                        variant="attention"
                                                        class="ct-integration-list__secret-help-text-alert"
                                                    >
                                                        {{ translate('ct-integration.detail.secretHelpText') }}
                                                    </mt-banner>
                                                </ct-block>
                                            </ct-block>

                                            <template #modal-footer>
                                                <ct-block name="sw_integration_list_detail_modal_inner_footer">
                                                    <ct-block name="sw_integration_list_detail_modal_inner_footer_cancel">
                                                        <mt-button
                                                            size="small"
                                                            :disabled="isModalLoading"
                                                            variant="secondary"
                                                            @click="onCloseDetailModal"
                                                        >
                                                            {{ translate('global.default.cancel') }}
                                                        </mt-button>
                                                    </ct-block>

                                                    <ct-block name="sw_integration_list_detail_modal_inner_footer_apply">
                                                        <mt-button
                                                            size="small"
                                                            class="ct-integration-detail-modal__save-action"
                                                            :disabled="
                                                                (isModalLoading && !!currentIntegration.label) ||
                                                                !acl.can('integration.editor')
                                                            "
                                                            variant="primary"
                                                            @click="onSaveIntegration"
                                                        >
                                                            {{
                                                                showSecretAccessKey
                                                                    ? translate('ct-integration.detail.buttonApply')
                                                                    : translate('ct-integration.detail.buttonApplyEdit')
                                                            }}
                                                        </mt-button>
                                                    </ct-block>
                                                </ct-block>
                                            </template>
                                        </ct-modal>
                                    </ct-block>

                                    <ct-block name="sw_integration_list_mcp_modal">
                                        <ct-modal
                                            v-if="mcpIntegration"
                                            class="ct-integration-list__mcp-modal"
                                            :title="translate('ct-integration.mcp.modalTitle') + ': ' + mcpIntegration.label"
                                            @modal-close="onCloseMcpModal"
                                        >
                                            <ct-block name="sw_integration_list_mcp_modal_allowlist">
                                                <ct-integration-mcp-allowlist
                                                    :allowlist="pendingMcpAllowlist"
                                                    :is-admin="mcpIntegration.admin"
                                                    :disabled="!acl.can('integration_mcp.editor')"
                                                    :granted-privileges="mcpGrantedPrivileges"
                                                    @update:allowlist="pendingMcpAllowlist = $event"
                                                />
                                            </ct-block>

                                            <template #modal-footer>
                                                <ct-block name="sw_integration_list_mcp_modal_footer">
                                                    <mt-button size="small" variant="secondary" @click="onCloseMcpModal">
                                                        {{ translate('global.default.cancel') }}
                                                    </mt-button>

                                                    <mt-button
                                                        size="small"
                                                        variant="primary"
                                                        :disabled="!acl.can('integration_mcp.editor')"
                                                        @click="onSaveMcpAllowlist"
                                                    >
                                                        {{ translate('ct-integration.detail.buttonApplyEdit') }}
                                                    </mt-button>
                                                </ct-block>
                                            </template>
                                        </ct-modal>
                                    </ct-block>

                                    <ct-block name="sw_integration_list_grid">
                                        <ct-entity-listing
                                            v-if="integrations && integrations.length > 0"
                                            class="ct-integration-list__grid"
                                            :data-source="integrations"
                                            :columns="integrationColumns"
                                            :repository="integrationRepository"
                                            :full-page="false"
                                            :plain-appearance="true"
                                            :compact-mode="true"
                                            :allow-column-edit="false"
                                            :show-selection="false"
                                            :show-settings="false"
                                            :allow-view="acl.can('integration.viewer')"
                                            :is-loading="isLoading"
                                        >
                                            <template #column-label="{ item }">
                                                <ct-block name="sw_integration_list_grid_inner_slot_columns_label">
                                                    <span class="ct-integration-list__app-icon">
                                                        <mt-icon name="regular-share" size="12px" />
                                                    </span>

                                                    <mt-link as="a" @click.prevent="onShowDetailModal(item)">
                                                        {{ item.label }}
                                                    </mt-link>
                                                </ct-block>
                                            </template>

                                            <template #column-writeAccess="{ item }">
                                                <ct-block name="sw_integration_list_grid_inner_slot_columns_writeAccess">
                                                    <mt-badge v-if="item.admin" variant="attention">
                                                        {{ translate('ct-users.user-detail.labelAdministrator') }}
                                                    </mt-badge>

                                                    <span v-if="!item.admin && item.aclRoles && item.aclRoles.length">
                                                        <mt-badge v-for="role in item.aclRoles" :key="role.name">
                                                            {{ role.name }}
                                                        </mt-badge>
                                                    </span>
                                                </ct-block>
                                            </template>

                                            <template #actions="{ item }">
                                                <ct-block name="sw_integration_list_grid_inner_slot_columns_actions_edit">
                                                    <ct-context-menu-item
                                                        class="sw_integration_list__edit-action"
                                                        :disabled="!acl.can('integration.editor')"
                                                        @click="onShowDetailModal(item)"
                                                    >
                                                        {{ translate('ct-integration.list.contextMenuEdit') }}
                                                    </ct-context-menu-item>
                                                </ct-block>

                                                <ct-block
                                                    name="sw_integration_list_grid_inner_slot_columns_actions_edit_mcp"
                                                >
                                                    <ct-context-menu-item
                                                        class="sw_integration_list__edit-mcp-action"
                                                        :disabled="!acl.can('integration_mcp.editor')"
                                                        @click="onShowMcpModal(item)"
                                                    >
                                                        {{ translate('ct-integration.list.contextMenuEditMcp') }}
                                                    </ct-context-menu-item>
                                                </ct-block>

                                                <ct-block name="sw_integration_list_grid_inner_slot_columns_actions_delete">
                                                    <ct-context-menu-item
                                                        class="sw_integration_list__delete-action"
                                                        variant="danger"
                                                        :disabled="!acl.can('integration.deleter')"
                                                        @click="showDeleteModal = item.id"
                                                    >
                                                        {{ translate('global.default.delete') }}
                                                    </ct-context-menu-item>
                                                </ct-block>
                                            </template>

                                            <template #action-modals="{ item }">
                                                <ct-block name="sw_integration_list_grid_inner_slot_delete_modal">
                                                    <ct-modal
                                                        v-if="showDeleteModal === item.id"
                                                        :title="translate('global.default.delete')"
                                                        @modal-close="onCloseDeleteModal"
                                                    >
                                                        <ct-block
                                                            name="sw_integration_list_grid_inner_slot_delete_modal_confirmtext"
                                                        >
                                                            <p>
                                                                {{ translate('ct-integration.detail.confirmDelete') }}
                                                                "{{ item.label }}"
                                                            </p>
                                                        </ct-block>

                                                        <template #modal-footer>
                                                            <ct-block
                                                                name="sw_integration_list_grid_inner_slot_delete_modal_footer"
                                                            >
                                                                <mt-button
                                                                    size="small"
                                                                    variant="secondary"
                                                                    @click="onCloseDeleteModal"
                                                                >
                                                                    {{ translate('global.default.cancel') }}
                                                                </mt-button>

                                                                <mt-button
                                                                    size="small"
                                                                    variant="critical"
                                                                    @click="onConfirmDelete(item.id)"
                                                                >
                                                                    {{ translate('global.default.delete') }}
                                                                </mt-button>
                                                            </ct-block>
                                                        </template>
                                                    </ct-modal>
                                                </ct-block>
                                            </template>
                                        </ct-entity-listing>
                                    </ct-block>

                                    <ct-block name="sw_integration_list_empty_state">
                                        <template v-if="integrations && integrations.length > 0"
                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                        >
                                        <mt-empty-state
                                            v-else
                                            :icon="$route.meta.$module.icon"
                                            :headline="translate('ct-integration.list.messageEmpty')"
                                        />
                                    </ct-block>
                                </ct-block>
                            </mt-card>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-integration-list.scss';
const {
    Data: { Criteria },
} = Contena;

defineProps({});

import { ref, computed, inject, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const translate = t;
const integrationService = inject('integrationService');
const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');

const integrations = ref(null);
const term = ref('');
const total = ref(0);
const isLoading = ref(false);
const isModalLoading = ref(false);
const showDeleteModal = ref(null);
const currentIntegration = ref(null);
const showSecretAccessKey = ref(false);
const mcpIntegration = ref(null);
const pendingMcpAllowlist = ref(null);

const integrationRepository = computed(() => {
    return repositoryFactory.create('integration');
});
const integrationCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    criteria.addFilter(Criteria.equals('deletedAt', null));
    criteria.setTerm(term.value);
    // Criteria is a local mutable query object, not component state.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    criteria.addSorting(Criteria.sort('label', 'ASC'));
    criteria.addAssociation('aclRoles');

    return criteria;
});
const mcpGrantedPrivileges = computed(() => {
    if (!mcpIntegration.value?.aclRoles) {
        return [];
    }

    return [...new Set(mcpIntegration.value.aclRoles.flatMap((role) => role.privileges ?? []))];
});
const secretAccessKeyFieldTypeIsText = computed(() => {
    return showSecretAccessKey.value;
});
const secretAccessKeyFieldTypeIsPassword = computed(() => {
    return !showSecretAccessKey.value;
});
const integrationColumns = computed(() => {
    return [
        {
            property: 'label',
            label: t('ct-integration.list.integrationName'),
            primary: true,
        },
        {
            property: 'writeAccess',
            label: t('ct-integration.list.permissions'),
        },
    ];
});

const createdComponent = () => {
    getList();
};
const onSearch = (value) => {
    term.value = value;
    getList();
};
const getList = () => {
    isLoading.value = true;
    return integrationRepository.value
        .search(integrationCriteria.value)
        .then((integrationsValue) => {
            integrations.value = integrationsValue;
            total.value = integrationsValue.total ?? integrationsValue.length;
        })
        .finally(() => {
            isLoading.value = false;
        });
};
const onSaveIntegration = () => {
    if (!currentIntegration.value) {
        return;
    }

    const integration = integrations.value.find((a) => a.id === currentIntegration.value.id);

    if (typeof integration === 'undefined') {
        createIntegration();
    } else {
        updateIntegration(integration);
    }
};
const updateIntegration = (integration) => {
    isModalLoading.value = true;
    const shouldSaveAdminFlagValue = shouldSaveAdminFlag(integration);
    integrationRepository.value
        .save(integration)
        .then(() => {
            return updateAdminFlagIfNecessary(integration, shouldSaveAdminFlagValue);
        })
        .then(() => {
            return getList();
        })
        .then(() => {
            createSavedSuccessNotification();
            onCloseDetailModal();
        })
        .catch(() => {
            createSavedErrorNotification();
            onCloseDetailModal();
        });
};
const createIntegration = () => {
    if (!currentIntegration.value.label || !currentIntegration.value.label.length) {
        createSavedErrorNotification();
        return;
    }
    isModalLoading.value = true;
    const integration = currentIntegration.value;
    const shouldSaveAdminFlagValue = shouldSaveAdminFlag(integration);
    integrationRepository.value
        .save(integration)
        .then(() => {
            return updateAdminFlagIfNecessary(integration, shouldSaveAdminFlagValue);
        })
        .then(() => {
            return getList();
        })
        .then(() => {
            createSavedSuccessNotification();
        })
        .catch(() => {
            createSavedErrorNotification();
        })
        .finally(() => {
            void nextTick(() => {
                onCloseDetailModal();
            });
        });
};
const shouldSaveAdminFlag = (integration) => {
    if (!integration || typeof integration.getOrigin !== 'function') {
        return false;
    }

    const origin = integration.getOrigin();

    return Boolean(origin?.admin) !== Boolean(integration.admin);
};
const updateAdminFlagIfNecessary = (integration, shouldSaveAdminFlag) => {
    if (!shouldSaveAdminFlag) {
        return Promise.resolve();
    }

    return integrationService.updateAdmin(integration.id, integration.admin);
};
const createSavedSuccessNotification = () => {
    createNotificationSuccess({
        message: t('ct-integration.detail.messageSaveSuccess'),
    });
};
const createSavedErrorNotification = () => {
    createNotificationError({
        message: t('ct-integration.detail.messageSaveError'),
    });
};
const onGenerateKeys = () => {
    if (!currentIntegration.value) {
        return;
    }

    isModalLoading.value = true;

    integrationService
        .generateKey()
        .then((response) => {
            currentIntegration.value = currentIntegration.value || integrationRepository.value.create();
            currentIntegration.value.accessKey = response.accessKey;
            currentIntegration.value.secretAccessKey = response.secretAccessKey;
            showSecretAccessKey.value = true;
            isModalLoading.value = false;
        })
        .catch(() => {
            createNotificationError({
                message: t('ct-integration.detail.messageCreateNewError'),
            });
        });
};
const onShowDetailModal = (integration) => {
    currentIntegration.value = integration;
};
const onCreateIntegration = () => {
    currentIntegration.value = integrationRepository.value.create();

    onGenerateKeys();
};
const onCloseDetailModal = () => {
    currentIntegration.value = null;
    showSecretAccessKey.value = false;
    isModalLoading.value = false;
};
const onShowMcpModal = (integration) => {
    mcpIntegration.value = integration;
    pendingMcpAllowlist.value = integration.mcpAllowlist ? { ...integration.mcpAllowlist } : null;
};
const onCloseMcpModal = () => {
    mcpIntegration.value = null;
    pendingMcpAllowlist.value = null;
};
const onSaveMcpAllowlist = () => {
    if (!mcpIntegration.value) {
        return;
    }

    integrationService
        .saveMcpAllowlist(mcpIntegration.value.id, pendingMcpAllowlist.value)
        .then(() => {
            mcpIntegration.value.mcpAllowlist = pendingMcpAllowlist.value;
            createSavedSuccessNotification();
            onCloseMcpModal();
        })
        .catch(() => {
            createSavedErrorNotification();
        });
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = null;
};
const onConfirmDelete = (id) => {
    if (!id) {
        return;
    }

    onCloseDeleteModal();

    integrationRepository.value.delete(id).then(() => {
        getList();
    });
};

createdComponent();

swDefinePublic({
    integrationService,
    repositoryFactory,
    acl,
    integrations,
    term,
    total,
    isLoading,
    isModalLoading,
    showDeleteModal,
    currentIntegration,
    showSecretAccessKey,
    mcpIntegration,
    pendingMcpAllowlist,
    integrationRepository,
    integrationCriteria,
    mcpGrantedPrivileges,
    secretAccessKeyFieldTypeIsText,
    secretAccessKeyFieldTypeIsPassword,
    integrationColumns,
    createdComponent,
    onSearch,
    getList,
    onSaveIntegration,
    updateIntegration,
    createIntegration,
    shouldSaveAdminFlag,
    updateAdminFlagIfNecessary,
    createSavedSuccessNotification,
    createSavedErrorNotification,
    onGenerateKeys,
    onShowDetailModal,
    onCreateIntegration,
    onCloseDetailModal,
    onShowMcpModal,
    onCloseMcpModal,
    onSaveMcpAllowlist,
    onCloseDeleteModal,
    onConfirmDelete,
});
usePageTitle();

defineExpose({
    integrationService,
    repositoryFactory,
    acl,
    integrations,
    term,
    total,
    isLoading,
    isModalLoading,
    showDeleteModal,
    currentIntegration,
    showSecretAccessKey,
    mcpIntegration,
    pendingMcpAllowlist,
    integrationRepository,
    integrationCriteria,
    mcpGrantedPrivileges,
    secretAccessKeyFieldTypeIsText,
    secretAccessKeyFieldTypeIsPassword,
    integrationColumns,
    createdComponent,
    onSearch,
    getList,
    onSaveIntegration,
    updateIntegration,
    createIntegration,
    shouldSaveAdminFlag,
    updateAdminFlagIfNecessary,
    createSavedSuccessNotification,
    createSavedErrorNotification,
    onGenerateKeys,
    onShowDetailModal,
    onCreateIntegration,
    onCloseDetailModal,
    onShowMcpModal,
    onCloseMcpModal,
    onSaveMcpAllowlist,
    onCloseDeleteModal,
    onConfirmDelete,
});
</script>
