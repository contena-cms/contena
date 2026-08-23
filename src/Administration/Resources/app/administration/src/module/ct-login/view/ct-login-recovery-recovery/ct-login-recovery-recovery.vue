<template>
    <ct-block name="sw_login_recovery_recovery">
        <form class="ct-login-reset" @submit.prevent="updatePassword">
            <template v-if="hashValid === true">
                <ct-block name="sw_login_recovery_recovery_headline">
                    <div class="ct-login-reset__content">
                        <h1 class="ct-login-reset__title">
                            {{ $t('ct-login.reset.title') }}
                        </h1>

                        <p class="ct-login-reset__description">
                            {{ $t('ct-login.reset.description') }}
                        </p>
                    </div>
                </ct-block>

                <ct-block name="sw_login_recovery_recovery_form">
                    <ct-block name="sw_login_recovery_recovery_form_new_password_field">
                        <mt-password-field
                            v-model="newPassword"
                            v-autofocus
                            class="ct-login-reset__password"
                            :label="$t('ct-login.reset.labelNewPassword')"
                            name="new-password"
                            autocomplete="new-password"
                            :disabled="isSubmitting"
                            required
                        />
                    </ct-block>

                    <ct-block name="sw_login_recovery_recovery_form_password_confirm_field">
                        <mt-password-field
                            v-model="newPasswordConfirm"
                            class="ct-login-reset__repeat-password"
                            :label="$t('ct-login.reset.labelRepeatPassword')"
                            name="new-password"
                            autocomplete="new-password"
                            :disabled="isSubmitting"
                            required
                        />
                    </ct-block>

                    <ct-block name="sw_login_recovery_recovery_form_submit">
                        <mt-button
                            class="ct-login-reset__reset"
                            type="submit"
                            variant="primary"
                            size="default"
                            block
                            :is-loading="isSubmitting"
                            :disabled="!canSubmit"
                        >
                            {{ $t('ct-login.reset.buttonSubmit') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <mt-banner v-else-if="hashValid === false" variant="critical">
                {{ $t('ct-login.reset.errorLinkExpired') }}
            </mt-banner>

            <mt-loader v-else />
        </form>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-login-recovery-recovery.scss';

const props = defineProps({
    hash: {
        type: String,
        required: true,
    },
});

import { ref, computed, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { t } = useI18n();

const userRecoveryService = inject('userRecoveryService');

const newPassword = ref('');
const newPasswordConfirm = ref('');
const hashValid = ref(null);
const isSubmitting = ref(false);

const canSubmit = computed(() => {
    return (
        hashValid.value === true &&
        newPassword.value.length > 0 &&
        newPassword.value === newPasswordConfirm.value &&
        !isSubmitting.value
    );
});

const validateHash = async () => {
    try {
        await userRecoveryService.checkHash(props.hash);
        hashValid.value = true;
    } catch {
        hashValid.value = false;
    }
};
const updatePassword = async () => {
    if (!canSubmit.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        await userRecoveryService.updateUserPassword(props.hash, newPassword.value, newPasswordConfirm.value);
        await router.push({ name: 'ct.login.index.credentials' });
    } catch {
        Contena.Store.get('notification').createNotification({
            variant: 'error',
            title: 'global.default.error',
            message: t('ct-login.reset.errorUnexpected'),
        });
    } finally {
        isSubmitting.value = false;
    }
};

void validateHash();

swDefinePublic({
    userRecoveryService,
    newPassword,
    newPasswordConfirm,
    hashValid,
    isSubmitting,
    canSubmit,
    validateHash,
    updatePassword,
});

defineExpose({
    newPassword,
    newPasswordConfirm,
    hashValid,
    isSubmitting,
    canSubmit,
    updatePassword,
});
</script>
