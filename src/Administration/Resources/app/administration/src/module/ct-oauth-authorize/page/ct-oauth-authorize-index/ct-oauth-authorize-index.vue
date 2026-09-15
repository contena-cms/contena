<template>
    <main class="ct-oauth-authorize">
        <div class="ct-oauth-authorize__container">
            <img
                class="ct-oauth-authorize__logo"
                :src="assetFilter('/administration/administration/static/img/contena-logo-v4.svg')"
                :alt="t('ct-oauth-authorize.logoAltText')"
            />

            <div class="ct-oauth-authorize__card">
                <div
                    v-if="isLoading"
                    class="ct-oauth-authorize__loading-indicator"
                    role="status"
                    :aria-label="t('ct-oauth-authorize.loadingLabel')"
                >
                    <mt-loader size="24px" />
                </div>
                <div v-else class="ct-oauth-authorize__status">
                    <span class="ct-oauth-authorize__status-icon">
                        <mt-icon name="solid-lock" size="24px" decorative />
                    </span>

                    <mt-banner
                        v-if="errorMessage"
                        class="ct-oauth-authorize__error"
                        variant="critical"
                        :show-icon="true"
                        :closable="false"
                    >
                        {{ errorMessage }}
                    </mt-banner>

                    <template v-else-if="info">
                        <i18n-t keypath="ct-oauth-authorize.consent.title" tag="h1" class="ct-oauth-authorize__title">
                            <template #clientName
                                ><strong class="ct-oauth-authorize__client-name">{{ clientName }}</strong></template
                            >
                            <template #workspaceName
                                ><strong class="ct-oauth-authorize__workspace-name">{{ workspaceName }}</strong></template
                            >
                        </i18n-t>
                        <i18n-t
                            keypath="ct-oauth-authorize.consent.loggedInAs"
                            tag="p"
                            class="ct-oauth-authorize__logged-in-as"
                        >
                            <template #username
                                ><strong class="ct-oauth-authorize__username">{{ username }}</strong></template
                            >
                        </i18n-t>
                        <section
                            class="ct-oauth-authorize__permissions"
                            aria-labelledby="ct-oauth-authorize-permissions-title"
                        >
                            <h2 id="ct-oauth-authorize-permissions-title" class="ct-oauth-authorize__permissions-title">
                                {{ t('ct-oauth-authorize.consent.permissionTitle') }}
                            </h2>
                            <p class="ct-oauth-authorize__description">
                                {{ t('ct-oauth-authorize.consent.permissionHint') }}
                            </p>
                        </section>
                        <p class="ct-oauth-authorize__trust-hint">{{ t('ct-oauth-authorize.consent.trustHint') }}</p>
                        <div class="ct-oauth-authorize__button-container">
                            <mt-button
                                class="ct-oauth-authorize__deny"
                                variant="secondary"
                                size="default"
                                :disabled="isSubmitting || undefined"
                                @click="onDeny"
                            >
                                {{ t('ct-oauth-authorize.consent.deny') }}
                            </mt-button>
                            <mt-button
                                class="ct-oauth-authorize__approve"
                                variant="primary"
                                size="default"
                                :disabled="isSubmitting || undefined"
                                :is-loading="isSubmitting"
                                @click="onApprove"
                            >
                                {{ t('ct-oauth-authorize.consent.approve') }}
                            </mt-button>
                        </div>
                        <i18n-t
                            keypath="ct-oauth-authorize.consent.redirectHint"
                            tag="p"
                            class="ct-oauth-authorize__redirect-hint"
                        >
                            <template #host
                                ><strong class="ct-oauth-authorize__redirect-host">{{ redirectHost }}</strong></template
                            >
                        </i18n-t>
                    </template>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import OAuthAuthorizeApiService, {
    type OAuthAuthorizationInfo,
    type OAuthAuthorizationParams,
} from 'src/core/service/api/oauth-authorize.api.service';

const REQUIRED_PARAMS = [
    'response_type',
    'client_id',
    'redirect_uri',
    'code_challenge',
    'code_challenge_method',
] as const;
const OPTIONAL_PARAMS = [
    'state',
    'scope',
] as const;
const { t } = useI18n();
const route = useRoute();
const oauthAuthorizeApiService = Contena.Service('oauthAuthorizeApiService') as OAuthAuthorizeApiService;
const systemConfigApiService = Contena.Service('systemConfigApiService');
const assetFilter = Contena.Filter.getByName('asset');
const isLoading = ref(true);
const isSubmitting = ref(false);
const info = ref<OAuthAuthorizationInfo | null>(null);
const errorMessage = ref<string | null>(null);
const workspaceName = ref('Contena');

const authorizationParams = computed<OAuthAuthorizationParams>(() => {
    const readString = (key: string): string | undefined => {
        const value = route.query[key];
        return typeof value === 'string' ? value : undefined;
    };
    const params: OAuthAuthorizationParams = {
        response_type: readString('response_type') ?? '',
        client_id: readString('client_id') ?? '',
        redirect_uri: readString('redirect_uri') ?? '',
        code_challenge: readString('code_challenge') ?? '',
        code_challenge_method: readString('code_challenge_method') ?? '',
    };
    OPTIONAL_PARAMS.forEach((key) => {
        const value = readString(key);
        if (value !== undefined) params[key] = value;
    });
    return params;
});
const hasRequiredParams = computed(() => REQUIRED_PARAMS.every((key) => authorizationParams.value[key].length > 0));
const username = computed(() => Contena.Store.get('session').currentUser?.username ?? '');
const clientName = computed(() => info.value?.client.name ?? '');
const redirectHost = computed(() => {
    if (!info.value?.redirectUri) return '';
    try {
        return new URL(info.value.redirectUri).host;
    } catch {
        return info.value.redirectUri;
    }
});

async function loadInfo(): Promise<void> {
    try {
        info.value = await oauthAuthorizeApiService.getInfo(authorizationParams.value);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
    }
}

async function loadWorkspaceName(): Promise<void> {
    try {
        const values = (await systemConfigApiService.getValues('core.basicInformation')) as Record<string, unknown>;
        const value = values['core.basicInformation.siteName'];
        workspaceName.value = typeof value === 'string' && value.length > 0 ? value : 'Contena';
    } catch {
        workspaceName.value = 'Contena';
    }
}

async function createdComponent(): Promise<void> {
    if (!hasRequiredParams.value) {
        errorMessage.value = t('ct-oauth-authorize.error.missingParameters');
        isLoading.value = false;
        return;
    }
    await Promise.all([
        loadInfo(),
        loadWorkspaceName(),
    ]);
    isLoading.value = false;
}

function getErrorMessage(error: unknown): string {
    const firstError = (error as { response?: { data?: { errors?: Array<{ detail?: string; title?: string }> } } })?.response
        ?.data?.errors?.[0];
    return firstError?.detail ?? firstError?.title ?? t('ct-oauth-authorize.error.generic');
}

function navigateTo(url: string): void {
    window.location.assign(url);
}

async function submitDecision(approved: boolean): Promise<void> {
    isSubmitting.value = true;
    try {
        const response = await oauthAuthorizeApiService.decide(authorizationParams.value, approved);
        navigateTo(response.redirectUri);
    } catch (error) {
        errorMessage.value = getErrorMessage(error);
        isSubmitting.value = false;
    }
}

function onApprove(): Promise<void> {
    return submitDecision(true);
}

function onDeny(): Promise<void> {
    return submitDecision(false);
}

onMounted(() => void createdComponent());
ctDefinePublic({});
</script>

<style lang="scss">
.ct-oauth-authorize {
    height: 100%;
    display: flex;
    padding: var(--scale-size-20);
    background-color: var(--color-elevation-surface-sunken);
    overflow: auto;

    &__container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--scale-size-32);
        width: 36rem;
        max-width: 100%;
        margin: auto;
    }

    &__logo {
        height: var(--scale-size-32);
    }

    &__card {
        width: 100%;
        padding: var(--scale-size-40);
        background: var(--color-elevation-surface-raised);
        border: 1px solid var(--color-border-secondary-default);
        border-radius: var(--border-radius-card);
    }

    &__loading-indicator {
        position: relative;
        width: var(--scale-size-40);
        height: var(--scale-size-40);
        margin: 0 auto;
    }

    &__status {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    &__status-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: var(--scale-size-48);
        height: var(--scale-size-48);
        border-radius: var(--border-radius-l);
        background: var(--color-background-brand-default);
        color: var(--color-icon-brand-default);
    }

    &__error {
        width: 100%;
        margin-top: var(--scale-size-24);
        text-align: left;
    }

    &__title {
        color: var(--color-text-primary-default);
        font-size: var(--font-size-xl);
        line-height: var(--font-line-height-xl);
        font-weight: var(--font-weight-regular);
        margin: var(--scale-size-24) 0 var(--scale-size-8);
        overflow-wrap: anywhere;

        strong {
            font-weight: var(--font-weight-bold);
        }
    }

    &__logged-in-as {
        font-size: var(--font-size-xs);
        line-height: var(--font-line-height-xs);
        color: var(--color-text-secondary-default);
        margin-bottom: var(--scale-size-24);
        overflow-wrap: anywhere;

        strong {
            font-weight: var(--font-weight-medium);
            color: var(--color-text-primary-default);
        }
    }

    &__permissions {
        width: 100%;
        padding: var(--scale-size-20);
        border: 1px solid var(--color-border-attention-default);
        border-radius: var(--border-radius-m);
        background: var(--color-background-attention-default);
        color: var(--color-text-attention-default);
        text-align: left;
    }

    &__permissions-title {
        font-size: var(--font-size-s);
        line-height: var(--font-line-height-s);
        font-weight: var(--font-weight-semibold);
        color: inherit;
        margin-bottom: var(--scale-size-8);
    }

    &__description,
    &__trust-hint,
    &__redirect-hint {
        font-size: var(--font-size-xs);
        line-height: var(--font-line-height-xs);
        margin-bottom: 0;
    }

    &__trust-hint {
        width: 100%;
        color: var(--color-text-secondary-default);
        text-align: left;
        margin-top: var(--scale-size-16);
    }

    &__redirect-hint {
        width: 100%;
        border-top: 1px solid var(--color-border-secondary-default);
        padding-top: var(--scale-size-20);
        margin-top: var(--scale-size-24);
        color: var(--color-text-secondary-default);
        font-size: var(--font-size-2xs);
        line-height: var(--font-line-height-2xs);
        overflow-wrap: anywhere;
    }

    &__redirect-host {
        font-weight: var(--font-weight-medium);
    }

    &__button-container {
        display: flex;
        width: 100%;
        gap: var(--scale-size-12);
        margin-top: var(--scale-size-24);

        .mt-button {
            flex: 1;
        }
    }

    @media screen and (max-width: 480px) {
        padding: var(--scale-size-16);

        &__card {
            padding: var(--scale-size-24);
        }

        &__button-container {
            flex-direction: column;
        }
    }
}
</style>
