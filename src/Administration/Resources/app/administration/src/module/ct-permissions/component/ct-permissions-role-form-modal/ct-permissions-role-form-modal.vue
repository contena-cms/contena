<template>
    <ct-block name="sw_permissions_role_form_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal class="ct-permissions-role-form-modal" width="m" :title="modalTitle">
                <ct-block name="sw_permissions_role_form_modal_content">
                    <div v-if="role" class="ct-permissions-role-form-modal__form">
                        <mt-text-field
                            v-model="role.name"
                            required
                            :label="t('ct-permissions.roles.detail.labelName')"
                            :disabled="isLoading || undefined"
                        />
                        <mt-text-field
                            v-model="role.code"
                            required
                            :label="t('ct-permissions.roles.detail.labelCode')"
                            :disabled="isLoading || undefined"
                        />
                        <mt-textarea
                            v-model="role.description"
                            :label="t('ct-permissions.roles.detail.labelDescription')"
                            :disabled="isLoading || undefined"
                        />
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_permissions_role_form_modal_footer">
                        <div class="ct-permissions-role-form-modal__footer-actions">
                            <mt-button variant="secondary" size="small" @click="closeModal">
                                {{ t('global.default.cancel') }}
                            </mt-button>
                            <mt-button
                                variant="primary"
                                size="small"
                                :is-loading="isLoading"
                                :disabled="!canSave || undefined"
                                @click="requestSave"
                            >
                                {{ t('global.default.save') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import './ct-permissions-role-form-modal.scss';

type RoleEntity = Entity<'acl_role'> & {
    code: string;
    name: string;
    description: string | null;
    privileges: string[];
    isNew(): boolean;
};

const props = withDefaults(defineProps<{ roleId?: string | null }>(), { roleId: null });
const emit = defineEmits<{ close: []; saved: [] }>();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');

if (!repositoryFactory || !acl) {
    throw new Error('The role form modal requires the repository factory and ACL service.');
}

const roleRepository = repositoryFactory.create('acl_role');
const role = ref<RoleEntity | null>(null);
const isLoading = ref(false);
const modalTitle = computed(() =>
    t(props.roleId ? 'ct-permissions.roles.modal.editTitle' : 'ct-permissions.roles.modal.createTitle'),
);
const canSave = computed(() => {
    const requiredPrivilege = props.roleId ? 'users_and_permissions.editor' : 'users_and_permissions.creator';

    return (
        Boolean(role.value?.name.trim()) &&
        Boolean(role.value?.code.trim()) &&
        !isLoading.value &&
        acl.can(requiredPrivilege)
    );
});
const loadRole = async () => {
    isLoading.value = true;

    try {
        role.value = props.roleId
            ? ((await roleRepository.get(props.roleId)) as RoleEntity)
            : (roleRepository.create() as RoleEntity);

        role.value.name ??= '';
        role.value.code ??= '';
        role.value.description ??= '';
        role.value.privileges ??= [];
    } finally {
        isLoading.value = false;
    }
};
const requestSave = async () => {
    if (!canSave.value) {
        return;
    }

    await saveRole(Contena.Context.api);
};
const saveRole = async (context: unknown) => {
    if (!role.value || !canSave.value) {
        return;
    }

    isLoading.value = true;

    try {
        await roleRepository.save(role.value, context);
        emit('saved');
        emit('close');
    } catch {
        createNotificationError({
            message: t('ct-permissions.roles.modal.saveError', { name: role.value.name }, 0),
        });
    } finally {
        isLoading.value = false;
    }
};
const closeModal = () => {
    if (!isLoading.value) {
        emit('close');
    }
};
const onModalChange = (isOpen: boolean) => {
    if (!isOpen) {
        closeModal();
    }
};

void loadRole();

swDefinePublic({
    role,
    isLoading,
    modalTitle,
    canSave,
    loadRole,
    requestSave,
    saveRole,
    closeModal,
    onModalChange,
});

defineExpose({
    role,
    isLoading,
    modalTitle,
    canSave,
    loadRole,
    requestSave,
    saveRole,
    closeModal,
    onModalChange,
});
</script>
