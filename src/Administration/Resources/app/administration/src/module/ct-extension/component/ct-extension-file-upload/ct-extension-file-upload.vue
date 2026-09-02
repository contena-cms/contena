<template>
    <ct-block name="ct_extension_file_upload">
        <div class="ct-extension-file-upload">
            <ct-block name="ct_extension_file_upload_content">
                <div class="ct-extension-file-upload__content">
                    <ct-block name="ct_extension_file_upload_button">
                        <mt-button
                            class="ct-extension-file-upload__button"
                            :is-loading="isLoading"
                            variant="primary"
                            size="default"
                            @click="showConfirmModal"
                        >
                            {{ $t('ct-extension.my-extensions.fileUpload.buttonFileUpload') }}
                        </mt-button>
                    </ct-block>
                    <ct-block name="ct_extension_file_upload_form">
                        <form ref="fileForm" class="ct-extension-file-upload__form">
                            <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                            <input
                                id="files"
                                ref="fileInput"
                                class="ct-extension-file-upload__file-input"
                                type="file"
                                @change="onFileInputChange"
                            />
                        </form>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_extension_file_upload_confirm_modal">
                <ct-modal
                    v-if="confirmModalVisible"
                    class="ct-extension-file-upload-confirm-modal"
                    :title="$t('global.default.warning')"
                    variant="small"
                    @modal-close="closeConfirmModal"
                >
                    <template #default>
                        <ct-block name="ct_extension_file_upload_confirm_modal_body">
                            <p>
                                {{ $t('ct-extension.my-extensions.fileUpload.descriptionWarningModal') }}
                            </p>
                        </ct-block>
                    </template>
                    <template #modal-footer>
                        <ct-block name="ct_extension_file_upload_confirm_modal_footer">
                            <ct-block name="ct_extension_file_upload_confirm_modal_footer_checkbox">
                                <mt-checkbox
                                    v-model:checked="shouldHideConfirmModal"
                                    :label="$t('ct-extension.my-extensions.fileUpload.textHideWarning')"
                                />
                            </ct-block>

                            <ct-block name="ct_extension_file_upload_confirm_modal_footer_buttons">
                                <div class="ct-extension-file-upload-confirm-modal__actions">
                                    <ct-block name="ct_extension_file_upload_confirm_modal_footer_cancel">
                                        <mt-button
                                            size="small"
                                            :disabled="isLoading"
                                            variant="secondary"
                                            @click="closeConfirmModal"
                                        >
                                            {{ $t('global.default.cancel') }}
                                        </mt-button>
                                    </ct-block>

                                    <ct-block name="ct_extension_file_upload_confirm_modal_footer_continue">
                                        <mt-button
                                            variant="primary"
                                            size="small"
                                            :is-loading="isLoading"
                                            @click="onClickUpload"
                                        >
                                            {{ $t('global.default.confirm') }}
                                        </mt-button>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </ct-block>
                    </template>
                </ct-modal>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-extension-file-upload.scss';
import pluginErrorHandler from '../../service/extension-error-handler.service';
const { Criteria } = Contena.Data;
const USER_CONFIG_KEY = 'extension.plugin_upload';

defineProps({});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const fileInput = ref(null);
const fileForm = ref(null);

const extensionStoreActionService = inject('extensionStoreActionService');
const repositoryFactory = inject('repositoryFactory');

const isLoading = ref(true);
const confirmModalVisible = ref(false);
const shouldHideConfirmModal = ref(false);
const pluginUploadUserConfig = ref(null);

const userConfigRepository = computed(() => {
    return repositoryFactory.create('user_config');
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const userConfigCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    criteria.addFilter(Criteria.equals('key', USER_CONFIG_KEY));
    criteria.addFilter(Criteria.equals('userId', currentUser.value?.id));

    return criteria;
});

const createdComponent = async () => {
    await getUserConfig();
    isLoading.value = false;
};
const onClickUpload = () => {
    fileInput.value.click();
};
const onFileInputChange = () => {
    const newFiles = Array.from(fileInput.value.files);
    handleUpload(newFiles);
    fileForm.value.reset();
};
const handleUpload = (files) => {
    isLoading.value = true;
    const formData = new FormData();
    formData.append('file', files[0]);

    return extensionStoreActionService
        .upload(formData)
        .then(() => {
            void Contena.Service('contenaExtensionService')
                .updateExtensionData()
                .then(() => {
                    return createNotificationSuccess({
                        message: t('ct-extension.my-extensions.fileUpload.messageUploadSuccess'),
                    });
                });
        })
        .catch((exception) => {
            const mappedErrors = pluginErrorHandler.mapErrors(exception.response.data.errors);
            mappedErrors.forEach((error) => {
                const message = [
                    t(error.message),
                    error.details,
                ]
                    .filter(Boolean)
                    .join('<br />');

                createNotificationError({
                    message: message,
                });
            });
        })
        .finally(() => {
            isLoading.value = false;
            confirmModalVisible.value = false;

            if (shouldHideConfirmModal.value === true) {
                saveConfig(true);
            }
        });
};
const showConfirmModal = () => {
    if (pluginUploadUserConfig.value.value.hide_upload_warning === true) {
        onClickUpload();
        return;
    }

    confirmModalVisible.value = true;
};
const closeConfirmModal = () => {
    confirmModalVisible.value = false;
};
const getUserConfig = () => {
    return userConfigRepository.value.search(userConfigCriteria.value, Contena.Context.api).then((response) => {
        if (response.length) {
            pluginUploadUserConfig.value = response.first();
        } else {
            pluginUploadUserConfig.value = userConfigRepository.value.create(Contena.Context.api);
            pluginUploadUserConfig.value.key = USER_CONFIG_KEY;
            pluginUploadUserConfig.value.userId = currentUser.value?.id;
            pluginUploadUserConfig.value.value = {
                hide_upload_warning: false,
            };
        }
    });
};
const saveConfig = (value) => {
    pluginUploadUserConfig.value.value = {
        hide_upload_warning: value,
    };

    userConfigRepository.value.save(pluginUploadUserConfig.value, Contena.Context.api).then(() => {
        getUserConfig();
    });
};

void createdComponent();

ctDefinePublic({
    extensionStoreActionService,
    repositoryFactory,
    isLoading,
    confirmModalVisible,
    shouldHideConfirmModal,
    pluginUploadUserConfig,
    userConfigRepository,
    currentUser,
    userConfigCriteria,
    createdComponent,
    onClickUpload,
    onFileInputChange,
    handleUpload,
    showConfirmModal,
    closeConfirmModal,
    getUserConfig,
    saveConfig,
});

defineExpose({
    extensionStoreActionService,
    repositoryFactory,
    isLoading,
    confirmModalVisible,
    shouldHideConfirmModal,
    pluginUploadUserConfig,
    userConfigRepository,
    currentUser,
    userConfigCriteria,
    createdComponent,
    onClickUpload,
    onFileInputChange,
    handleUpload,
    showConfirmModal,
    closeConfirmModal,
    getUserConfig,
    saveConfig,
});
</script>
