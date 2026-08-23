<template>
    <ct-block name="sw_settings_cache_modal">
        <mt-modal-root :is-open="open" @change="onModalChange">
            <mt-modal :title="$t('ct-settings-cache.modal.title')" width="s">
                <ct-block name="sw_settings_cache_modal_content">
                    {{ $t('ct-settings-cache.modal.message') }}
                </ct-block>

                <template #footer>
                    <ct-block name="sw_settings_cache_modal_footer">
                        <div class="ct-settings-cache-modal__actions">
                            <mt-button size="small" variant="secondary" @click="closeModal">
                                {{ $t('global.default.cancel') }}
                            </mt-button>

                            <mt-button ref="button" size="small" variant="primary" @click="clearCache">
                                {{ $t('ct-settings-cache.modal.actions.clear') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { inject, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import type AclService from 'src/app/service/acl.service';

type CacheApiService = {
    clear: () => Promise<void>;
};

type ButtonInstance = {
    $el?: HTMLElement;
};

defineProps({});
const cacheApiService = inject<CacheApiService>('cacheApiService');
const acl = inject<AclService>('acl');
const { t } = useI18n();
const { createNotificationError, createNotificationInfo, createNotificationSuccess } = useNotification();

if (!cacheApiService || !acl) {
    throw new Error('ct-settings-cache-modal requires cacheApiService and acl');
}

const open = ref(false);
const button = ref<ButtonInstance | null>(null);

const keydownEventListener = (event: KeyboardEvent): void => {
    if (event.key === 'Alt' || (event.key === 'c' && event.altKey)) {
        event.preventDefault();
    }
};

const openModal = async (): Promise<void> => {
    if (!acl.can('system.clear_cache')) {
        return;
    }

    open.value = true;
    await nextTick();
    button.value?.$el?.focus();
};

const closeModal = (): void => {
    open.value = false;
};

const clearCache = (): void => {
    createNotificationInfo({
        message: t('ct-settings-cache.notifications.clearCache.started'),
    });

    void cacheApiService
        .clear()
        .then(() => {
            createNotificationSuccess({
                message: t('ct-settings-cache.notifications.clearCache.success'),
            });
        })
        .catch(() => {
            createNotificationError({
                message: t('ct-settings-cache.notifications.clearCache.error'),
            });
        });

    closeModal();
};

const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) {
        closeModal();
    }
};

swDefinePublic({
    open,
    keydownEventListener,
    openModal,
    closeModal,
    clearCache,
    onModalChange,
});

onMounted(() => document.addEventListener('keydown', keydownEventListener.value));
onBeforeUnmount(() => document.removeEventListener('keydown', keydownEventListener.value));

defineExpose({ open, keydownEventListener, openModal, closeModal, clearCache, onModalChange });
</script>

<style scoped>
.ct-settings-cache-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--scale-size-12, 12px);
}
</style>
