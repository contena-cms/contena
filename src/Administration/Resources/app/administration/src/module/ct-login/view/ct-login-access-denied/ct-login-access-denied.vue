<template>
    <div class="ct-login-access-denied">
        <div class="ct-login-access-denied__icon">
            <mt-icon name="lock" mode="solid" size="var(--scale-size-24)" />
        </div>

        <div class="ct-login-access-denied__content">
            <h2 class="ct-login-access-denied__title">
                {{ $t('ct-login.accessDenied.title') }}
            </h2>

            <p class="ct-login-access-denied__description">
                {{ $t('ct-login.accessDenied.description') }}
            </p>
        </div>

        <mt-button
            class="ct-login-access-denied__retry-button"
            variant="primary"
            size="small"
            @click="loginWithDifferentAccount"
        >
            {{ $t('ct-login.accessDenied.buttonDifferentAccount') }}
        </mt-button>

        <p v-if="email" class="ct-login-access-denied__used-account">
            <span class="ct-login-access-denied__used-account-title">
                {{ $t('ct-login.accessDenied.loggedInAs') }}
            </span>

            <span class="ct-login-access-denied__used-account-email">
                {{ email }}
            </span>
        </p>
    </div>
</template>

<script setup lang="ts">
import './ct-login-access-denied.scss';

defineProps({});

import { computed, inject } from 'vue';

const loginService = inject('loginService');

const email = computed(() => {
    return Contena.Store.get('session').currentUser?.email ?? '';
});

const loginWithDifferentAccount = () => {
    loginService.logout();
};

ctDefinePublic({
    loginService,
    email,
    loginWithDifferentAccount,
});

defineExpose({
    email,
    loginWithDifferentAccount,
});
</script>
