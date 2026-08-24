<template>
    <ct-block name="sw_login">
        <div class="ct-login-index">
            <ct-block name="sw_login_container">
                <ct-block name="sw_login_badge">
                    <div class="ct-login-index__brand">
                        <ct-block name="sw_login_badge_image">
                            <img
                                class="ct-login-index__logo-image"
                                :src="assetFilter('/administration/administration/static/img/contena-logo-v4.svg')"
                                alt="Contena"
                            />
                        </ct-block>
                        <ct-block name="sw_login_brand_name">
                            <span class="ct-login-index__brand-name">{{ $t('ct-login.index.brandName') }}</span>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="sw_login_form">
                    <section class="ct-login-index__card" aria-live="polite">
                        <ct-block name="sw_login_back_link">
                            <router-link
                                v-if="$route.meta.backToLogin"
                                class="ct-login-index__back-to-login"
                                :to="{ name: 'ct.login.index.credentials' }"
                                @mousedown.prevent
                            >
                                <ct-icon name="left" />
                                <span>{{ $t('ct-login.index.backToLogin') }}</span>
                            </router-link>
                        </ct-block>

                        <ct-block name="sw_login_view">
                            <router-view v-slot="{ Component }">
                                <component :is="Component" />
                            </router-view>
                        </ct-block>
                    </section>
                </ct-block>
            </ct-block>

            <ct-block name="sw_login_footer">
                <footer class="ct-login-index__footer">© {{ copyrightYear }} Contena</footer>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">

defineProps({});

import { computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});
const backToLogin = async (): Promise<void> => {
    await router.push({ name: 'ct.login.index.credentials' });
};
const copyrightYear = new Date().getFullYear();

swDefinePublic({
    assetFilter,
    backToLogin,
    copyrightYear,
});
</script>

<style lang="scss">
.ct-login-index {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    padding: var(--scale-size-40) var(--scale-size-24);
    overflow: hidden;
    background: linear-gradient(180deg, #a9def3 0%, #dff4fc 58%, #f8fbfd 100%);

    &::before,
    &::after {
        position: absolute;
        content: "";
        pointer-events: none;
        border-radius: 50%;
        filter: blur(0.25rem);
    }

    &::before {
        right: 10%;
        bottom: -14rem;
        width: 34rem;
        height: 20rem;
        background: rgba(255, 255, 255, 70%);
        box-shadow: -20rem 4rem 0 4rem rgba(255, 255, 255, 60%);
    }

    &::after {
        top: -8rem;
        left: 18%;
        width: 26rem;
        height: 16rem;
        background: rgba(255, 255, 255, 20%);
    }

    &__brand {
        position: absolute;
        top: var(--scale-size-32);
        left: var(--scale-size-40);
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: var(--scale-size-8);
    }

    &__logo-image {
        display: block;
        width: var(--scale-size-48);
        height: var(--scale-size-48);
    }

    &__brand-name {
        color: var(--color-text-primary);
        font-size: 1.125rem;
        font-weight: var(--font-weight-bold);
        line-height: 1.4;
    }

    &__card {
        position: relative;
        z-index: 1;
        width: min(100%, 26rem);
        min-height: 30rem;
        padding: var(--scale-size-48);
        background: linear-gradient(160deg, rgba(255, 255, 255, 96%) 0%, rgba(245, 251, 255, 92%) 100%);
        border: 1px solid rgba(255, 255, 255, 80%);
        border-radius: var(--border-radius-card);
        box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, 9%);
    }

    &__back-to-login {
        display: inline-flex;
        align-items: center;
        gap: var(--scale-size-4);
        margin-bottom: var(--scale-size-24);
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
    }

    &__footer {
        position: absolute;
        right: var(--scale-size-24);
        bottom: var(--scale-size-20);
        left: var(--scale-size-24);
        z-index: 1;
        color: var(--color-text-secondary);
        font-size: var(--font-size-2xs);
        text-align: center;
    }
}

html[data-theme="dark"] .ct-login-index {
    --color-text-primary: #f8fafc;
    --color-text-secondary: #cbd5e1;

    color: var(--color-text-primary);
    background: linear-gradient(180deg, #081426 0%, #0b1d35 56%, #111827 100%);

    &::before {
        background: rgba(37, 99, 235, 16%);
        box-shadow: -20rem 4rem 0 4rem rgba(6, 182, 212, 10%);
    }

    &::after {
        background: rgba(34, 211, 238, 9%);
    }

    .ct-login-index__card {
        background: linear-gradient(155deg, rgba(19, 32, 54, 97%) 0%, rgba(15, 27, 46, 94%) 100%);
        border-color: rgba(148, 163, 184, 18%);
        box-shadow: 0 1.5rem 4rem rgba(0, 0, 0, 36%);
    }

    .ct-login-credentials__icon {
        background: rgba(59, 130, 246, 14%);
        border-color: rgba(96, 165, 250, 28%);
    }

    .ct-login-index__back-to-login,
    .ct-login-credentials__forgot-password {
        color: #60a5fa;
    }
}

@media (max-width: 30rem) {
    .ct-login-index {
        align-items: flex-start;
        min-height: 100vh;
        padding: var(--scale-size-24) var(--scale-size-16);

        &__brand {
            top: var(--scale-size-20);
            left: var(--scale-size-20);
        }

        &__logo-image {
            width: var(--scale-size-40);
            height: var(--scale-size-40);
        }

        &__brand-name {
            font-size: 1rem;
        }

        &__card {
            min-height: 0;
            padding: var(--scale-size-32) var(--scale-size-24);
        }

        &__footer {
            right: var(--scale-size-16);
            bottom: var(--scale-size-12);
            left: var(--scale-size-16);
        }
    }
}
</style>
