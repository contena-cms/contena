<template>
    <!-- eslint-disable vue/no-mutating-props -->
    <ct-block name="sw_channel_detail_base">
        <div class="ct-channel-detail-base">
            <ct-block name="sw_channel_detail_base_general">
                <mt-card
                    position-identifier="ct-channel-detail-base-general"
                    :title="t('ct-channel.detail.titleGeneral')"
                    :is-loading="isLoading"
                >
                    <ct-block name="sw_channel_detail_base_general_input_name">
                        <mt-text-field
                            v-model="channel.name"
                            validation="required"
                            required
                            :label="t('ct-channel.detail.labelName')"
                            :placeholder="t('ct-channel.detail.placeholderName')"
                            :disabled="disableEdit || undefined"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_favorite">
                        <mt-switch
                            :disabled="!canManageFavorites || channel._isNew || undefined"
                            :label="t('ct-channel.detail.favouriteLabel')"
                            :model-value="isFavorite()"
                            @update:model-value="(favorite) => channelFavoritesService.update(favorite, channel.id)"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_navigation_category_container">
                        <div class="ct-channel-detail-base__navigation-category">
                            <ct-category-tree-field
                                id="navigationCategoryId"
                                required
                                :categories-collection="mainCategories"
                                :placeholder="navigationCategoryPlaceholder"
                                :single-select="true"
                                :label="t('ct-channel.detail.labelNavigationCategory')"
                                :disabled="disableEdit || undefined"
                                :help-text="t('ct-channel.detail.navigationCategoryHelpText')"
                                class="ct-channel-detail__select-navigation-category-id"
                                @selection-add="onMainSelectionAdd"
                                @selection-remove="onMainSelectionRemove"
                            />

                            <mt-number-field
                                v-model="channel.navigationCategoryDepth"
                                number-type="int"
                                :min="1"
                                :disabled="disableEdit || undefined"
                                :label="t('ct-channel.detail.labelNavigationDepth')"
                            />
                        </div>
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_footer_category">
                        <ct-category-tree-field
                            :categories-collection="footerCategories"
                            :placeholder="footerCategoryPlaceholder"
                            :single-select="true"
                            :label="t('ct-channel.detail.labelFooterCategory')"
                            :disabled="disableEdit || undefined"
                            class="ct-channel-detail__select-footer-category-id"
                            @selection-add="onFooterSelectionAdd"
                            @selection-remove="onFooterSelectionRemove"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_service_category">
                        <ct-category-tree-field
                            :categories-collection="serviceCategories"
                            :placeholder="serviceCategoryPlaceholder"
                            :single-select="true"
                            :label="t('ct-channel.detail.labelServiceCategory')"
                            :disabled="disableEdit || undefined"
                            class="ct-channel-detail__select-service-category-id"
                            @selection-add="onServiceSelectionAdd"
                            @selection-remove="onServiceSelectionRemove"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_member_group">
                        <mt-entity-select
                            v-model="channel.memberGroupId"
                            entity="member_group"
                            required
                            :label="t('ct-channel.detail.labelMemberGroup')"
                            :disabled="disableEdit || undefined"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_countries">
                        <ct-channel-defaults-select
                            :channel="channel"
                            :criteria="countryCriteria"
                            property-name="countries"
                            :property-label="t('ct-channel.detail.labelCountries')"
                            :help-text="t('ct-channel.detail.countryMultiSelectHelpText')"
                            default-property-name="countryId"
                            :default-property-label="t('ct-channel.detail.labelCountry')"
                            :disabled="disableEdit || undefined"
                            :disabled-tooltip-message="t('ct-channel.detail.tooltipDisabledCountry')"
                            should-show-active-state
                        />
                    </ct-block>

                    <mt-banner
                        v-if="unservedLanguages.length > 0"
                        :variant="unservedLanguageVariant"
                        :title="t(`global.default.${unservedLanguageVariant}`)"
                    >
                        <div class="ct-channel-detail-base__unserved-language-banner">
                            <span class="ct-channel-detail-base__unserved-language-message">
                                {{ unservedLanguageMessage }}
                            </span>

                            <mt-button
                                v-if="isDomainAware"
                                class="ct-channel-detail-base__unserved-language-domain-action"
                                size="small"
                                variant="secondary"
                                :disabled="disableEdit || undefined"
                                @click="onClickCreateDomainForUnservedLanguage"
                            >
                                {{ t('ct-channel.detail.buttonAddDomain') }}
                            </mt-button>
                        </div>
                    </mt-banner>

                    <ct-block name="sw_channel_detail_base_general_input_languages">
                        <ct-channel-defaults-select
                            :channel="channel"
                            :criteria="languageCriteria"
                            property-name="languages"
                            :property-label="t('ct-channel.detail.labelLanguages')"
                            default-property-name="languageId"
                            :default-property-label="t('ct-channel.detail.labelLanguage')"
                            property-name-in-domain="languageId"
                            :disabled="disableEdit || undefined"
                        />
                    </ct-block>

                    <ct-block name="sw_channel_detail_base_general_input_business_time_zone">
                        <mt-select
                            v-model="channel.businessTimeZone"
                            :options="timezoneOptions"
                            :label="t('ct-channel.detail.labelBusinessTimeZone')"
                            :help-text="t('ct-channel.detail.helpTextBusinessTimeZone')"
                            :disabled="disableEdit || undefined"
                        />
                    </ct-block>
                </mt-card>
            </ct-block>

            <ct-block name="sw_channel_detail_base_options_api">
                <mt-card
                    position-identifier="ct-channel-detail-base-options-api"
                    :title="t('ct-channel.detail.titleOptionsApiKey')"
                    :is-loading="isLoading"
                >
                    <div class="ct-channel-detail-base__description-text">
                        {{ t('ct-channel.detail.textApiAccessDescription') }}
                    </div>

                    <mt-text-field
                        v-model="channel.accessKey"
                        :label="t('ct-channel.detail.labelAccessKey')"
                        :disabled="true"
                    />

                    <div class="ct-channel-detail__action-buttons">
                        <mt-button
                            size="small"
                            :disabled="disableEdit || undefined"
                            class="ct-channel-detail-base__button-generate-keys"
                            variant="secondary"
                            @click="onGenerateKey"
                        >
                            {{ t('ct-channel.detail.buttonGenerateKey') }}
                        </mt-button>

                        <mt-button
                            size="small"
                            class="ct-channel-detail-base__button-copy-key"
                            variant="secondary"
                            @click="copyToClipboard"
                        >
                            {{ t('ct-channel.detail.buttonCopyApiKey') }}
                        </mt-button>
                    </div>
                </mt-card>
            </ct-block>

            <ct-block name="sw_channel_detail_base_options_status">
                <mt-card
                    position-identifier="ct-channel-detail-base-options-status"
                    :title="t('ct-channel.detail.titleStatus')"
                    :is-loading="isLoading"
                >
                    <div class="ct-channel-detail-base__description-text">
                        {{ t('ct-channel.detail.textActiveDescription') }}
                    </div>

                    <mt-switch
                        v-model="channel.active"
                        :label="t('ct-channel.detail.labelActive')"
                        :disabled="disableEdit || undefined"
                    />

                    <h4 class="ct-channel-detail-base__headline">
                        <span class="ct-channel-detail-base__headline-text">
                            {{ t('ct-channel.detail.maintenanceModeTitle') }}
                        </span>
                    </h4>

                    <div class="ct-channel-detail-base__description-text">
                        {{ t('ct-channel.detail.maintenanceModeDescription') }}
                    </div>

                    <mt-switch
                        v-model="channel.maintenance"
                        name="ct-field--channel-maintenance"
                        :disabled="disableEdit || undefined"
                        :label="t('ct-channel.detail.labelMaintenance')"
                    />

                    <ct-multi-tag-ip-select
                        v-model:value="maintenanceIpAllowlist"
                        :is-loading="isLoading"
                        :disabled="disableEdit || undefined"
                        :label="t('ct-channel.detail.labelMaintenanceIps')"
                        :help-text="t('ct-channel.detail.ipAddressAllowlistHelpText')"
                        :known-ips="knownIps"
                        :validate="validateMaintenanceIpCidr"
                        error-code="CONTENA_INVALID_IP_CIDR"
                    />
                </mt-card>
            </ct-block>

            <ct-block name="sw_channel_detail_base_options_hreflang">
                <ct-channel-detail-hreflang
                    v-if="isWebChannel"
                    :channel="channel"
                    :disabled="disableEdit"
                    :is-loading="isLoading"
                />
            </ct-block>

            <ct-block name="sw_channel_detail_base_options_domains">
                <ct-channel-detail-domains
                    v-if="isDomainAware && channel.domains"
                    ref="channelDomains"
                    :channel="channel"
                    :disabled="disableEdit"
                    :is-loading="isLoading"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type ChannelApiService from 'src/core/service/api/channel.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-channel-detail-base.scss';

type TimezoneOption = { label: string; value: string };
type KnownIp = { name: string; value: string };
type KnownIpsService = { getKnownIps: () => Promise<KnownIp[]> };

type ChannelDomainsComponent = { openCreateModal: (_defaults?: Partial<Entity<'channel_domain'>>) => void };

const props = defineProps({
    channel: { type: Object as PropType<Entity<'channel'>>, required: true },
    isLoading: { type: Boolean, default: false },
    createMode: { type: Boolean, default: false },
});
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const acl = inject<AclService>('acl');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const channelService = inject<ChannelApiService>('channelService');
const knownIpsService = inject<KnownIpsService>('knownIpsService');
if (!acl || !repositoryFactory || !channelService || !knownIpsService) {
    throw new Error('The Channel base services are unavailable.');
}

const disableEdit = computed(() => (props.createMode ? !acl.can('channel.creator') : !acl.can('channel.editor')));
const canManageFavorites = computed(() => acl.can('user_config:create') && acl.can('user_config:update'));
const channelFavoritesService = computed(() => Contena.Service('channelFavorites'));
const channel = props.channel;
const timezoneOptions = ref<TimezoneOption[]>(Contena.Service('timezoneService').getTimezoneOptions());
const countryCriteria = computed(() => {
    return new Contena.Data.Criteria(1, 25).addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
});
const languageCriteria = computed(() => {
    return new Contena.Data.Criteria(1, 25)
        .addSorting(Contena.Data.Criteria.sort('name', 'ASC'))
        .addFilter(Contena.Data.Criteria.equals('active', true));
});
const isWebChannel = computed(() => props.channel.typeId === Contena.Defaults.webChannelTypeId);
const isDomainAware = computed(() => {
    return [
        Contena.Defaults.webChannelTypeId,
        Contena.Defaults.apiChannelTypeId,
    ].includes(props.channel.typeId);
});
const categoryRepository = computed(() => repositoryFactory.create('category'));
const createCategoryCollection = (): EntityCollection<'category'> => {
    return new Contena.Data.EntityCollection('/category', 'category', Contena.Context.api);
};
const mainCategories = ref<EntityCollection<'category'>>(createCategoryCollection());
const footerCategories = ref<EntityCollection<'category'>>(createCategoryCollection());
const serviceCategories = ref<EntityCollection<'category'>>(createCategoryCollection());
const categoryCriteria = (id: string | null | undefined) => {
    return new Contena.Data.Criteria(1, 25).addFilter(Contena.Data.Criteria.equals('id', id ?? null));
};
const navigationCategoryPlaceholder = computed(() => {
    return props.channel.navigationCategoryId ? '' : t('ct-category.base.link.categoryPlaceholder');
});
const footerCategoryPlaceholder = computed(() => {
    return props.channel.footerCategoryId ? '' : t('ct-category.base.link.categoryPlaceholder');
});
const serviceCategoryPlaceholder = computed(() => {
    return props.channel.serviceCategoryId ? '' : t('ct-category.base.link.categoryPlaceholder');
});
const unservedLanguages = computed(() => {
    return (
        props.channel.languages?.filter((language) => {
            return !(props.channel.domains ?? []).some((domain) => domain.languageId === language.id);
        }) ?? []
    );
});
const unservedLanguageVariant = computed<'attention' | 'info'>(() => {
    return unservedLanguages.value.some((language) => language.id === props.channel.languageId) ? 'attention' : 'info';
});
const unservedLanguageMessage = computed(() => {
    return t('ct-channel.detail.warningUnservedLanguage', {
        list: unservedLanguages.value.map((language) => language.translated?.name || language.name).join(', '),
    });
});
const primaryUnservedLanguage = computed(() => {
    return (
        unservedLanguages.value.find((language) => language.id === props.channel.languageId) ??
        unservedLanguages.value[0] ??
        null
    );
});
const knownIps = ref<KnownIp[]>([]);
const maintenanceIpAllowlist = computed<string[]>({
    get: () => props.channel.maintenanceIpAllowlist ?? [],
    set: (value) => {
        channel.maintenanceIpAllowlist = value;
    },
});
const channelDomains = ref<ChannelDomainsComponent | null>(null);

const createCategoryCollections = async (): Promise<void> => {
    [
        mainCategories.value,
        footerCategories.value,
        serviceCategories.value,
    ] = await Promise.all([
        categoryRepository.value.search(categoryCriteria(props.channel.navigationCategoryId), Contena.Context.api),
        categoryRepository.value.search(categoryCriteria(props.channel.footerCategoryId), Contena.Context.api),
        categoryRepository.value.search(categoryCriteria(props.channel.serviceCategoryId), Contena.Context.api),
    ]);
};
const onMainSelectionAdd = (category: Entity<'category'>): void => {
    channel.navigationCategoryId = category.id;
};
const onMainSelectionRemove = (): void => {
    channel.navigationCategoryId = null;
};
const onFooterSelectionAdd = (category: Entity<'category'>): void => {
    channel.footerCategoryId = category.id;
};
const onFooterSelectionRemove = (): void => {
    channel.footerCategoryId = null;
};
const onServiceSelectionAdd = (category: Entity<'category'>): void => {
    channel.serviceCategoryId = category.id;
};
const onServiceSelectionRemove = (): void => {
    channel.serviceCategoryId = null;
};
const onGenerateKey = async (): Promise<void> => {
    try {
        const response = await channelService.generateKey();
        channel.accessKey = response.accessKey;
    } catch {
        createNotificationError({ message: t('ct-channel.detail.messageApiError') });
    }
};
const copyToClipboard = async (): Promise<void> => {
    try {
        await Contena.Utils.dom.copyStringToClipboard(props.channel.accessKey);
        createNotificationSuccess({ message: t('global.ct-field.notification.notificationCopySuccessMessage') });
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-field.notification.notificationCopyFailureMessage'),
        });
    }
};
const validateMaintenanceIpCidr = (term: string): boolean => {
    return Contena.Utils.string.isValidIp(term) || Contena.Utils.string.isValidCidr(term);
};
const onClickCreateDomainForUnservedLanguage = (): void => {
    channelDomains.value?.openCreateModal({ languageId: primaryUnservedLanguage.value?.id });
};
const isFavorite = (): boolean => channelFavoritesService.value.isFavorite(props.channel.id);

watch(
    () => props.channel,
    () => void createCategoryCollections(),
    { immediate: true },
);
onMounted(() => {
    void knownIpsService.getKnownIps().then((ips) => {
        knownIps.value = ips;
    });
});

swDefinePublic({
    disableEdit,
    canManageFavorites,
    channelFavoritesService,
    timezoneOptions,
    countryCriteria,
    languageCriteria,
    isWebChannel,
    isDomainAware,
    categoryRepository,
    mainCategories,
    footerCategories,
    serviceCategories,
    navigationCategoryPlaceholder,
    footerCategoryPlaceholder,
    serviceCategoryPlaceholder,
    unservedLanguages,
    unservedLanguageVariant,
    unservedLanguageMessage,
    primaryUnservedLanguage,
    knownIps,
    maintenanceIpAllowlist,
    channelDomains,
    createCategoryCollections,
    onMainSelectionAdd,
    onMainSelectionRemove,
    onFooterSelectionAdd,
    onFooterSelectionRemove,
    onServiceSelectionAdd,
    onServiceSelectionRemove,
    onGenerateKey,
    copyToClipboard,
    validateMaintenanceIpCidr,
    onClickCreateDomainForUnservedLanguage,
    isFavorite,
});

defineExpose({
    disableEdit,
    countryCriteria,
    languageCriteria,
    isWebChannel,
    isDomainAware,
    mainCategories,
    footerCategories,
    serviceCategories,
    unservedLanguages,
    unservedLanguageVariant,
    unservedLanguageMessage,
    maintenanceIpAllowlist,
    createCategoryCollections,
    onMainSelectionAdd,
    onMainSelectionRemove,
    onFooterSelectionAdd,
    onFooterSelectionRemove,
    onServiceSelectionAdd,
    onServiceSelectionRemove,
    onGenerateKey,
    copyToClipboard,
    validateMaintenanceIpCidr,
    onClickCreateDomainForUnservedLanguage,
    isFavorite,
});
</script>
