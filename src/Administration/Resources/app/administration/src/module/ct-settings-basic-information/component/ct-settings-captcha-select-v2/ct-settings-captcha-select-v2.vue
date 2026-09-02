<template>
    <ct-block name="ct_settings_captcha_select_v2">
        <div class="ct-settings-captcha-select-v2">
            <ct-block name="ct_settings_captcha_select_v2_multi_select">
                <mt-select
                    v-model="activeCaptchaSelect"
                    v-bind="attributes"
                    enable-multi-selection
                    :options="availableCaptchas"
                />
            </ct-block>

            <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v2">
                <div
                    v-if="currentValue.googleReCaptchaV2.isActive"
                    class="ct-settings-captcha-select-v2__google-recaptcha-v2"
                >
                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v2_description">
                        <p
                            class="ct-settings-captcha-select-v2__description ct-settings-captcha-select-v2__google-recaptcha-v2-description"
                        >
                            {{ $t('ct-settings-basic-information.captcha.label.googleReCaptchaV2CheckboxDescription') }}
                        </p>

                        <p
                            class="ct-settings-captcha-select-v2__description ct-settings-captcha-select-v2__google-recaptcha-v2-description"
                        >
                            {{ $t('ct-settings-basic-information.captcha.label.googleReCaptchaV2InvisibleDescription') }}
                        </p>
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v2_site_key">
                        <mt-text-field
                            v-model="currentValue.googleReCaptchaV2.config.siteKey"
                            name="googleReCaptchaV2SiteKey"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV2SiteKey')"
                        />
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v2_secret_key">
                        <mt-text-field
                            v-model="currentValue.googleReCaptchaV2.config.secretKey"
                            name="googleReCaptchaV2SecretKey"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV2SecretKey')"
                        />
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v2_invisible">
                        <mt-switch
                            v-model="currentValue.googleReCaptchaV2.config.invisible"
                            bordered
                            name="googleReCaptchaV2Invisible"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV2Invisible')"
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v3">
                <div
                    v-if="currentValue.googleReCaptchaV3.isActive"
                    class="ct-settings-captcha-select-v2__google-recaptcha-v3"
                >
                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v3_description">
                        <p
                            class="ct-settings-captcha-select-v2__description ct-settings-captcha-select-v2__google-recaptcha-v3-description"
                        >
                            {{ $t('ct-settings-basic-information.captcha.label.googleReCaptchaV3Description') }}
                        </p>
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v3_site_key">
                        <mt-text-field
                            v-model="currentValue.googleReCaptchaV3.config.siteKey"
                            name="googleReCaptchaV3SiteKey"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV3SiteKey')"
                        />
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v3_secret_key">
                        <mt-text-field
                            v-model="currentValue.googleReCaptchaV3.config.secretKey"
                            name="googleReCaptchaV3SecretKey"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV3SecretKey')"
                        />
                    </ct-block>

                    <ct-block name="ct_settings_captcha_select_v2_google_recaptcha_v3_threshold_score">
                        <mt-number-field
                            v-model="currentValue.googleReCaptchaV3.config.thresholdScore"
                            name="googleReCaptchaV3ThresholdScore"
                            number-type="float"
                            :min="0"
                            :max="1"
                            :step="0.1"
                            :label="$t('ct-settings-basic-information.captcha.label.googleReCaptchaV3ThresholdScore')"
                            :help-text="
                                $t('ct-settings-basic-information.captcha.label.googleReCaptchaV3DescriptionThresholdScore')
                            "
                        />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_settings_captcha_select_v2_basic_captcha_notice">
                <div v-if="currentValue.basicCaptcha.isActive">
                    <mt-banner
                        variant="neutral"
                        :title="$t('ct-settings-basic-information.captcha.basicCaptchaNoticeTitle')"
                        class="ct-settings-captcha-select-v2__basic-captcha-notice"
                    >
                        {{ $t('ct-settings-basic-information.captcha.basicCaptchaNotice') }}
                    </mt-banner>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref, useAttrs, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';
import type CaptchaService from '../../service/captcha.service';
import './ct-settings-captcha-select-v2.scss';

interface CaptchaItem {
    name: string;
    isActive: boolean;
}

interface GoogleReCaptchaV2Item extends CaptchaItem {
    config: {
        siteKey: string;
        secretKey: string;
        invisible: boolean;
    };
}

interface GoogleReCaptchaV3Item extends CaptchaItem {
    config: {
        siteKey: string;
        secretKey: string;
        thresholdScore: number;
    };
}

interface CaptchaConfiguration {
    honeypot: CaptchaItem;
    basicCaptcha: CaptchaItem;
    googleReCaptchaV2: GoogleReCaptchaV2Item;
    googleReCaptchaV3: GoogleReCaptchaV3Item;
}

interface CaptchaOption {
    label: string;
    value: string;
}

const props = withDefaults(
    defineProps<{
        value?: CaptchaConfiguration | null;
    }>(),
    {
        value: null,
    },
);
const emit = defineEmits<{
    'update:value': [value: CaptchaConfiguration];
}>();

const attrs = useAttrs();
const { t } = useI18n();
const { getInlineSnippet } = useInlineSnippet();

const captchaService = inject<CaptchaService>('captchaService');
if (!captchaService) {
    throw new Error('The captcha service is unavailable.');
}

const availableCaptchas = ref<CaptchaOption[]>([]);
const currentValue = computed<CaptchaConfiguration>({
    get: () => props.value as CaptchaConfiguration,
    set: (value) => emit('update:value', value),
});
const getTranslations = (): Record<string, unknown> => {
    return [
        'label',
        'placeholder',
        'helpText',
    ]
        .filter((name) => Boolean(attrs[name]))
        .reduce<Record<string, unknown>>((translations, name) => {
            const value = attrs[name];

            return {
                ...translations,
                [name]: typeof value === 'string' ? value : getInlineSnippet(value as Record<string, string>),
            };
        }, {});
};
const attributes = computed(() => ({
    ...attrs,
    ...getTranslations(),
}));
const activeCaptchaSelect = computed<string[]>({
    get: () => {
        return Object.entries(currentValue.value)
            .filter(
                ([
                    ,
                    captcha,
                ]) => captcha.isActive,
            )
            .map(([technicalName]) => technicalName);
    },
    set: (selectedCaptchas) => {
        Object.entries(currentValue.value).forEach(
            ([
                technicalName,
                captcha,
            ]) => {
                captcha.isActive = selectedCaptchas.includes(technicalName);
            },
        );
    },
});
const renderCaptchaOption = (technicalName: string): CaptchaOption => {
    return {
        label: t(`ct-settings-basic-information.captcha.label.${technicalName}`),
        value: technicalName,
    };
};
const setCaptchaOptions = (list: string[]): void => {
    availableCaptchas.value = list.map((technicalName) => renderCaptchaOption(technicalName));
};
const createdComponent = (): void => {
    captchaService.list(setCaptchaOptions);
};

watch(currentValue, (value) => emit('update:value', value), { deep: true });
createdComponent();

ctDefinePublic({
    availableCaptchas,
    attributes,
    currentValue,
    activeCaptchaSelect,
    createdComponent,
    setCaptchaOptions,
    renderCaptchaOption,
    getTranslations,
});

defineExpose({
    availableCaptchas,
    attributes,
    currentValue,
    activeCaptchaSelect,
    createdComponent,
    setCaptchaOptions,
    renderCaptchaOption,
    getTranslations,
});
</script>
