<template>
    <ct-block name="ct_channel_menu">
        <div class="ct-channel-menu">
            <ct-block name="ct_channel_menu_modal">
                <ct-channel-modal v-if="showModal" @modal-close="showModal = false" />
            </ct-block>

            <ct-block name="ct_channel_menu_headline">
                <div class="ct-admin-menu__headline">
                    <div class="collapsible-text ct-admin-menu__headline_text hide-on-collapse">
                        <router-link :to="{ name: 'ct.channel.list' }">
                            {{ t('ct-channel.general.titleMenuItems') }}
                        </router-link>
                    </div>

                    <mt-dropdown-menu-root :open="contextMenuOpen" @update:open="contextMenuOpen = $event">
                        <mt-dropdown-menu-trigger as-child>
                            <mt-button
                                class="ct-admin-menu__headline-action"
                                variant="tertiary"
                                size="x-small"
                                square
                                :aria-label="t('ct-channel.general.manageChannels')"
                            >
                                <template #iconFront>
                                    <mt-icon name="solid-ellipsis-h-s" size="14px" />
                                </template>
                            </mt-button>
                        </mt-dropdown-menu-trigger>

                        <mt-dropdown-menu-portal>
                            <mt-action-menu
                                :side="isSidebarExpanded ? 'bottom' : 'right'"
                                :side-offset="isSidebarExpanded ? 4 : 12"
                            >
                                <router-link v-slot="{ navigate }" :to="{ name: 'ct.channel.list' }" custom>
                                    <mt-action-menu-item
                                        class="ct-admin-menu__headline-context-menu-manage-channels"
                                        icon="regular-pencil-s"
                                        @click="navigate"
                                    >
                                        {{ t('ct-channel.general.manageChannels') }}
                                    </mt-action-menu-item>
                                </router-link>

                                <mt-action-menu-item
                                    v-if="canCreateChannels"
                                    class="ct-admin-menu__headline-context-menu-add-channel"
                                    icon="regular-plus-circle"
                                    @click="openChannelModal"
                                >
                                    {{ t('ct-channel.general.addChannel') }}
                                </mt-action-menu-item>
                            </mt-action-menu>
                        </mt-dropdown-menu-portal>
                    </mt-dropdown-menu-root>
                </div>
            </ct-block>

            <ct-block name="ct_channel_menu_navigation">
                <nav class="ct-admin-menu__navigation">
                    <ul class="ct-admin-menu__navigation-list">
                        <ct-block name="ct_channel_menu_navigation_main_items">
                            <ct-admin-menu-item
                                v-for="(entry, index) in buildMenuTree"
                                :key="entry.id || index"
                                class="ct-admin-menu__channel-item"
                                :class="[`ct-admin-menu__channel-item--${index}`]"
                                :entry="entry"
                                :sidebar-expanded="isSidebarExpanded"
                                icon-size="16px"
                            >
                                <template #additional-text>
                                    <mt-button
                                        v-if="entry.domainLink && entry.active"
                                        class="ct-channel-menu-domain-link"
                                        variant="tertiary"
                                        size="x-small"
                                        square
                                        :aria-label="t('ct-channel.general.tooltipOpenFrontend')"
                                        :title="t('ct-channel.general.tooltipOpenFrontend')"
                                        @click.prevent="openFrontendLink(entry.domainLink)"
                                    >
                                        <template #iconFront>
                                            <mt-icon
                                                class="ct-channel-menu-domain-link__icon hide-on-collapse"
                                                name="regular-eye"
                                                size="16px"
                                            />
                                        </template>
                                    </mt-button>
                                </template>
                            </ct-admin-menu-item>
                        </ct-block>

                        <ct-block name="ct_channel_menu_navigation_more_items">
                            <ct-admin-menu-item
                                v-if="moreChannelsAvailable"
                                :entry="moreItemsEntry"
                                :sidebar-expanded="isSidebarExpanded"
                                :show-active-state="false"
                                class="ct-admin-menu__channel-more-items"
                                icon-size="16px"
                            />
                        </ct-block>

                        <ct-block name="ct_channel_menu_navigation_add_channel">
                            <li
                                v-if="showAddChannelMenuItem"
                                class="ct-admin-menu__navigation-list-item ct-channel-menu__add-channel"
                            >
                                <div class="ct-admin-menu__navigation-item-row">
                                    <button
                                        type="button"
                                        class="ct-admin-menu__navigation-link"
                                        :aria-label="t('ct-channel.general.addChannel')"
                                        @click="openChannelModal"
                                    >
                                        <mt-icon
                                            name="regular-plus-circle"
                                            size="16px"
                                            class="ct-admin-menu__navigation-link-icon"
                                        />
                                        <span class="ct-admin-menu__navigation-link-label collapsible-text hide-on-collapse">
                                            {{ t('ct-channel.general.addChannel') }}
                                        </span>
                                    </button>
                                </div>
                            </li>
                        </ct-block>
                    </ul>
                </nav>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import { computed, getCurrentInstance, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import type AclService from 'src/app/service/acl.service';

import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { getDomainLink } from 'src/module/ct-channel/service/domain-link.service';
import './ct-channel-menu.scss';

type ChannelMenuEntry = {
    id?: string;
    path: string;
    params?: { id: string };
    label: string | { label: string; translated: boolean };
    icon: string;
    children: ChannelMenuEntry[];
    domainLink?: string | null;
    active?: boolean;
};
type DeviceHelper = {
    getMediaQuery: (_query: string) => MediaQueryList;
};

defineProps({});
const { t } = useI18n();
const route = useRoute();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const instance = getCurrentInstance();
const device = instance?.appContext.config.globalProperties.$device as DeviceHelper | undefined;
if (!repositoryFactory || !acl || !device) {
    throw new Error('The Channel menu services are unavailable.');
}

const channels = ref<EntityCollection<'channel'> | Entity<'channel'>[]>([]);
const channelsLoaded = ref(false);
const showModal = ref(false);
const isLoading = ref(true);
const isMobileViewport = ref(false);
const contextMenuOpen = ref(false);
const mobileViewportQuery = ref<MediaQueryList | null>(null);
const adminMenuStore = computed(() => Contena.Store.get('adminMenu'));
const isSidebarExpanded = computed(() => adminMenuStore.value.isExpanded || isMobileViewport.value);
const channelRepository = computed(() => repositoryFactory.create('channel'));
const canCreateChannels = computed(() => acl.can('channel.creator'));
const channelFavoritesService = computed(() => Contena.Service('channelFavorites'));
const channelFavorites = computed(() => channelFavoritesService.value.getFavoriteIds());
const showAddChannelMenuItem = computed(() => {
    return (
        channelsLoaded.value && channels.value.length === 0 && channelFavorites.value.length === 0 && canCreateChannels.value
    );
});
const channelCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 7);

    criteria.addIncludes({
        channel: [
            'name',
            'type',
            'active',
            'translated',
            'domains',
        ],
        channel_type: ['iconName'],
        channel_domain: [
            'url',
            'languageId',
        ],
    });
    criteria.addSorting(Contena.Data.Criteria.sort('channel.name', 'ASC'));
    criteria.addAssociation('type');
    criteria.addAssociation('domains');

    if (channelFavorites.value.length > 0) {
        criteria.setLimit(50);
        criteria.addFilter(Contena.Data.Criteria.equalsAny('id', channelFavorites.value));
    }

    return criteria;
});
const moreChannelsAvailable = computed(() => {
    const total = 'total' in channels.value ? channels.value.total : channels.value.length;

    return total > channels.value.length;
});
const buildMenuTree = computed<ChannelMenuEntry[]>(() => {
    const flatTree = new Contena.Helper.FlatTreeHelper();

    channels.value.forEach((channel) => {
        flatTree.add({
            id: channel.id,
            path: 'ct.channel.detail',
            params: { id: channel.id },
            label: {
                label: channel.translated?.name ?? channel.name ?? '',
                translated: true,
            },
            icon: channel.type?.iconName ?? 'regular-server',
            children: [],
            domainLink: getDomainLink(channel),
            active: channel.active,
        });
    });

    return flatTree.convertToTree() as ChannelMenuEntry[];
});
const moreItemsEntry = computed<ChannelMenuEntry>(() => ({
    path: 'ct.channel.list',
    label: t('ct-channel.general.titleMenuMoreItems'),
    icon: 'regular-eye',
    children: [],
}));

const syncMobileViewport = (): void => {
    isMobileViewport.value = mobileViewportQuery.value?.matches ?? false;
};
const registerListener = (): void => {
    Contena.Utils.EventBus.on('ct-channel-detail-channel-change', onChannelChange);
    Contena.Utils.EventBus.on('ct-language-switch-change-application-language', onLanguageChange);
    Contena.Utils.EventBus.on('ct-channel-detail-base-channel-change', onChannelBaseChange);
    Contena.Utils.EventBus.on('ct-channel-list-add-new-channel', onAddChannel);
};
const unregisterListener = (): void => {
    Contena.Utils.EventBus.off('ct-channel-detail-channel-change', onChannelChange);
    Contena.Utils.EventBus.off('ct-language-switch-change-application-language', onLanguageChange);
    Contena.Utils.EventBus.off('ct-channel-detail-base-channel-change', onChannelBaseChange);
    Contena.Utils.EventBus.off('ct-channel-list-add-new-channel', onAddChannel);
};
const loadEntityData = async (): Promise<void> => {
    channels.value = await channelRepository.value.search(channelCriteria.value, Contena.Context.api);
    channelsLoaded.value = true;
};
const openChannelModal = (): void => {
    showModal.value = true;
};
const onChannelChange = (): void => {
    void loadEntityData();
};
const onLanguageChange = (): void => {
    void loadEntityData();
};
const onChannelBaseChange = (): void => {
    openChannelModal();
};
const onAddChannel = (): void => {
    openChannelModal();
};
const openFrontendLink = (frontendLink: string): void => {
    window.open(frontendLink, '_blank');
};

watch(channelFavorites, () => {
    if (!isLoading.value) {
        void loadEntityData();
    }
});
watch(
    () => route.path,
    () => {
        contextMenuOpen.value = false;
    },
);
watch(isMobileViewport, (isMobile) => {
    if (isMobile) {
        contextMenuOpen.value = false;
    }
});

onMounted(() => {
    mobileViewportQuery.value = device.getMediaQuery('(max-width: 1280px)');
    mobileViewportQuery.value.addEventListener('change', syncMobileViewport);
    syncMobileViewport();
    registerListener();

    void channelFavoritesService.value.initService().finally(() => {
        isLoading.value = false;
        void loadEntityData();
    });
});
onBeforeUnmount(() => {
    mobileViewportQuery.value?.removeEventListener('change', syncMobileViewport);
    unregisterListener();
});

ctDefinePublic({
    channels,
    channelsLoaded,
    showModal,
    isLoading,
    isMobileViewport,
    contextMenuOpen,
    mobileViewportQuery,
    adminMenuStore,
    isSidebarExpanded,
    channelRepository,
    canCreateChannels,
    showAddChannelMenuItem,
    channelCriteria,
    moreChannelsAvailable,
    buildMenuTree,
    moreItemsEntry,
    channelFavoritesService,
    channelFavorites,
    syncMobileViewport,
    registerListener,
    unregisterListener,
    loadEntityData,
    openChannelModal,
    openFrontendLink,
});

defineExpose({
    channels,
    channelsLoaded,
    showModal,
    isLoading,
    isMobileViewport,
    contextMenuOpen,
    mobileViewportQuery,
    adminMenuStore,
    isSidebarExpanded,
    channelRepository,
    canCreateChannels,
    showAddChannelMenuItem,
    channelCriteria,
    moreChannelsAvailable,
    buildMenuTree,
    moreItemsEntry,
    channelFavoritesService,
    channelFavorites,
    syncMobileViewport,
    registerListener,
    unregisterListener,
    loadEntityData,
    openChannelModal,
    openFrontendLink,
});
</script>
