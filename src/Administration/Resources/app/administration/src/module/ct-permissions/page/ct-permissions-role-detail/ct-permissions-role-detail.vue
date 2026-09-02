<template>
    <ct-block name="ct_permissions_role_detail">
        <ct-page class="ct-permissions-role-detail">
            <template #smart-bar-header>
                <ct-block name="ct_permissions_role_detail_smart_bar_header">
                    <ct-block name="ct_permissions_role_detail_smart_bar_header_title">
                        <h2 v-if="role && role.isNew() && role.name.length <= 0">
                            {{ t('ct-permissions.roles.general.labelCreateNewRole') }}
                        </h2>

                        <h2 v-else-if="role">
                            {{ role.name }}
                        </h2>

                        <h2 v-else>
                            {{ t('ct-permissions.roles.detail.role') }}
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_permissions_role_detail_smart_bar_actions">
                    <ct-block name="ct_permissions_role_detail_smart_bar_actions_button_cancel">
                        <mt-button variant="secondary" size="default" @click="onCancel">
                            {{ t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_permissions_role_detail_smart_bar_actions_button_save">
                        <ct-button-process
                            size="default"
                            class="ct-permissions-role-detail__button-save"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="isLoading || !acl.can('users_and_permissions.editor')"
                            variant="primary"
                            @update:process-success="saveFinish"
                            @click.prevent="onSave"
                        >
                            {{ t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_permissions_role_detail_content">
                    <ct-block name="ct_permissions_role_detail_content_card_view">
                        <ct-card-view>
                            <ct-block name="ct_permissions_role_detail_content_card_view_tabs">
                                <div position-identifier="ct-permissions-role-detail-content">
                                    <mt-tabs
                                        :items="roleTabItems"
                                        :default-item="$route.name"
                                        @new-item-active="onTabChange"
                                    />
                                </div>
                            </ct-block>

                            <ct-block name="ct_permissions_role_detail_content_router_view">
                                <div class="ct-permissions-role-detail__tab-content">
                                    <router-view v-slot="{ Component }">
                                        <component
                                            :is="Component"
                                            :role="role"
                                            :is-loading="isLoading"
                                            :detailed-privileges="detailedPrivileges"
                                        />
                                    </router-view>
                                </div>
                            </ct-block>
                        </ct-card-view>
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-permissions-role-detail.scss';

defineProps({});

import { ref, computed, inject, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const $route = route;
const repositoryFactory = inject('repositoryFactory');
const privileges = inject('privileges');
const userService = inject('userService');
const acl = inject('acl');

const isLoading = ref(true);
const isSaveSuccessful = ref(false);
const role = ref(null);
const detailedPrivileges = ref([]);

const identifier = computed(() => {
    return role.value?.name ?? '';
});
const roleTabItems = computed(() => {
    return [
        {
            label: t('ct-permissions.roles.tabs.general'),
            name: 'ct.permissions.role.detail.general',
        },
        {
            label: t('ct-permissions.roles.tabs.additional'),
            name: 'ct.permissions.role.detail.additional-permissions',
        },
        {
            label: t('ct-permissions.roles.tabs.detailed'),
            name: 'ct.permissions.role.detail.detailed-privileges',
        },
    ];
});
const languageId = computed(() => {
    return Contena.Store.get('session').languageId;
});
const roleRepository = computed(() => {
    return repositoryFactory.create('acl_role');
});
const roleId = computed(() => {
    return route.params.id?.toLowerCase();
});

const onTabChange = (routeName) => {
    void router.push({
        name: routeName,
        params: { id: route.params.id },
    });
};
const createdComponent = () => {
    if (!roleId.value) {
        createNewRole();
        return;
    }

    isLoading.value = true;
    void getRole().finally(() => {
        isLoading.value = false;
    });
};
const createNewRole = () => {
    isLoading.value = true;

    role.value = roleRepository.value.create();

    role.value.name = '';
    role.value.description = '';
    role.value.privileges = [];

    isLoading.value = false;
};
const getRole = async () => {
    role.value = await roleRepository.value.get(roleId.value);

    const filteredPrivileges = privileges.filterPrivilegesRoles(role.value.privileges);
    const allGeneralPrivileges = privileges.getPrivilegesForAdminPrivilegeKeys(filteredPrivileges);
    const defaultUserPrivileges = privileges.getDefaultUserPrivileges();

    detailedPrivileges.value = role.value.privileges.filter((privilege) => {
        return ![
            ...allGeneralPrivileges,
            ...defaultUserPrivileges,
        ].includes(privilege);
    });
    role.value.privileges = filteredPrivileges;
};
const onSave = () => {
    return saveRole(Contena.Context.api);
};
const saveRole = async (context) => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    role.value.privileges = [
        ...privileges.getPrivilegesForAdminPrivilegeKeys(role.value.privileges),
        ...detailedPrivileges.value,
    ].sort();

    try {
        await roleRepository.value.save(role.value, context);
        await updateCurrentUser();

        if (role.value.isNew()) {
            void router.push({
                name: 'ct.permissions.role.detail',
                params: { id: role.value.id },
            });
        }

        await getRole();
        isSaveSuccessful.value = true;
    } catch {
        createNotificationError({
            message: t(
                'global.notification.notificationSaveErrorMessage',
                {
                    entityName: role.value.name,
                },
                0,
            ),
        });

        role.value.privileges = privileges.filterPrivilegesRoles(role.value.privileges);
    } finally {
        isLoading.value = false;
    }
};
const updateCurrentUser = async () => {
    const { data } = await userService.getUser();

    delete data.password;

    Contena.Store.get('session').setCurrentUser(data);
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onCancel = () => {
    void router.push({ name: 'ct.permissions.index' });
};

watch(
    () => languageId.value,
    () => {
        createdComponent();
    },
);

createdComponent();

ctDefinePublic({
    repositoryFactory,
    privileges,
    userService,
    acl,
    isLoading,
    isSaveSuccessful,
    role,
    detailedPrivileges,
    identifier,
    roleTabItems,
    languageId,
    roleRepository,
    roleId,
    onTabChange,
    createdComponent,
    createNewRole,
    getRole,
    onSave,
    saveRole,
    updateCurrentUser,
    saveFinish,
    onCancel,
});
usePageTitle(() => identifier.value);

defineExpose({
    repositoryFactory,
    privileges,
    userService,
    acl,
    isLoading,
    isSaveSuccessful,
    role,
    detailedPrivileges,
    identifier,
    roleTabItems,
    languageId,
    roleRepository,
    roleId,
    onTabChange,
    createdComponent,
    createNewRole,
    getRole,
    onSave,
    saveRole,
    updateCurrentUser,
    saveFinish,
    onCancel,
});
</script>
