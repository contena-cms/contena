<template>
    <ct-block name="sw_login_login">
        <form class="ct-login-credentials" @submit.prevent="onSubmit">
            <ct-block name="sw_login_login_form_headline">
                <ct-block name="sw_login_login_form_icon">
                    <div class="ct-login-credentials__icon" aria-hidden="true">
                        <img
                            class="ct-login-credentials__icon-logo"
                            :src="assetFilter('/administration/administration/static/img/contena-logo-v4.svg')"
                            alt=""
                        />
                    </div>
                </ct-block>

                <h1 class="ct-login-credentials__title">
                    {{ $t('ct-login.credentials.buttonLogin') }}
                </h1>
                <p class="ct-login-credentials__description">
                    {{ $t('ct-login.credentials.description') }}
                </p>
            </ct-block>

            <a-alert
                v-if="isRateLimited"
                class="ct-login-credentials__warning"
                type="warning"
                show-icon
                :message="$t('ct-login.credentials.warningTooManyAttemptsCountdown', { time: countdownLabel })"
            />

            <ct-block name="sw_login_login_user_field">
                <a-form-item class="ct-login-credentials__field" :label="$t('ct-login.credentials.labelUsername')" required>
                    <a-input
                        v-model:value="username"
                        v-autofocus
                        name="username"
                        autocomplete="username"
                        size="large"
                        :disabled="isDisabled"
                    />
                </a-form-item>
            </ct-block>

            <ct-block name="sw_login_login_password_field">
                <a-form-item class="ct-login-credentials__field" :label="$t('ct-login.credentials.labelPassword')" required>
                    <a-input-password
                        v-model:value="password"
                        name="password"
                        autocomplete="current-password"
                        size="large"
                        :disabled="isDisabled"
                    />
                </a-form-item>
            </ct-block>

            <ct-block name="sw_login_login_alert">
                <a-alert
                    v-if="error"
                    class="ct-login-credentials__error"
                    type="error"
                    show-icon
                    :message="$t('ct-login.credentials.errorInvalidCredentials')"
                />
            </ct-block>

            <ct-block name="sw_login_login_submit">
                <ct-block name="sw_login_login_support">
                    <div class="ct-login-credentials__support">
                        <a-checkbox v-model:checked="rememberMe" :disabled="isDisabled">
                            {{ $t('ct-login.credentials.labelKeepLoggedIn') }}
                        </a-checkbox>

                        <ct-block name="sw_login_login_forgot_password">
                            <router-link
                                class="ct-login-credentials__forgot-password"
                                :to="{ name: 'ct.login.index.recovery' }"
                            >
                                {{ $t('ct-login.credentials.forgotPassword') }}
                            </router-link>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="sw_login_login_submit_button">
                    <a-button
                        class="ct-login-credentials__login-button"
                        html-type="submit"
                        type="primary"
                        size="large"
                        block
                        :loading="isLoggingIn"
                        :disabled="isDisabled"
                    >
                        {{ $t('ct-login.credentials.buttonLogin') }}
                    </a-button>
                </ct-block>
            </ct-block>
        </form>
    </ct-block>
</template>

<script setup lang="ts">
import { parseApiRejection } from '../../service/login-error';
import { HTTP_STATUS, ROUTES, STORAGE_KEYS, TIMING } from '../../service/login.constants';

defineProps({});

import { ref, computed, inject, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { LoginService } from '../../../../core/service/login.service';

const router = useRouter();
const { t } = useI18n();
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});
// ESLint does not detect countdownLabel inside the translated template parameter object.

const loginService = inject('loginService') as LoginService;

const username = ref('');
const password = ref('');
const rememberMe = ref(false);
const isLoggingIn = ref(false);
const error = ref(false);
const retryAfterSeconds = ref(0);
const retryTimer = ref(null);

const isRateLimited = computed(() => {
    return retryAfterSeconds.value > 0;
});
const countdownLabel = computed(() => {
    const minutes = Math.floor(retryAfterSeconds.value / TIMING.SECONDS_PER_MINUTE);
    const seconds = retryAfterSeconds.value % TIMING.SECONDS_PER_MINUTE;

    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});
const canSubmit = computed(() => {
    return username.value.length > 2 && password.value.length > 2;
});
const isDisabled = computed(() => {
    return isLoggingIn.value || isRateLimited.value;
});

const createdComponent = async () => {
    await ensureAdminLocale();
};
const ensureAdminLocale = async () => {
    if (localStorage.getItem(STORAGE_KEYS.ADMIN_LOCALE)) {
        return;
    }

    const localeFactory = Contena.Application.getContainer('factory').locale;
    await Contena.Store.get('session').setAdminLocale(localeFactory.getLastKnownLocale());
};
const onSubmit = async (event: SubmitEvent) => {
    const formData = new FormData(event.currentTarget as HTMLFormElement);
    const submittedUsername = formData.get('username');
    const submittedPassword = formData.get('password');

    if (typeof submittedUsername === 'string') {
        username.value = submittedUsername;
    }

    if (typeof submittedPassword === 'string') {
        password.value = submittedPassword;
    }

    if (!canSubmit.value || isLoggingIn.value || isRateLimited.value) {
        return;
    }

    error.value = false;
    isLoggingIn.value = true;
    loginService.setRememberMe(rememberMe.value);

    try {
        await loginService.loginByUsername(username.value, password.value);
        await handleLoginSuccess();
    } catch (error) {
        handleLoginError(error);
    } finally {
        isLoggingIn.value = false;
    }
};
const handleLoginError = (loginError: unknown) => {
    const { status, retryAfterSeconds } = parseApiRejection(loginError);

    if (status === HTTP_STATUS.TOO_MANY_REQUESTS) {
        startRetryCountdown(retryAfterSeconds ?? 0);

        return;
    }

    if (status !== undefined) {
        error.value = true;
        password.value = '';

        return;
    }

    Contena.Store.get('notification').createNotification({
        variant: 'error',
        title: 'global.default.error',
        message: t('ct-login.credentials.errorUnexpected'),
    });
};
const handleLoginSuccess = async () => {
    password.value = '';

    if (reloadIfRequested()) {
        return;
    }

    await forwardLogin();
};
const forwardLogin = async () => {
    const previousRoute = readPreviousRoute();

    if (previousRoute?.fullPath) {
        await router.push(previousRoute.fullPath);

        return;
    }

    await router.push({ name: ROUTES.CORE });
};
const readPreviousRoute = () => {
    const raw = sessionStorage.getItem(STORAGE_KEYS.PREVIOUS_ROUTE);
    sessionStorage.removeItem(STORAGE_KEYS.PREVIOUS_ROUTE);

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw) as { fullPath?: string };
    } catch {
        return null;
    }
};
const reloadIfRequested = () => {
    if (!sessionStorage.getItem(STORAGE_KEYS.SHOULD_RELOAD)) {
        return false;
    }

    sessionStorage.removeItem(STORAGE_KEYS.SHOULD_RELOAD);
    window.location.reload();

    return true;
};
const startRetryCountdown = (seconds: number) => {
    stopRetryCountdown();
    retryAfterSeconds.value = seconds;

    if (seconds <= 0) {
        return;
    }

    retryTimer.value = window.setInterval(() => {
        retryAfterSeconds.value -= 1;

        if (retryAfterSeconds.value <= 0) {
            stopRetryCountdown();
        }
    }, TIMING.COUNTDOWN_INTERVAL_MS);
};
const stopRetryCountdown = () => {
    if (retryTimer.value === null) {
        return;
    }

    window.clearInterval(retryTimer.value);
    retryTimer.value = null;
};

void createdComponent();

onBeforeUnmount(() => {
    stopRetryCountdown();
});

swDefinePublic({
    loginService,
    assetFilter,
    username,
    password,
    rememberMe,
    isLoggingIn,
    error,
    retryAfterSeconds,
    retryTimer,
    isRateLimited,
    countdownLabel,
    canSubmit,
    isDisabled,
    createdComponent,
    ensureAdminLocale,
    onSubmit,
    handleLoginError,
    handleLoginSuccess,
    forwardLogin,
    readPreviousRoute,
    reloadIfRequested,
    startRetryCountdown,
    stopRetryCountdown,
});

defineExpose({
    username,
    password,
    rememberMe,
    isLoggingIn,
    error,
    isRateLimited,
    countdownLabel,
    isDisabled,
    onSubmit,
});
</script>

<style lang="scss">
.ct-login-credentials {
    display: flex;
    flex-direction: column;
    height: 100%;

    &__icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: var(--scale-size-40);
        height: var(--scale-size-40);
        margin: 0 auto var(--scale-size-16);
        background: rgba(37, 99, 235, 8%);
        border: 1px solid rgba(37, 99, 235, 14%);
        border-radius: var(--border-radius-m);
    }

    &__icon-logo {
        display: block;
        width: var(--scale-size-32);
        height: var(--scale-size-32);
    }

    &__title {
        margin: 0;
        font-size: var(--font-size-2xl);
        font-weight: var(--font-weight-semibold);
        line-height: var(--font-line-height-2xl);
        text-align: center;
    }

    &__description {
        margin: var(--scale-size-8) 0 var(--scale-size-32);
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
        text-align: center;
    }

    &__warning,
    &__error {
        margin: 0 0 var(--scale-size-24);
    }

    &__field.ant-form-item {
        margin-bottom: var(--scale-size-16);

        .ant-form-item-row {
            display: block;
        }

        .ant-form-item-label {
            display: block;
            padding: 0 0 var(--scale-size-8);
            text-align: left;
        }

        .ant-form-item-label > label {
            display: inline-flex;
            height: auto;
        }

        .ant-form-item-control {
            width: 100%;
        }
    }

    &__support {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--scale-size-16);
        margin: var(--scale-size-8) 0 var(--scale-size-24);
    }

    &__forgot-password {
        flex-shrink: 0;
        font-size: var(--font-size-sm);
    }
}
</style>
