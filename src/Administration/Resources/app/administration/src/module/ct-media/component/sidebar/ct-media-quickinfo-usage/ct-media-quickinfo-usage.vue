<template>
    <ct-block name="ct_media_quickinfo_usage">
        <div class="ct-media-quickinfo-usage">
            <ct-block name="ct_media_quickinfo_usage_loader"> </ct-block>

            <ct-block name="ct_media_quickinfo_usage_empty_state">
                <mt-banner
                    v-if="isNotUsed"
                    class="ct-media-quickinfo-usage__info-not-used"
                    variant="info"
                    :title="$t('ct-media.sidebar.usage.titleMediaNotUsed')"
                >
                    {{ $t('ct-media.sidebar.usage.labelMediaNotUsed') }}
                </mt-banner>
            </ct-block>

            <ct-block name="ct_media_quickinfo_usage_list">
                <template v-if="isNotUsed"><!-- Keeps the conditional chain connected across ct-block. --></template>
                <ul v-else class="ct-media-quickinfo-usage__list">
                    <ct-block name="ct_media_quickinfo_usage_item">
                        <router-link
                            v-for="usage in getUsages"
                            :key="usage.link.id"
                            :to="{ name: usage.link.name, params: { id: usage.link.id } }"
                            :target="routerLinkTarget"
                        >
                            <li
                                v-tooltip="{
                                    showDelay: 300,
                                    hideDelay: 5,
                                    message: usage.tooltip,
                                }"
                                class="ct-media-quickinfo-usage__item"
                            >
                                <ct-block name="ct_media_quickinfo_usage_item_icon">
                                    <div class="ct-media-quickinfo-usage__label">
                                        <mt-icon :name="usage.icon.name" :color="usage.icon.color" size="16px" />
                                    </div>
                                </ct-block>

                                <ct-block name="ct_media_quickinfo_usage_item_label">
                                    <div class="ct-media-quickinfo-usage__label">
                                        {{ usage.name }}
                                    </div>
                                </ct-block>
                            </li>
                        </router-link>
                    </ct-block>
                </ul>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-quickinfo-usage.scss';
const { Application } = Contena;

const props = defineProps({
    item: {
        required: true,
        type: Object,
        validator(value) {
            return value.getEntityName() === 'media';
        },
    },
    routerLinkTarget: {
        required: false,
        type: String,
        default: '',
    },
});

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const moduleFactory = computed(() => {
    return Application.getContainer('factory').module;
});
const getUsages = computed(() => {
    return (props.item.avatarUsers ?? []).map((user) => getAvatarUserUsage(user));
});
const isNotUsed = computed(() => {
    return getUsages.value.length === 0;
});

const getAvatarUserUsage = (user) => {
    return {
        name: user.username,
        tooltip: t('ct-media.sidebar.usage.tooltipFoundInUser'),
        link: {
            name: 'ct.users.user.detail',
            id: user.id,
        },
        icon: getIconForModule('ct-users'),
    };
};
const getIconForModule = (name) => {
    const module = moduleFactory.value.getModuleRegistry().get(name);
    return {
        name: module.manifest.icon,
        color: module.manifest.color,
    };
};

ctDefinePublic({
    moduleFactory,
    getUsages,
    isNotUsed,
    getAvatarUserUsage,
    getIconForModule,
});

defineExpose({
    moduleFactory,
    getUsages,
    isNotUsed,
    getAvatarUserUsage,
    getIconForModule,
});
</script>
