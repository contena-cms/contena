<template>
    <ct-block name="ct_users_user_detail_integrations">
        <ct-block name="ct_users_user_detail_card_integrations">
            <mt-card
                :title="translate('ct-users.user-detail.labelIntegrationsCard')"
                position-identifier="ct-users-user-detail-integrations"
            >
                <template #grid>
                    <ct-block name="ct_users_user_detail_key_grid">
                        <mt-data-table
                            class="ct-users-user-detail__integration-table"
                            :caption="translate('ct-users.user-detail.labelIntegrationsCard')"
                            :data-source="integrations"
                            :columns="integrationColumns"
                            :is-loading="isLoading"
                            :pagination-total-items="integrations.length"
                            :current-page="1"
                            :pagination-limit="25"
                            :disable-edit="true"
                            :disable-delete="!acl.can('users_and_permissions.editor') || undefined"
                            :additional-context-buttons="integrationContextButtons"
                            @context-select="onIntegrationContextSelect"
                            @item-delete="onIntegrationDelete"
                        >
                            <template #toolbar>
                                <ct-block name="ct_users_user_detail_grid_toolbar">
                                    <mt-button
                                        variant="secondary"
                                        size="default"
                                        :disabled="!acl.can('users_and_permissions.editor') || undefined"
                                        @click.stop.prevent="addAccessKey"
                                    >
                                        {{ translate('ct-users.user-detail.addAccessKey') }}
                                    </mt-button>
                                </ct-block>
                            </template>

                            <template #empty-state>
                                <mt-empty-state
                                    icon="regular-key"
                                    :headline="translate('ct-users.user-detail.noAccessKeysTitle')"
                                    :description="translate('ct-users.user-detail.noAccessKeysSubline')"
                                />
                            </template>
                        </mt-data-table>
                    </ct-block>
                </template>
            </mt-card>
        </ct-block>
    </ct-block>
</template>

<script setup>
import { inject } from 'vue';

const {
    integrations,
    isLoading,
    acl,
    translate,
    integrationColumns,
    integrationContextButtons,
    addAccessKey,
    onIntegrationContextSelect,
    onIntegrationDelete,
} = inject('ctUsersUserDetailContext');

ctDefinePublic({});
</script>
