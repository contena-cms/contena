<template>
    <ct-block name="sw_seo_url">
        <div class="ct-seo-url">
            <mt-card
                class="ct-seo-url__card"
                position-identifier="ct-seo-url"
                :title="$t('ct-seo-url.titleCard')"
                :is-loading="isLoading"
            >
                <template v-if="showEmptySeoUrlError">
                    {{ $t('ct-seo-url.textEmptySeoUrls') }}
                </template>

                <template v-else>
                    <ct-block name="sw_seo_url_card_seo_path">
                        <ct-inherit-wrapper
                            v-model:value="currentSeoUrl.seoPathInfo"
                            :has-parent="currentChannelId !== null && !isHeadlessChannel && hasDefaultTemplate"
                            :inherited-value="
                                currentSeoUrl.channelId !== null && !isHeadlessChannel ? defaultSeoUrl.seoPathInfo : null
                            "
                        >
                            <template #content="inheritanceProps">
                                <ct-block name="sw_seo_url_card_seo_path_edit">
                                    <mt-text-field
                                        :is-inheritance-field="inheritanceProps.isInheritField"
                                        :is-inherited="inheritanceProps.isInherited"
                                        :model-value="inheritanceProps.currentValue"
                                        :disabled="inheritanceProps.isInherited || isHeadlessChannel || !allowInput"
                                        :disable-inheritance-toggle="isHeadlessChannel"
                                        :label="$t('ct-seo-url.labelSeoPathInfo')"
                                        :help-text="seoUrlHelptext"
                                        :error="seoPathInfoError"
                                        @update:model-value="inheritanceProps.updateCurrentValue"
                                        @inheritance-restore="inheritanceProps.restoreInheritance"
                                        @inheritance-remove="inheritanceProps.removeInheritance"
                                    />
                                </ct-block>
                            </template>
                        </ct-inherit-wrapper>
                    </ct-block>
                </template>

                <template v-if="!showEmptySeoUrlError" #toolbar>
                    <ct-block name="sw_seo_url_card_toolbar">
                        <ct-channel-switch
                            ref="channelSwitch"
                            :disabled="disabled || undefined"
                            :label="$t('ct-seo-url.labelChannelSelect')"
                            @change-channel-id="onChannelChanged"
                        />
                    </ct-block>
                </template>

                <div v-if="hasAdditionalSeoSlot" class="ct-seo-url__card-seo-additional">
                    <slot name="seo-additional" v-bind="{ currentChannelId }">
                        <ct-block name="sw_seo_url_additional"></ct-block>
                    </slot>
                </div>
            </mt-card>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, onBeforeUnmount, ref, useSlots, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import './store';
import './ct-seo-url.scss';

const Criteria = Contena.Data.Criteria;
const EntityCollectionConstructor = Contena.Data.EntityCollection;
// eslint-disable-next-line no-control-regex
const DISALLOWED_SEO_PATH_CHARS = /%(?![0-9A-Fa-f]{2})|[#\\\x00-\x1F\x7F]/;

type SeoUrl = Entity<'seo_url'>;

const props = defineProps({
    channelId: {
        type: String,
        required: false,
        default: null,
    },

    urls: {
        type: Array as PropType<SeoUrl[]>,
        required: false,
        default() {
            return [];
        },
    },

    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },

    hasDefaultTemplate: {
        type: Boolean,
        required: false,
        default: true,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    resultLimit: {
        type: Number,
        required: false,
        default: 25,
    },
});
const emit = defineEmits<{
    'on-change-channel': [channelId: string | null];
}>();

const slots = useSlots();
const { t } = useI18n();

const $t = t;
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;

const currentChannelId = ref<string | null>(props.channelId);
const showEmptySeoUrlError = ref(false);

const seoUrlCollection = computed(() => {
    return Contena.Store.get('swSeoUrl').seoUrlCollection;
});
const currentSeoUrl = computed(() => {
    return Contena.Store.get('swSeoUrl').currentSeoUrl;
});
const defaultSeoUrl = computed(() => {
    return Contena.Store.get('swSeoUrl').defaultSeoUrl;
});
const seoUrlRepository = computed(() => {
    return repositoryFactory.create('seo_url');
});
const channelRepository = computed(() => {
    return repositoryFactory.create('channel');
});
const isHeadlessChannel = computed(() => {
    if (!Contena.Store.get('swSeoUrl')) {
        return true;
    }

    if (Contena.Store.get('swSeoUrl').channelCollection === null) {
        return true;
    }

    const channel = Contena.Store.get('swSeoUrl').channelCollection.find((entry) => {
        return entry.id === currentChannelId.value;
    });

    return currentChannelId.value !== null && channel?.typeId === Contena.Defaults.apiChannelTypeId;
});
const seoUrlHelptext = computed(() => {
    return isHeadlessChannel.value ? t('ct-seo-url.textSeoUrlsDisallowedForHeadless') : null;
});
const seoPathInfoError = computed(() => {
    const seoPathInfo = currentSeoUrl.value?.seoPathInfo;

    if (typeof seoPathInfo !== 'string' || seoPathInfo === '') {
        return null;
    }

    if (!DISALLOWED_SEO_PATH_CHARS.test(seoPathInfo)) {
        return null;
    }

    return {
        code: 'CONTENT__SEO_URL_INVALID_CHARACTERS',
        detail: t('ct-seo-url.errorInvalidCharacters'),
    };
});
const hasAdditionalSeoSlot = computed(() => {
    return Boolean(slots['seo-additional']);
});
const allowInput = computed(() => {
    return props.hasDefaultTemplate || currentChannelId.value !== null;
});

const createdComponent = (): void => {
    initChannelCollection();
    initSeoUrlCollection();
    if (!showEmptySeoUrlError.value) {
        refreshCurrentSeoUrl();
    }
};
const initChannelCollection = (): void => {
    const channelCriteria = new Criteria(1, props.resultLimit);
    channelCriteria.addAssociation('type');

    void channelRepository.value.search(channelCriteria).then((channelCollection) => {
        Contena.Store.get('swSeoUrl').channelCollection = channelCollection;
    });
};
const initSeoUrlCollection = (): void => {
    showEmptySeoUrlError.value = false;
    const seoUrlCollection = new EntityCollectionConstructor<'seo_url'>(
        seoUrlRepository.value.route,
        seoUrlRepository.value.schema.entity,
        Contena.Context.api,
        new Criteria(1, props.resultLimit),
    );

    const defaultSeoUrlData = props.urls.find((entityData) => {
        return entityData.channelId === null;
    });

    if (defaultSeoUrlData === undefined && (props.hasDefaultTemplate || props.urls.length <= 0)) {
        showEmptySeoUrlError.value = true;
    }

    const defaultSeoUrlEntity = seoUrlRepository.value.create();
    Object.assign(defaultSeoUrlEntity, defaultSeoUrlData);
    seoUrlCollection.add(defaultSeoUrlEntity);
    Contena.Store.get('swSeoUrl').defaultSeoUrl = defaultSeoUrlEntity;

    props.urls.forEach((entityData) => {
        const entity = seoUrlRepository.value.create();
        Object.assign(entity, entityData);

        seoUrlCollection.add(entity);
    });

    if (!Contena.Store.get('swSeoUrl').defaultSeoUrl) {
        showEmptySeoUrlError.value = true;
    }

    Contena.Store.get('swSeoUrl').seoUrlCollection = seoUrlCollection;
    Contena.Store.get('swSeoUrl').originalSeoUrls = props.urls;
    clearDefaultSeoUrls();
};
const clearDefaultSeoUrls = (): void => {
    seoUrlCollection.value?.forEach((entity) => {
        if (entity.id === defaultSeoUrl.value?.id) {
            return;
        }

        if (entity.seoPathInfo === defaultSeoUrl.value?.seoPathInfo) {
            entity.seoPathInfo = null;
        }
    });
};
const refreshCurrentSeoUrl = (): void => {
    const actualLanguageId = Contena.Context.api.languageId;

    const currentSeoUrl = seoUrlCollection.value?.find((entity) => {
        return entity.languageId === actualLanguageId && entity.channelId === currentChannelId.value;
    });

    if (!currentSeoUrl) {
        const entity = seoUrlRepository.value.create();
        // Fetch any seo url as template, since we need to know foreignKey, pathInfo and the routeName
        const seoUrl =
            seoUrlCollection.value?.find((item) => {
                return item.pathInfo && item.routeName && item.foreignKey;
            }) || {};

        entity.foreignKey = defaultSeoUrl.value?.foreignKey ?? seoUrl.foreignKey;
        entity.isCanonical = true;
        entity.languageId = actualLanguageId;
        entity.channelId = currentChannelId.value;
        entity.routeName = defaultSeoUrl.value?.routeName ?? seoUrl.routeName;
        entity.pathInfo = defaultSeoUrl.value?.pathInfo ?? seoUrl.pathInfo;
        entity.isModified = true;

        seoUrlCollection.value?.add(entity);

        Contena.Store.get('swSeoUrl').currentSeoUrl = entity;

        return;
    }

    Contena.Store.get('swSeoUrl').currentSeoUrl = currentSeoUrl;
};
const onChannelChanged = (channelId: string | null): void => {
    currentChannelId.value = channelId;
    emit('on-change-channel', channelId);
    refreshCurrentSeoUrl();
};

watch(
    () => props.urls,
    () => {
        initSeoUrlCollection();
        refreshCurrentSeoUrl();
    },
);

Contena.Utils.EventBus.on('ct-blog-detail-save-finish', clearDefaultSeoUrls);

createdComponent();

onBeforeUnmount(() => {
    Contena.Utils.EventBus.off('ct-blog-detail-save-finish', clearDefaultSeoUrls);
});

swDefinePublic({
    repositoryFactory,
    currentChannelId,
    showEmptySeoUrlError,
    seoUrlCollection,
    currentSeoUrl,
    defaultSeoUrl,
    seoUrlRepository,
    channelRepository,
    isHeadlessChannel,
    seoUrlHelptext,
    seoPathInfoError,
    hasAdditionalSeoSlot,
    allowInput,
    createdComponent,
    initChannelCollection,
    initSeoUrlCollection,
    clearDefaultSeoUrls,
    refreshCurrentSeoUrl,
    onChannelChanged,
});

defineExpose({
    repositoryFactory,
    currentChannelId,
    showEmptySeoUrlError,
    seoUrlCollection,
    currentSeoUrl,
    defaultSeoUrl,
    seoUrlRepository,
    channelRepository,
    isHeadlessChannel,
    seoUrlHelptext,
    seoPathInfoError,
    hasAdditionalSeoSlot,
    allowInput,
    createdComponent,
    initChannelCollection,
    initSeoUrlCollection,
    clearDefaultSeoUrls,
    refreshCurrentSeoUrl,
    onChannelChanged,
});
</script>
