<template>
    <ct-block name="ct_extension_config">
        <ct-meteor-page class="ct-extension-config" :from-link="props.fromLink">
            <template #smart-bar-icon>
                <ct-extension-icon
                    class="ct-extension-config__extension-icon"
                    :src="image"
                    :alt="
                        translate(
                            'ct-extension.component.ct-extension-config.imageDescription',
                            { extensionName: extensionLabel },
                            0,
                        )
                    "
                />
            </template>

            <template #smart-bar-header>
                {{ extensionLabel }}
            </template>

            <template v-if="extension" #smart-bar-header-meta>
                <span v-if="extension.producerWebsite && extension.producerName">
                    {{ translate('ct-extension.component.ct-extension-config.labelBy') }}

                    <!-- TODO Codemod: Converted from ct-external-link - please check if everything works correctly -->
                    <mt-external-link small :href="extension.producerWebsite" class="ct-extension-config__producer-link">
                        {{ extension.producerName }}
                    </mt-external-link>
                </span>

                <span v-else-if="extension.producerName">
                    {{ translate('ct-extension.component.ct-extension-config.labelBy') }} {{ extension.producerName }}
                </span>
            </template>

            <template #smart-bar-actions>
                <mt-button variant="primary" class="ct-extension-config__save-action" size="default" @click.prevent="onSave">
                    {{ translate('global.default.save') }}
                </mt-button>
            </template>

            <template #default>
                <div class="ct-extension-config__content">
                    <ct-system-config ref="systemConfig" :domain="domain" />
                </div>
            </template>
        </ct-meteor-page>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-extension-config.scss';
type SystemConfigComponent = {
    saveAll: () => Promise<void>;
};

const props = defineProps({
    namespace: {
        type: String,
        required: true,
    },
    fromLink: {
        type: Object,
        required: false,
        default: null,
    },
});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const translate = t;
const systemConfig = ref<SystemConfigComponent | null>(null);

const contenaExtensionService = inject('contenaExtensionService');

const extension = ref(null);

const domain = computed(() => {
    return `${props.namespace}.config`;
});
const myExtensions = computed(() => {
    return Contena.Store.get('contenaExtensions').myExtensions.data;
});
const defaultExtensionAsset = computed(() => {
    return Contena.Filter.getByName('asset')(
        'administration/administration/static/img/services/extension-icon-placeholder.svg',
    );
});
const image = computed(() => {
    if (extension.value?.icon) {
        return extension.value.icon;
    }

    if (extension.value?.iconRaw) {
        return `data:image/png;base64, ${extension.value.iconRaw}`;
    }

    return defaultExtensionAsset.value;
});
const extensionLabel = computed(() => {
    return extension.value?.label ?? props.namespace;
});

const createdComponent = async () => {
    if (!myExtensions.value.length) {
        await contenaExtensionService.updateExtensionData();
    }

    refreshExtension();
};
const refreshExtension = () => {
    extension.value =
        myExtensions.value.find((ext) => {
            return ext.name === props.namespace;
        }) ?? null;
};
const onSave = async () => {
    try {
        await systemConfig.value?.saveAll();

        createNotificationSuccess({
            message: t('ct-extension.component.ct-extension-config.messageSaveSuccess'),
        });
    } catch (err) {
        createNotificationError({
            message: err as string,
        });
    }
};

void createdComponent();

ctDefinePublic({
    contenaExtensionService,
    extension,
    domain,
    myExtensions,
    defaultExtensionAsset,
    image,
    extensionLabel,
    createdComponent,
    refreshExtension,
    onSave,
});

defineExpose({
    contenaExtensionService,
    extension,
    domain,
    myExtensions,
    defaultExtensionAsset,
    image,
    extensionLabel,
    createdComponent,
    refreshExtension,
    onSave,
});
</script>
