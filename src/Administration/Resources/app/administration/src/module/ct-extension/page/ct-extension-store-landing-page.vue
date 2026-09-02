<template>
    <ct-block name="ct_extension_store_landing_page">
        <ct-page>
            <template #smart-bar-header>
                <ct-block name="ct_extension_store_landing_page_smart_bar_header">
                    <h2>{{ $t('ct-extension.mainMenu.store') }}</h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_extension_store_landing_page_content">
                    <div class="ct-extension-store-landing-page">
                        <ct-block name="ct_extension_store_landing_page_wrapper">
                            <div class="ct-extension-store-landing-page__wrapper">
                                <ct-block name="ct_extension_store_landing_page_loading">
                                    <template v-if="isLoading">
                                        <div class="ct-extension-store-landing-page__loading">
                                            <mt-loader />
                                            <p>
                                                {{ $t('ct-extension.store.activating') }}<br />
                                                {{ $t('ct-extension.store.takeMinutes') }}
                                            </p>
                                        </div>
                                    </template>
                                </ct-block>

                                <ct-block name="ct_extension_store_landing_page_status">
                                    <template v-if="!isLoading && activationStatus !== null">
                                        <div class="ct-extension-store-landing-page__status">
                                            <template v-if="activationStatus === 'error'">
                                                <ct-block name="ct_extension_store_landing_page_status_error">
                                                    <div
                                                        class="ct-extension-store-landing-page__status-icon ct-extension-store-landing-page__status-icon--error"
                                                    >
                                                        <mt-icon name="regular-times-circle" size="30px" />
                                                    </div>

                                                    <h2>
                                                        {{
                                                            activationError?.title ||
                                                            $t('ct-extension.store.activationErrorTitle')
                                                        }}
                                                    </h2>
                                                    <p>
                                                        {{
                                                            activationError?.detail ||
                                                            $t('ct-extension.store.activationErrorDescription')
                                                        }}
                                                    </p>

                                                    <mt-button variant="primary" @click="installStore">
                                                        {{ $t('ct-extension.store.retry') }}
                                                    </mt-button>
                                                </ct-block>
                                            </template>

                                            <template v-else>
                                                <ct-block name="ct_extension_store_landing_page_status_success">
                                                    <div
                                                        class="ct-extension-store-landing-page__status-icon ct-extension-store-landing-page__status-icon--success"
                                                    >
                                                        <mt-icon name="regular-check-circle" size="30px" />
                                                    </div>

                                                    <h2>{{ $t('ct-extension.store.activationSuccessTitle') }}</h2>
                                                    <p>{{ $t('ct-extension.store.activationSuccessDescription') }}</p>
                                                </ct-block>
                                            </template>
                                        </div>
                                    </template>
                                </ct-block>

                                <ct-block name="ct_extension_store_landing_page_activation">
                                    <template v-if="!isLoading && activationStatus === null">
                                        <div class="ct-extension-store-landing-page__content">
                                            <ct-block name="ct_extension_store_landing_page_illustration">
                                                <div
                                                    class="ct-extension-store-landing-page__illustration"
                                                    aria-hidden="true"
                                                >
                                                    <div class="ct-extension-store-landing-page__illustration-window">
                                                        <mt-icon name="regular-storefront" size="52px" />
                                                    </div>
                                                    <span class="ct-extension-store-landing-page__illustration-dot"></span>
                                                    <span class="ct-extension-store-landing-page__illustration-card"></span>
                                                </div>
                                            </ct-block>

                                            <ct-block name="ct_extension_store_landing_page_label">
                                                <span class="ct-extension-store-landing-page__label">
                                                    {{ $t('ct-extension.store.landingLabel') }}
                                                </span>
                                            </ct-block>

                                            <ct-block name="ct_extension_store_landing_page_title">
                                                <h2>
                                                    <span>{{ $t('ct-extension.store.landingTitle') }}</span>
                                                </h2>
                                            </ct-block>

                                            <ct-block name="ct_extension_store_landing_page_description">
                                                <p>{{ $t('ct-extension.store.landingDescription') }}</p>
                                            </ct-block>

                                            <ct-block name="ct_extension_store_landing_page_action">
                                                <mt-button variant="primary" @click="installStore">
                                                    {{ $t('ct-extension.store.install') }}
                                                </mt-button>
                                            </ct-block>
                                        </div>
                                    </template>
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { inject, ref } from 'vue';
import type ContenaExtensionService from '../service/contena-extension.service';

import './ct-extension-store-landing-page.scss';

defineProps({});

type ActivationStatus = 'error' | 'success';

interface ActivationError {
    title: string;
    detail: string;
}

const extensionService = inject<ContenaExtensionService | null>('contenaExtensionService', null);
const isLoading = ref(false);
const activationStatus = ref<ActivationStatus | null>(null);
const activationError = ref<ActivationError | null>(null);

const reloadPage = (): void => {
    window.location.reload();
};

const extractActivationError = (error: unknown): ActivationError | null => {
    if (!error || typeof error !== 'object' || !('response' in error)) {
        return null;
    }

    const response = error.response;

    if (!response || typeof response !== 'object' || !('data' in response)) {
        return null;
    }

    const data = response.data;

    if (!data || typeof data !== 'object' || !('errors' in data) || !Array.isArray(data.errors) || !data.errors[0]) {
        return null;
    }

    const firstError = data.errors[0];

    if (typeof firstError !== 'object' || firstError === null) {
        return null;
    }

    const title = 'title' in firstError && typeof firstError.title === 'string' ? firstError.title : null;
    const detail = 'detail' in firstError && typeof firstError.detail === 'string' ? firstError.detail : null;

    if (!title && !detail) {
        return null;
    }

    return {
        title: title ?? '',
        detail: detail ?? '',
    };
};

const installStore = async (): Promise<void> => {
    activationStatus.value = null;
    activationError.value = null;

    if (!extensionService) {
        activationStatus.value = 'error';

        return;
    }

    isLoading.value = true;

    try {
        await extensionService.installAndActivateExtension('CtExtensionStore');
        activationStatus.value = 'success';
        reloadPage();
    } catch (error) {
        activationStatus.value = 'error';
        activationError.value = extractActivationError(error);
    } finally {
        isLoading.value = false;
    }
};

ctDefinePublic({
    extensionService,
    isLoading,
    activationStatus,
    activationError,
    extractActivationError,
    reloadPage,
    installStore,
});

defineExpose({
    extensionService,
    isLoading,
    activationStatus,
    activationError,
    extractActivationError,
    reloadPage,
    installStore,
});

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});
</script>
