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
                                <mt-icon name="regular-chevron-left" size="16px" />
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
import './ct-login.scss';

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
