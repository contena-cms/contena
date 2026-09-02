<template>
    <ct-block name="ct_system_config">
        <div class="ct-system-config">
            <ct-block name="ct_system_config_channel_switch">
                <ct-channel-switch
                    v-if="channelSwitchable"
                    :label="t('ct-settings.system-config.labelChannelSelect')"
                    @change-channel-id="onChannelChanged"
                />
            </ct-block>
            <ct-block name="ct_system_config_content_card">
                <mt-card
                    v-for="(card, index) in config"
                    :key="index"
                    position-identifier="ct-system-config-content"
                    :class="[
                        `ct-system-config__card--${index}`,
                        card.name ? `ct-system-config__card--${kebabCase(card.name)}` : null,
                    ]"
                    :is-loading="isLoading"
                    :title="getInlineSnippet(card.title)"
                    :subtitle="getInlineSnippet(card.subtitle)"
                >
                    <slot name="title">
                        <ct-ai-copilot-badge v-if="card.aiBadge" />
                    </slot>

                    <slot
                        name="beforeElements"
                        v-bind="{ card, config: actualConfigData[currentChannelId], currentChannelId }"
                    ></slot>

                    <template v-if="!isLoading">
                        <template v-for="element in card.elements">
                            <slot
                                name="card-element"
                                v-bind="{
                                    element: getElementBind(element),
                                    config: actualConfigData[currentChannelId],
                                    card,
                                    currentChannelId,
                                }"
                            >
                                <ct-block name="ct_system_config_content_card_group">
                                    <template v-if="isMeteorComponent(element)">
                                        <ct-inherit-wrapper
                                            v-model:value="actualConfigData[currentChannelId][element.name]"
                                            v-bind="getInheritWrapperBind(element)"
                                            :has-parent="isNotDefaultChannel"
                                            :inherited-value="getInheritedValue(element)"
                                            :class="'ct-system-config--field-' + kebabCase(element.name)"
                                        >
                                            <template #content="props">
                                                <ct-form-field-renderer
                                                    v-if="props"
                                                    v-bind="getMeteorElementBind(element, props)"
                                                    v-on="getMeteorElementEventsHandler(element, props)"
                                                />
                                            </template>
                                        </ct-inherit-wrapper>
                                    </template>

                                    <template v-else>
                                        <ct-block name="ct_system_config_content_card_field">
                                            <ct-inherit-wrapper
                                                v-model:value="actualConfigData[currentChannelId][element.name]"
                                                v-bind="getInheritWrapperBind(element)"
                                                :has-parent="isNotDefaultChannel"
                                                :inherited-value="getInheritedValue(element)"
                                                :class="'ct-system-config--field-' + kebabCase(getElementBind(element).name)"
                                            >
                                                <template #content="props">
                                                    <ct-form-field-renderer
                                                        v-bind="getElementBind(element, props)"
                                                        :key="props.isInheritField + props.isInherited"
                                                        :disabled="props.isInherited"
                                                        :value="props.currentValue"
                                                        :error="getFieldError(element.name)"
                                                        @update:value="props.updateCurrentValue"
                                                    />
                                                </template>
                                            </ct-inherit-wrapper>
                                        </ct-block>
                                    </template>
                                </ct-block>
                            </slot>
                        </template>
                        <slot name="card-element-last"></slot>
                    </template>

                    <slot
                        name="afterElements"
                        v-bind="{
                            card,
                            config: actualConfigData[currentChannelId],
                            index,
                            isNotDefaultChannel,
                            inheritance: actualConfigData.null,
                            currentChannelId,
                        }"
                    >
                    </slot>
                </mt-card>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import ErrorResolverSystemConfig from 'src/core/data/error-resolver.system-config.data';
import deepCloneWithEntity from 'src/core/service/utils/entity-clone.utils';
import './ct-system-config.scss';
const {
    object,
    types,
    string: { kebabCase: kebabCaseString },
} = Contena.Utils;
const { mapSystemConfigErrors } = Contena.Component.getComponentHelper();

const props = defineProps({
    domain: {
        required: true,
        type: String,
    },
    channelId: {
        type: String,
        default: null,
    },
    channelSwitchable: {
        type: Boolean,
        default: false,
    },
    inherit: {
        type: Boolean,
        default: true,
    },
});
const emit = defineEmits([
    'loading-changed',
    'config-changed',
]);

import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';

const { t } = useI18n();
const { createNotificationError } = useNotification();
const { getInlineSnippet } = useInlineSnippet();

const systemConfigApiService = inject('systemConfigApiService');

const isLoading = ref(false);
const currentChannelId = ref(props.channelId);
const config = ref({});
const actualConfigData = ref({});
const initialConfigData = ref({});
const typesWithMapInheritanceSupport = computed(() => {
    return [
        'text',
        'textarea',
        'url',
        'password',
        'int',
        'float',
        'checkbox',
        'colorpicker',
    ];
});
const isNotDefaultChannel = computed(() => currentChannelId.value !== null);

const getFieldError = (fieldName) => {
    return mapSystemConfigErrors(ErrorResolverSystemConfig.ENTITY_NAME, currentChannelId.value, fieldName);
};
const createdComponent = async () => {
    isLoading.value = true;
    try {
        actualConfigData.value = {};
        initialConfigData.value = {};

        await readConfig();
        await readAll();
    } catch (error) {
        if (error?.response?.data?.errors) {
            createErrorNotification(error.response.data.errors);
        }
    } finally {
        isLoading.value = false;
    }
};
const readConfig = async () => {
    config.value = await systemConfigApiService.getConfig(props.domain);
};
const readAll = async () => {
    isLoading.value = true;
    try {
        const channelKey = String(currentChannelId.value);
        if (Object.prototype.hasOwnProperty.call(actualConfigData.value, channelKey)) {
            return;
        }

        const values = await systemConfigApiService.getValues(props.domain, currentChannelId.value);
        actualConfigData.value[channelKey] = values;
        initialConfigData.value[channelKey] = object.deepCopyObject(values);
    } finally {
        isLoading.value = false;
    }
};
const saveAll = () => {
    isLoading.value = true;

    const changedConfigData = getChangedConfigData();
    if (!hasConfigChanges(changedConfigData)) {
        isLoading.value = false;
        return Promise.resolve();
    }

    const additionalParams = hasCacheRelevantChanges(changedConfigData) ? { silent: false } : {};

    return systemConfigApiService
        .batchSave(changedConfigData, additionalParams)
        .then(() => {
            initialConfigData.value = object.deepCopyObject(actualConfigData.value);
        })
        .finally(() => {
            isLoading.value = false;
        });
};
const getChangedConfigData = () => {
    const changedConfig = {};

    Object.entries(actualConfigData.value).forEach(
        ([
            channelKey,
            configValues,
        ]) => {
            const initialConfig = initialConfigData.value[channelKey] ?? {};
            const changedValues = {};

            Object.entries(configValues).forEach(
                ([
                    key,
                    value,
                ]) => {
                    if (!types.isEqual(value, initialConfig[key])) {
                        changedValues[key] = value;
                    }
                },
            );

            if (hasConfigChanges(changedValues)) {
                changedConfig[channelKey] = changedValues;
            }
        },
    );

    if (!hasConfigChanges(changedConfig)) {
        return {};
    }

    return changedConfig;
};
const hasConfigChanges = (configData) => {
    return Object.keys(configData).length > 0;
};
const hasCacheRelevantChanges = (changedConfigData) => {
    const cacheRelevantFieldNames = getCacheRelevantFieldNames();

    return Object.values(changedConfigData).some((values) => {
        return Object.keys(values).some((key) => cacheRelevantFieldNames.has(key));
    });
};
const getCacheRelevantFieldNames = () => {
    const fieldNames = new Set();

    config.value.forEach((card) => {
        card.elements?.forEach((element) => {
            if (element.config?.cacheRelevant === true) {
                fieldNames.add(element.name);
            }
        });
    });

    return fieldNames;
};
const createErrorNotification = (errors) => {
    let message = `<div>${t('ct-config-form-renderer.configLoadErrorMessage', {}, errors.length)}</div><ul>`;

    errors.forEach((error) => {
        message = `${message}<li>${error.detail}</li>`;
    });
    message += '</ul>';

    createNotificationError({
        message: message,
        autoClose: false,
    });
};
const onChannelChanged = (channelId) => {
    currentChannelId.value = channelId;
    void readAll();
};
const getInheritedValue = (element) => {
    let value = actualConfigData.value.null?.[element.name];

    if (typeof value === 'object' && !Array.isArray(value) && value !== null) {
        value = deepCloneWithEntity(value);
    }

    if (value) {
        return value;
    }

    if (element.config?.componentName === 'ct-switch-field') {
        return false;
    }

    switch (element.type) {
        case 'date':
        case 'datetime':
        case 'single-select':
        case 'colorpicker':
        case 'password':
        case 'url':
        case 'text':
        case 'textarea':
        case 'text-editor':
            return '';
        case 'multi-select':
            return [];
        case 'checkbox':
        case 'bool':
            return false;
        case 'float':
        case 'int':
            return 0;
        default:
            return null;
    }
};
const hasMapInheritanceSupport = (element) => {
    return typesWithMapInheritanceSupport.value.includes(element.type);
};
const getElementBind = (element, mapInheritance) => {
    const bind = object.deepCopyObject(element);

    if (!hasMapInheritanceSupport(element)) {
        delete bind.config.label;
        delete bind.config.helpText;
    } else {
        bind.mapInheritance = mapInheritance;
    }

    // Add select properties
    if (
        [
            'single-select',
            'multi-select',
        ].includes(bind.type)
    ) {
        bind.config.labelProperty = 'name';
        bind.config.valueProperty = 'id';

        if (bind.config.required) {
            bind.config.hideClearableButton = true;
        }
    }

    if (element.type === 'text-editor') {
        bind.config.componentName = 'mt-text-editor';
    }

    return bind;
};
const getInheritWrapperBind = (element) => {
    if (hasMapInheritanceSupport(element)) {
        return {};
    }

    if (isMeteorComponent(element)) {
        return {};
    }

    return {
        label: getInlineSnippet(element.config.label),
        helpText: getInlineSnippet(element.config.helpText),
    };
};
const emitConfig = () => {
    emit('config-changed', actualConfigData.value[String(currentChannelId.value)] ?? {});
};
const kebabCase = (value) => {
    return kebabCaseString(value);
};
const isMeteorComponent = (element) => {
    const typesWithMeteorSupport = [
        'bool',
        'switch',
        'text',
        'textarea',
        'url',
        'checkbox',
        'colorpicker',
        'password',
        'date',
        'datetime',
        'time',
        'single-select',
        'multi-select',
        'float',
        'int',
    ];

    return typesWithMeteorSupport.includes(element.type);
};
const getMeteorElementBind = (element, mapInheritance) => {
    const bind = {};

    // Bind necessary props to ct-form-field-renderer
    bind.value = mapInheritance?.currentValue;
    bind.type = element.type;
    bind.config = { ...(element.config || {}) };
    bind.error = getFieldError(element.name);

    // Inheritance bindings
    bind.inheritedValue = getInheritedValue(element);
    bind.isInheritanceField = mapInheritance?.isInheritField;
    bind.isInherited = mapInheritance?.isInherited;
    bind.disabled = mapInheritance?.isInherited || element.config?.disabled;

    // Handle datepicker date/datetime value format
    if (element.type === 'date') {
        bind.dateType = 'date';
    }

    if (element.type === 'datetime') {
        bind.dateType = 'datetime';
    }

    // Handle select properties
    if (
        [
            'single-select',
            'multi-select',
        ].includes(element.type)
    ) {
        bind.config.labelProperty = 'name';
        bind.config.valueProperty = 'id';

        if (bind.config.required) {
            bind.config.hideClearableButton = true;
        }
    }

    // Handle multi select
    if (element.type === 'multi-select') {
        bind.enableMultiSelection = true;
    }

    return bind;
};
const getMeteorElementEventsHandler = (element, mapInheritance) => {
    const eventHandler = {};

    eventHandler['update:value'] = mapInheritance?.updateCurrentValue;
    eventHandler['inheritance-remove'] = mapInheritance?.removeInheritance;
    eventHandler['inheritance-restore'] = mapInheritance?.restoreInheritance;
    return eventHandler;
};

watch(
    () => actualConfigData.value,
    () => {
        emitConfig();
    },
    { deep: true },
);
watch(
    () => props.domain,
    () => {
        void createdComponent();
    },
);
watch(
    () => isLoading.value,
    (value) => {
        emit('loading-changed', value);
    },
);

void createdComponent();

ctDefinePublic({
    systemConfigApiService,
    isLoading,
    currentChannelId,
    isNotDefaultChannel,
    config,
    actualConfigData,
    initialConfigData,
    typesWithMapInheritanceSupport,
    getFieldError,
    createdComponent,
    readConfig,
    readAll,
    onChannelChanged,
    saveAll,
    getChangedConfigData,
    hasConfigChanges,
    hasCacheRelevantChanges,
    getCacheRelevantFieldNames,
    createErrorNotification,
    hasMapInheritanceSupport,
    getElementBind,
    getInheritWrapperBind,
    getInheritedValue,
    emitConfig,
    kebabCase,
    isMeteorComponent,
    getMeteorElementBind,
    getMeteorElementEventsHandler,
});

defineExpose({
    systemConfigApiService,
    isLoading,
    currentChannelId,
    isNotDefaultChannel,
    config,
    actualConfigData,
    initialConfigData,
    typesWithMapInheritanceSupport,
    getFieldError,
    createdComponent,
    readConfig,
    readAll,
    onChannelChanged,
    saveAll,
    getChangedConfigData,
    hasConfigChanges,
    hasCacheRelevantChanges,
    getCacheRelevantFieldNames,
    createErrorNotification,
    hasMapInheritanceSupport,
    getElementBind,
    getInheritWrapperBind,
    getInheritedValue,
    emitConfig,
    kebabCase,
    isMeteorComponent,
    getMeteorElementBind,
    getMeteorElementEventsHandler,
});
</script>
