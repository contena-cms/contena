<template>
    <ct-block name="sw_login_recovery_info">
        <div class="ct-login-request-sent">
            <div class="ct-login-request-sent__icon">
                <mt-icon
                    class="ct-login-request-sent__icon-symbol"
                    name="check-circle"
                    mode="solid"
                    size="var(--scale-size-24)"
                />
            </div>

            <div class="ct-login-request-sent__content">
                <ct-block name="sw_login_recovery_info_headline">
                    <h1 class="ct-login-request-sent__title">
                        {{ $t('ct-login.requestSent.title') }}
                    </h1>
                </ct-block>

                <ct-block name="sw_login_recovery_info_info">
                    <i18n-t
                        v-if="email"
                        keypath="ct-login.requestSent.description"
                        tag="p"
                        scope="local"
                        class="ct-login-request-sent__description"
                    >
                        <template #email>
                            <span class="ct-login-request-sent__description-email">{{ email }}</span>
                        </template>
                    </i18n-t>

                    <p v-else class="ct-login-request-sent__description">
                        {{ $t('ct-login.requestSent.descriptionGeneric') }}
                    </p>

                    <p v-if="waitTime" class="ct-login-request-sent__description">
                        {{ $t('ct-login.requestSent.rateLimited', { seconds: waitTime }) }}
                    </p>
                </ct-block>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-login-recovery-info.scss';

defineProps({});

import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const email = computed(() => {
    const email = route.query.email;

    return typeof email === 'string' ? email : '';
});
const waitTime = computed(() => {
    const waitTime = route.query.waitTime;
    const seconds = typeof waitTime === 'string' ? Number.parseInt(waitTime, 10) : Number.NaN;

    return Number.isNaN(seconds) || seconds < 1 ? null : seconds;
});

swDefinePublic({
    email,
    waitTime,
});

defineExpose({
    email,
    waitTime,
});
</script>
