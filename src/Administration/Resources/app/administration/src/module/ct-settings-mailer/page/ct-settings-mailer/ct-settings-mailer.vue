<template>
    <ct-block name="ct_settings_mailer">
        <ct-page class="ct-settings-mailer">
            <template #smart-bar-header>
                <h2>{{ $t('ct-settings-mailer.general.title') }}</h2>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_mailer_actions">
                    <ct-button-process
                        variant="primary"
                        :is-loading="isLoading"
                        :process-success="isSaveSuccessful"
                        :disabled="isLoading || !hasSelectedAgent || undefined"
                        @update:process-success="isSaveSuccessful = false"
                        @click="onSave"
                    >
                        {{ $t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-card-view>
                    <ct-block name="ct_settings_mailer_config">
                        <mt-card position-identifier="ct-settings-mailer-configuration" :is-loading="isLoading">
                            <ct-block name="ct_settings_mailer_agent">
                                <mt-select
                                    v-model="mailerSettings['core.mailerSettings.emailAgent']"
                                    class="ct-settings-mailer__agent"
                                    :label="$t('ct-settings-mailer.configuration.agent')"
                                    :placeholder="$t('ct-settings-mailer.configuration.agentPlaceholder')"
                                    :options="emailAgentOptions"
                                />
                            </ct-block>

                            <ct-block name="ct_settings_mailer_local_settings">
                                <mt-select
                                    v-if="mailerSettings['core.mailerSettings.emailAgent'] === 'local'"
                                    v-model="mailerSettings['core.mailerSettings.sendMailOptions']"
                                    class="ct-settings-mailer__sendmail-options"
                                    :label="$t('ct-settings-mailer.configuration.sendmailOptions')"
                                    :options="sendmailOptions"
                                />
                            </ct-block>

                            <ct-block name="ct_settings_mailer_smtp_settings">
                                <div v-if="isSmtpMode" class="ct-settings-mailer__smtp-grid">
                                    <mt-text-field
                                        v-model="mailerSettings['core.mailerSettings.host']"
                                        required
                                        :label="$t('ct-settings-mailer.smtp.host')"
                                        :placeholder="$t('ct-settings-mailer.smtp.hostPlaceholder')"
                                        :error="smtpHostError"
                                        @update:model-value="smtpHostError = null"
                                    />

                                    <mt-number-field
                                        v-model="mailerSettings['core.mailerSettings.port']"
                                        required
                                        number-type="int"
                                        :step="1"
                                        :max="65535"
                                        :label="$t('ct-settings-mailer.smtp.port')"
                                        :placeholder="$t('ct-settings-mailer.smtp.portPlaceholder')"
                                        :error="smtpPortError"
                                        @update:model-value="smtpPortError = null"
                                    />

                                    <template v-if="!isOauthMode">
                                        <mt-text-field
                                            v-model="mailerSettings['core.mailerSettings.username']"
                                            :label="$t('ct-settings-mailer.smtp.username')"
                                            :placeholder="$t('ct-settings-mailer.smtp.usernamePlaceholder')"
                                        />

                                        <mt-password-field
                                            v-model="mailerSettings['core.mailerSettings.password']"
                                            :label="$t('ct-settings-mailer.smtp.password')"
                                            :placeholder="$t('ct-settings-mailer.smtp.passwordPlaceholder')"
                                        />
                                    </template>

                                    <template v-else>
                                        <mt-text-field
                                            v-model="mailerSettings['core.mailerSettings.oauthUrl']"
                                            required
                                            :label="$t('ct-settings-mailer.smtp.oauthUrl')"
                                            :placeholder="$t('ct-settings-mailer.smtp.oauthUrlPlaceholder')"
                                        />

                                        <mt-text-field
                                            v-model="mailerSettings['core.mailerSettings.oauthScope']"
                                            required
                                            :label="$t('ct-settings-mailer.smtp.oauthScope')"
                                            :placeholder="$t('ct-settings-mailer.smtp.oauthScopePlaceholder')"
                                        />

                                        <mt-text-field
                                            v-model="mailerSettings['core.mailerSettings.clientId']"
                                            required
                                            :label="$t('ct-settings-mailer.smtp.clientId')"
                                            :placeholder="$t('ct-settings-mailer.smtp.clientIdPlaceholder')"
                                        />

                                        <mt-password-field
                                            v-model="mailerSettings['core.mailerSettings.clientSecret']"
                                            required
                                            :label="$t('ct-settings-mailer.smtp.clientSecret')"
                                            :placeholder="$t('ct-settings-mailer.smtp.clientSecretPlaceholder')"
                                        />
                                    </template>

                                    <mt-select
                                        v-model="mailerSettings['core.mailerSettings.encryption']"
                                        :label="$t('ct-settings-mailer.smtp.encryption')"
                                        :placeholder="$t('ct-settings-mailer.smtp.encryptionPlaceholder')"
                                        :options="encryptionOptions"
                                    />

                                    <mt-text-field
                                        v-model="mailerSettings['core.mailerSettings.senderAddress']"
                                        :label="$t('ct-settings-mailer.smtp.senderAddress')"
                                        :placeholder="$t('ct-settings-mailer.smtp.senderAddressPlaceholder')"
                                    />

                                    <mt-text-field
                                        v-model="mailerSettings['core.mailerSettings.deliveryAddress']"
                                        :label="$t('ct-settings-mailer.smtp.deliveryAddress')"
                                        :placeholder="$t('ct-settings-mailer.smtp.deliveryAddressPlaceholder')"
                                    />
                                </div>
                            </ct-block>

                            <ct-block name="ct_settings_mailer_delivery">
                                <mt-switch
                                    v-if="hasSelectedAgent"
                                    v-model="mailerSettings['core.mailerSettings.disableDelivery']"
                                    class="ct-settings-mailer__disable-delivery"
                                    :label="$t('ct-settings-mailer.configuration.disableDelivery')"
                                />
                            </ct-block>
                        </mt-card>
                    </ct-block>
                </ct-card-view>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type SystemConfigApiService from 'src/core/service/api/system-config.api.service';
import { useNotification } from 'src/app/composables/use-notification';

type MailerSetting = string | number | boolean | null;
type MailerSettings = Record<string, MailerSetting>;

const environmentAgent = 'environment';
defineProps({});
const { t } = useI18n();
const { createNotificationError } = useNotification();
const systemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');

if (!systemConfigApiService) {
    throw new Error('System config API service is not available.');
}

const mailerSettings = reactive<MailerSettings>({
    'core.mailerSettings.emailAgent': 'smtp',
    'core.mailerSettings.host': null,
    'core.mailerSettings.port': null,
    'core.mailerSettings.username': null,
    'core.mailerSettings.password': null,
    'core.mailerSettings.oauthUrl': null,
    'core.mailerSettings.oauthScope': null,
    'core.mailerSettings.clientId': null,
    'core.mailerSettings.clientSecret': null,
    'core.mailerSettings.encryption': 'null',
    'core.mailerSettings.senderAddress': null,
    'core.mailerSettings.deliveryAddress': null,
    'core.mailerSettings.disableDelivery': false,
    'core.mailerSettings.sendMailOptions': '-t -i',
});
const isLoading = ref(true);
const isSaveSuccessful = ref(false);
const smtpHostError = ref<{ detail: string } | null>(null);
const smtpPortError = ref<{ detail: string } | null>(null);

const emailAgentOptions = computed(() => [
    { value: environmentAgent, label: t('ct-settings-mailer.configuration.environmentAgent') },
    { value: 'local', label: t('ct-settings-mailer.configuration.localAgent') },
    { value: 'smtp', label: t('ct-settings-mailer.configuration.smtpAgent') },
    { value: 'smtp+oauth', label: t('ct-settings-mailer.configuration.smtpOauthAgent') },
]);
const sendmailOptions = computed(() => [
    { value: '-bs', label: t('ct-settings-mailer.configuration.synchronous') },
    { value: '-t -i', label: t('ct-settings-mailer.configuration.asynchronous') },
]);
const encryptionOptions = computed(() => [
    { value: 'null', label: t('ct-settings-mailer.encryption.none') },
    { value: 'ssl', label: t('ct-settings-mailer.encryption.ssl') },
    { value: 'tls', label: t('ct-settings-mailer.encryption.tls') },
]);
const isSmtpMode = computed(() =>
    [
        'smtp',
        'smtp+oauth',
    ].includes(String(mailerSettings['core.mailerSettings.emailAgent'])),
);
const isOauthMode = computed(() => mailerSettings['core.mailerSettings.emailAgent'] === 'smtp+oauth');
const hasSelectedAgent = computed(() => mailerSettings['core.mailerSettings.emailAgent'] !== null);

async function loadMailerSettings(): Promise<void> {
    isLoading.value = true;

    try {
        const values = (await systemConfigApiService.getValues('core.mailerSettings')) as MailerSettings;
        Object.assign(mailerSettings, values);

        if (!Object.prototype.hasOwnProperty.call(values, 'core.mailerSettings.emailAgent')) {
            mailerSettings['core.mailerSettings.emailAgent'] = 'smtp';
        }

        if (
            Object.prototype.hasOwnProperty.call(values, 'core.mailerSettings.emailAgent') &&
            values['core.mailerSettings.emailAgent'] === ''
        ) {
            mailerSettings['core.mailerSettings.emailAgent'] = environmentAgent;
        }
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('global.notification.notificationLoadingErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

async function onSave(): Promise<void> {
    isSaveSuccessful.value = false;

    if (isSmtpMode.value && !validateSmtpConfiguration()) {
        createNotificationError({
            title: t('global.default.error'),
            message: t('ct-settings-mailer.smtp.invalidConfiguration'),
        });

        return;
    }

    isLoading.value = true;

    try {
        const values = { ...mailerSettings };
        if (values['core.mailerSettings.emailAgent'] === environmentAgent) {
            values['core.mailerSettings.emailAgent'] = '';
        }

        await systemConfigApiService.saveValues(values);
        isSaveSuccessful.value = true;
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('global.notification.notificationSaveErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

function validateSmtpConfiguration(): boolean {
    const host = mailerSettings['core.mailerSettings.host'];
    const port = mailerSettings['core.mailerSettings.port'];
    const requiredMessage = t('ct-settings-mailer.smtp.required');

    smtpHostError.value = typeof host === 'string' && host.trim() !== '' ? null : { detail: requiredMessage };
    smtpPortError.value =
        typeof port === 'number' && Number.isInteger(port) && port > 0 && port <= 65535
            ? null
            : { detail: t('ct-settings-mailer.smtp.invalidPort') };

    return smtpHostError.value === null && smtpPortError.value === null;
}

onMounted(loadMailerSettings);

ctDefinePublic({
    mailerSettings,
    isLoading,
    isSaveSuccessful,
    smtpHostError,
    smtpPortError,
    emailAgentOptions,
    sendmailOptions,
    encryptionOptions,
    isSmtpMode,
    isOauthMode,
    hasSelectedAgent,
    loadMailerSettings,
    onSave,
});

defineExpose({
    mailerSettings,
    isLoading,
    isSaveSuccessful,
    smtpHostError,
    smtpPortError,
    emailAgentOptions,
    sendmailOptions,
    encryptionOptions,
    isSmtpMode,
    isOauthMode,
    hasSelectedAgent,
    loadMailerSettings,
    onSave,
});
</script>

<style>
.ct-settings-mailer .ct-card-view__content {
    max-width: 960px;
    margin: 0 auto;
}

.ct-settings-mailer__smtp-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: var(--scale-size-24);
    row-gap: var(--scale-size-16);
    margin-top: var(--scale-size-16);
}

.ct-settings-mailer__smtp-grid > * {
    margin-bottom: 0;
}

.ct-settings-mailer__disable-delivery {
    margin-top: var(--scale-size-12);
    margin-bottom: 0;
}

@media (max-width: 767px) {
    .ct-settings-mailer__smtp-grid {
        grid-template-columns: 1fr;
    }
}
</style>
