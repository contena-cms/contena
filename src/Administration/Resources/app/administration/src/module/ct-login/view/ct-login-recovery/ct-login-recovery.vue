<template>
    <ct-block name="ct_login_recovery">
        <form class="ct-login-recovery" novalidate @submit.prevent="sendRecoveryMail">
            <div class="ct-login-recovery__content">
                <ct-block name="ct_login_recovery_headline">
                    <h1 class="ct-login-recovery__title">
                        {{ $t('ct-login.recovery.title') }}
                    </h1>
                </ct-block>

                <ct-block name="ct_login_recovery_text">
                    <p class="ct-login-recovery__description">
                        {{ $t('ct-login.recovery.description') }}
                    </p>
                </ct-block>
            </div>

            <mt-banner v-if="error" class="ct-login-recovery__error" variant="critical">
                {{ $t('ct-login.recovery.errorUnexpected') }}
            </mt-banner>

            <mt-banner v-if="warning" class="ct-login-recovery__warning" variant="attention">
                {{ $t('ct-login.recovery.warningTooManyAttempts') }}
            </mt-banner>

            <ct-block name="ct_login_recovery_form">
                <ct-block name="ct_login_recovery_form_email_field">
                    <mt-email-field
                        v-model="email"
                        v-autofocus
                        class="ct-login-recovery__email"
                        :label="$t('ct-login.recovery.labelEmail')"
                        name="email"
                        autocomplete="email"
                        :disabled="isSubmitting"
                        required
                    />
                </ct-block>

                <ct-block name="ct_login_recovery_form_submit">
                    <mt-button
                        class="ct-login-recovery__submit"
                        type="submit"
                        variant="primary"
                        size="large"
                        block
                        :is-loading="isSubmitting"
                        :disabled="!canSubmit"
                    >
                        {{ $t('ct-login.recovery.buttonSubmit') }}
                    </mt-button>
                </ct-block>
            </ct-block>
        </form>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-login-recovery.scss';
import { parseApiRejection } from '../../service/login-error';
import { HTTP_STATUS } from '../../service/login.constants';

defineProps({});

import { ref, computed, inject } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const userRecoveryService = inject('userRecoveryService');

const email = ref('');
const error = ref(false);
const warning = ref(false);
const isSubmitting = ref(false);

const canSubmit = computed(() => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value) && !isSubmitting.value;
});

const sendRecoveryMail = async () => {
    if (!canSubmit.value) {
        return;
    }

    error.value = false;
    warning.value = false;
    isSubmitting.value = true;

    try {
        await userRecoveryService.createRecovery(email.value);
        await forwardToRequestSent();
    } catch (recoveryError) {
        const { status, retryAfterSeconds } = parseApiRejection(recoveryError);

        if (status === HTTP_STATUS.TOO_MANY_REQUESTS) {
            warning.value = true;
            await forwardToRequestSent(retryAfterSeconds);

            return;
        }

        error.value = true;
    } finally {
        isSubmitting.value = false;
    }
};
const forwardToRequestSent = async (waitTime?: number) => {
    await router.push({
        name: 'ct.login.index.requestSent',
        query: {
            email: email.value,
            ...(waitTime ? { waitTime: String(waitTime) } : {}),
        },
    });
};

ctDefinePublic({
    userRecoveryService,
    email,
    error,
    warning,
    isSubmitting,
    canSubmit,
    sendRecoveryMail,
    forwardToRequestSent,
});

defineExpose({
    email,
    error,
    warning,
    isSubmitting,
    canSubmit,
    sendRecoveryMail,
});
</script>
