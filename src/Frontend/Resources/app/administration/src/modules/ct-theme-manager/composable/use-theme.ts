import { computed, inject, ref, type ComputedRef, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type Repository from 'src/core/data/repository.data';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useNotification } from 'src/app/composables/use-notification';

interface UseThemeOptions {
    isLoading: Ref<boolean>;
    getList?: () => unknown;
}

/** @private */
export type ThemeEntity = Omit<Entity<'theme'>, 'previewMediaId' | 'previewMedia' | 'channels'> & {
    previewMediaId?: string | null;
    previewMedia?: Entity<'media'> | null;
    channels?: EntityCollection<'channel'>;
};

interface UseThemeReturn {
    showDeleteModal: Ref<boolean>;
    showMediaModal: Ref<boolean>;
    showRenameModal: Ref<boolean>;
    showDuplicateModal: Ref<boolean>;
    newThemeName: Ref<string>;
    modalTheme: Ref<ThemeEntity | null>;
    themeRepository: ComputedRef<Repository<'theme'>>;
    onDeleteTheme: (theme: ThemeEntity) => void;
    onCloseDeleteModal: () => void;
    onConfirmThemeDelete: () => void;
    deleteTheme: (theme: ThemeEntity) => void;
    onDuplicateTheme: (theme: ThemeEntity) => void;
    onCloseDuplicateModal: () => void;
    onConfirmThemeDuplicate: () => void;
    duplicateTheme: (parentTheme: ThemeEntity, name: string) => void;
    onRenameTheme: (theme: ThemeEntity) => void;
    onCloseRenameModal: () => void;
    onConfirmThemeRename: () => void;
    RenameTheme: (theme: ThemeEntity, name: string) => void;
}

/**
 * Shared theme lifecycle implementation used by Theme SFCs.
 *
 * @private
 */
export function useTheme({ isLoading, getList }: UseThemeOptions): UseThemeReturn {
    const injectedRepositoryFactory = inject<RepositoryFactory>('repositoryFactory');
    const injectedAcl = inject<AclService>('acl');
    if (!injectedRepositoryFactory || !injectedAcl) {
        throw new Error('The repositoryFactory and acl services are required.');
    }

    const repositoryFactory = injectedRepositoryFactory;
    const acl = injectedAcl;

    const router = useRouter();
    const { t } = useI18n();
    const { createNotificationError } = useNotification();
    const showDeleteModal = ref(false);
    const showMediaModal = ref(false);
    const showRenameModal = ref(false);
    const showDuplicateModal = ref(false);
    const newThemeName = ref('');
    const modalTheme = ref<ThemeEntity | null>(null);
    const themeRepository = computed(() => repositoryFactory.create('theme'));

    function onDeleteTheme(theme: ThemeEntity): void {
        if (!acl.can('theme.deleter')) {
            return;
        }

        modalTheme.value = theme;
        showDeleteModal.value = true;
    }

    function onCloseDeleteModal(): void {
        showDeleteModal.value = false;
        modalTheme.value = null;
    }

    function onConfirmThemeDelete(): void {
        deleteTheme(modalTheme.value as ThemeEntity);

        showDeleteModal.value = false;
        modalTheme.value = null;
    }

    function deleteTheme(theme: ThemeEntity): void {
        const titleDeleteError = t('ct-theme-manager.components.themeListItem.notificationDeleteErrorTitle');
        const messageDeleteError = t('ct-theme-manager.components.themeListItem.notificationDeleteErrorMessage');

        isLoading.value = true;
        void themeRepository.value
            .delete(theme.id, Contena.Context.api)
            .then(() => {
                if (getList) {
                    getList();
                    return;
                }

                void router.push({ name: 'ct.theme.manager.index' });
            })
            .catch(() => {
                isLoading.value = false;
                createNotificationError({
                    title: titleDeleteError,
                    message: messageDeleteError,
                });
            });
    }

    function onDuplicateTheme(theme: ThemeEntity): void {
        if (!acl.can('theme.creator')) {
            return;
        }

        modalTheme.value = theme;
        showDuplicateModal.value = true;
    }

    function onCloseDuplicateModal(): void {
        showDuplicateModal.value = false;
        modalTheme.value = null;
        newThemeName.value = '';
    }

    function onConfirmThemeDuplicate(): void {
        duplicateTheme(modalTheme.value as ThemeEntity, newThemeName.value);

        showDuplicateModal.value = false;
        modalTheme.value = null;
        newThemeName.value = '';
    }

    function duplicateTheme(parentTheme: ThemeEntity, name: string): void {
        const themeDuplicate = themeRepository.value.create(Contena.Context.api) as ThemeEntity;

        themeDuplicate.name = name;
        themeDuplicate.parentThemeId = parentTheme.id;
        themeDuplicate.author = parentTheme.author;
        themeDuplicate.description = parentTheme.description;
        themeDuplicate.customFields = parentTheme.customFields;
        themeDuplicate.baseConfig = null;
        themeDuplicate.configValues = null;
        themeDuplicate.previewMediaId = parentTheme.previewMediaId;
        themeDuplicate.active = true;

        void themeRepository.value.save(themeDuplicate as Entity<'theme'>, Contena.Context.api).then(() => {
            void router.push({
                name: 'ct.theme.manager.detail',
                params: { id: themeDuplicate.id },
            });
        });
    }

    function onRenameTheme(theme: ThemeEntity): void {
        if (!acl.can('theme.editor')) {
            return;
        }

        modalTheme.value = theme;
        newThemeName.value = modalTheme.value.name;
        showRenameModal.value = true;
    }

    function onCloseRenameModal(): void {
        showRenameModal.value = false;
        modalTheme.value = null;
        newThemeName.value = '';
    }

    function onConfirmThemeRename(): void {
        RenameTheme(modalTheme.value as ThemeEntity, newThemeName.value);

        showRenameModal.value = false;
        modalTheme.value = null;
        newThemeName.value = '';
    }

    function RenameTheme(theme: ThemeEntity, name: string): void {
        if (name) {
            theme.name = name;
        }

        void themeRepository.value.save(theme as Entity<'theme'>, Contena.Context.api);
    }

    return {
        showDeleteModal,
        showMediaModal,
        showRenameModal,
        showDuplicateModal,
        newThemeName,
        modalTheme,
        themeRepository,
        onDeleteTheme,
        onCloseDeleteModal,
        onConfirmThemeDelete,
        deleteTheme,
        onDuplicateTheme,
        onCloseDuplicateModal,
        onConfirmThemeDuplicate,
        duplicateTheme,
        onRenameTheme,
        onCloseRenameModal,
        onConfirmThemeRename,
        RenameTheme,
    };
}
