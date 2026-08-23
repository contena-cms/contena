<template>
    <ct-meteor-card class="ct-extension-card-base" :class="extensionCardClasses">
        <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
        <mt-loader v-if="isLoading" />

        <div class="ct-extension-card-base__switch">
            <ct-block name="sw_extension_card_base_activation_switch_switch">
                <mt-switch v-model="isActive" :disabled="extensionManagementDisabled || !isInstalled" />
            </ct-block>
        </div>

        <ct-extension-icon :src="image" />

        <ct-block name="sw_extension_card_base_activation_switch">
            <div class="ct-extension-card-base__info">
                <section>
                    <span class="ct-extension-card-base__info-name">
                        {{ extension.label }}
                    </span>

                    <ct-block name="sw_extension_card_base_info_inactive_label">
                        <span v-if="isInstalled && !extension.active" class="ct-extension-card-base__info-inactive">
                            {{ $t('ct-extension.component.ct-extension-card-base.inactiveLabel') }}
                        </span>
                    </ct-block>
                </section>
            </div>
        </ct-block>

        <div class="ct-extension-card-base__meta-info">
            <ct-block name="sw_extension_card_base_info_content">
                <section>
                    <span v-if="extension.version" class="ct-extension-card-base__meta-info-version">
                        {{ $t('ct-extension.my-extensions.listing.version') }}: {{ extension.version }}
                    </span>

                    <span v-if="!extensionManagementDisabled && isUpdateable">
                        <a href="#" @click.prevent="updateExtension(false)">
                            {{ $t('ct-extension.my-extensions.listing.update') }}
                        </a>
                    </span>
                </section>
            </ct-block>

            <span v-if="isInstalled">
                {{ $t('ct-extension.component.ct-extension-card-base.installedLabel') }}
                <ct-time-ago
                    v-if="installedAtDate"
                    :date="installedAtDate"
                    :date-time-format="{ month: '2-digit', day: '2-digit' }"
                />
            </span>
        </div>

        <div class="ct-extension-card-base__main-action">
            <span
                v-if="!isInstalled"
                class="ct-extension-card-base__open-extension"
                role="button"
                tabindex="0"
                @click="installAndActivateExtension"
                @keydown.enter="installAndActivateExtension"
            >
                {{ $t('ct-extension.component.ct-extension-card-base.installExtensionLabel') }}
            </span>
            <router-link v-else-if="extension.configurable" :to="configLink">
                {{ $t('global.default.configure') }}
            </router-link>
        </div>

        <ct-context-button v-if="showContextMenu" class="ct-extension-card-base__context-menu" :menu-width="180">
            <ct-block name="sw_extension_card_base_context_menu_actions">
                <ct-context-menu-item
                    v-if="openLinkExists && extension.active"
                    :disabled="!openLinkExists"
                    :router-link="link"
                >
                    {{ $t('ct-extension.component.ct-extension-card-base.contextMenu.openExtension') }}
                </ct-context-menu-item>

                <ct-context-menu-item v-if="!extensionManagementDisabled && isUpdateable" @click="updateExtension(false)">
                    {{
                        $t(
                            'ct-extension.component.ct-extension-card-base.contextMenu.updateLabel',
                            { version: extension.latestVersion },
                            0,
                        )
                    }}
                </ct-context-menu-item>

                <ct-block name="sw_extension_card_base_context_menu_actions_additional"></ct-block>

                <ct-context-menu-item
                    v-if="!extensionManagementDisabled && isRemovable"
                    class="ct-extension-card-base__remove-link"
                    variant="danger"
                    @click="openRemovalModal"
                >
                    {{ $t('global.default.remove') }}
                </ct-context-menu-item>

                <ct-context-menu-item
                    v-if="!extensionManagementDisabled && isUninstallable"
                    variant="danger"
                    @click="openUninstallModal"
                >
                    {{ $t('ct-extension.component.ct-extension-card-base.contextMenu.uninstallLabel') }}
                </ct-context-menu-item>
            </ct-block>
        </ct-context-button>

        <ct-block name="sw_extension_card_base_modals">
            <ct-extension-uninstall-modal
                v-if="showUninstallModal"
                :extension-name="extension.label"
                :is-loading="isLoading"
                @modal-close="closeUninstallModal"
                @uninstall-extension="closeModalAndUninstallExtension"
            />

            <ct-extension-removal-modal
                v-if="showRemovalModal"
                :extension-name="extension.label"
                :is-loading="isLoading"
                @modal-close="closeRemovalModal"
                @remove-extension="closeModalAndRemoveExtension"
            />
        </ct-block>
    </ct-meteor-card>
</template>

<script setup>
import './ct-extension-card-base.scss';
const { Filter } = Contena;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    extension: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['update-list']);

import { ref, computed, getCurrentInstance, inject, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();
const componentInstance = getCurrentInstance();

const contenaExtensionService = inject('contenaExtensionService');
const cacheApiService = inject('cacheApiService');

const isLoading = ref(false);
const showUninstallModal = ref(false);
const showRemovalModal = ref(false);
const openLink = ref(null);

const extensionCardClasses = computed(() => {
    return {
        'is--deactivated': isInstalled.value && !props.extension.active,
        'is--not-installed': !isInstalled.value,
    };
});
const image = computed(() => {
    if (props.extension.icon) {
        return props.extension.icon;
    }

    if (props.extension.iconRaw) {
        return `data:image/png;base64, ${props.extension.iconRaw}`;
    }

    return assetFilter.value('administration/administration/static/img/services/extension-icon-placeholder.svg');
});
const isActive = computed({
    get: () => isInstalled.value && props.extension.active,
    set: (active) => {
        if (!isInstalled.value) {
            return;
        }
        const extension = props.extension;
        extension.active = active;
        void nextTick(() => componentInstance?.proxy?.changeExtensionStatus?.());
    },
});
const isInstalled = computed(() => {
    return props.extension.installedAt !== null;
});
const installedAtDate = computed(() => {
    const installedAt = props.extension.installedAt;

    if (!installedAt || typeof installedAt === 'string' || installedAt instanceof Date) {
        return installedAt;
    }

    if (!installedAt.date) {
        return null;
    }

    if (
        ![
            'UTC',
            'Etc/UTC',
            'GMT',
        ].includes(installedAt.timezone) ||
        /(?:Z|[+-]\d{2}:?\d{2})$/.test(installedAt.date)
    ) {
        return installedAt.date;
    }

    const utcDate = installedAt.date.replace(' ', 'T').replace(/(\.\d{3})\d+$/, '$1');

    return `${utcDate}Z`;
});
const assetFilter = computed(() => {
    return Filter.getByName('asset');
});
const isRemovable = computed(() => {
    if (props.extension.installedAt === null && !props.extension.managedByComposer) {
        return true;
    }

    return false;
});
const isUninstallable = computed(() => {
    if (props.extension.installedAt !== null) {
        return true;
    }

    return false;
});
const isUpdateable = computed(() => {
    if (!props.extension || props.extension.latestVersion === null || !props.extension.allowUpdate) {
        return false;
    }

    return props.extension.latestVersion !== props.extension.version;
});
const openLinkExists = computed(() => {
    return !!link.value;
});
const configLink = computed(() => {
    if (!props.extension.configurable) {
        return null;
    }

    return {
        name: 'ct.extension.config',
        params: {
            namespace: props.extension.name,
        },
    };
});
const link = computed(() => {
    if (openLink.value) {
        return openLink.value;
    }

    return configLink.value;
});
const extensionManagementDisabled = computed(() => {
    return Contena.Store.get('context').app.config.settings?.disableExtensionManagement;
});
const showContextMenu = computed(() => {
    if (isInstalled.value && props.extension.configurable) {
        return true;
    }

    if (openLinkExists.value && props.extension.active) {
        return true;
    }

    if (!extensionManagementDisabled.value && isUpdateable.value) {
        return true;
    }

    if (!extensionManagementDisabled.value && isRemovable.value) {
        return true;
    }

    if (!extensionManagementDisabled.value && isUninstallable.value) {
        return true;
    }

    return false;
});

const createdComponent = async () => {
    openLink.value = await contenaExtensionService.getOpenLink(props.extension);
};
const emitUpdateList = () => {
    emit('update-list');
};
const getHelp = () => {};
const openRemovalModal = () => {
    showRemovalModal.value = true;
};
const openUninstallModal = () => {
    showUninstallModal.value = true;
};
const closeRemovalModal = () => {
    showRemovalModal.value = false;
};
const closeUninstallModal = () => {
    showUninstallModal.value = false;
};
const openExtension = () => {
    if (link.value) {
        void router.push(link.value);
    }
};
const reloadPage = ref(() => window.location.reload());
const clearCacheAndReloadPage = () => {
    return cacheApiService.clear().then(() => {
        reloadPage.value();
    });
};
const showExtensionErrors = (errorResponse) => {
    const translator = { $t: t };
    Contena.Service('extensionErrorService').handleErrorResponse(errorResponse, translator).forEach(createNotificationError);
};
const closeModalAndUninstallExtension = async (removeData) => {
    showUninstallModal.value = false;
    isLoading.value = true;
    try {
        await contenaExtensionService.uninstallExtension(props.extension.name, removeData);
        await clearCacheAndReloadPage();
    } catch (error) {
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const updateExtension = async () => {
    isLoading.value = true;
    try {
        if (props.extension.installedAt) {
            await contenaExtensionService.updateExtension(props.extension.name);
        }
        await clearCacheAndReloadPage();
    } catch (error) {
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const activateExtension = async () => {
    const extension = props.extension;

    try {
        isLoading.value = true;
        await contenaExtensionService.activateExtension(extension.name);
        extension.active = true;
        await clearCacheAndReloadPage();
    } catch (error) {
        extension.active = false;
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const deactivateExtension = async () => {
    const extension = props.extension;

    try {
        isLoading.value = true;
        await contenaExtensionService.deactivateExtension(extension.name);
        extension.active = false;
        await clearCacheAndReloadPage();
    } catch (error) {
        extension.active = true;
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const changeExtensionStatus = async () => {
    if (isActive.value) {
        await activateExtension();
        return;
    }

    await deactivateExtension();
};
const installExtension = async () => {
    isLoading.value = true;
    try {
        await contenaExtensionService.installExtension(props.extension.name);
        await clearCacheAndReloadPage();
    } catch (error) {
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const installAndActivateExtension = async () => {
    isLoading.value = true;
    try {
        await contenaExtensionService.installAndActivateExtension(props.extension.name);
        await clearCacheAndReloadPage();
    } catch (error) {
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const removeExtension = async () => {
    try {
        showRemovalModal.value = false;
        isLoading.value = true;
        await contenaExtensionService.removeExtension(props.extension.name);
        const extension = props.extension;
        extension.active = false;
        await clearCacheAndReloadPage();
    } catch (error) {
        showExtensionErrors(error);
    } finally {
        isLoading.value = false;
    }
};
const closeModalAndRemoveExtension = async () => {
    await removeExtension();
    showRemovalModal.value = false;
};

void createdComponent();

swDefinePublic({
    contenaExtensionService,
    cacheApiService,
    isLoading,
    showUninstallModal,
    showRemovalModal,
    openLink,
    extensionCardClasses,
    image,
    isActive,
    isInstalled,
    installedAtDate,
    assetFilter,
    isRemovable,
    isUninstallable,
    isUpdateable,
    openLinkExists,
    configLink,
    link,
    extensionManagementDisabled,
    showContextMenu,
    createdComponent,
    emitUpdateList,
    getHelp,
    openRemovalModal,
    openUninstallModal,
    closeRemovalModal,
    closeUninstallModal,
    openExtension,
    reloadPage,
    clearCacheAndReloadPage,
    showExtensionErrors,
    closeModalAndUninstallExtension,
    updateExtension,
    closeModalAndRemoveExtension,
    changeExtensionStatus,
    activateExtension,
    deactivateExtension,
    installExtension,
    installAndActivateExtension,
    removeExtension,
});

defineExpose({
    contenaExtensionService,
    cacheApiService,
    isLoading,
    showUninstallModal,
    showRemovalModal,
    openLink,
    extensionCardClasses,
    image,
    isActive,
    isInstalled,
    installedAtDate,
    assetFilter,
    isRemovable,
    isUninstallable,
    isUpdateable,
    openLinkExists,
    configLink,
    link,
    extensionManagementDisabled,
    showContextMenu,
    createdComponent,
    emitUpdateList,
    getHelp,
    openRemovalModal,
    openUninstallModal,
    closeRemovalModal,
    closeUninstallModal,
    openExtension,
    reloadPage,
    clearCacheAndReloadPage,
    showExtensionErrors,
    closeModalAndUninstallExtension,
    updateExtension,
    closeModalAndRemoveExtension,
    changeExtensionStatus,
    activateExtension,
    deactivateExtension,
    installExtension,
    installAndActivateExtension,
    removeExtension,
});
</script>
