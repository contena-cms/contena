<template>
    <ct-block name="sw_settings">
        <ct-page class="ct-settings-index" :show-smart-bar="false">
            <template #content>
                <ct-block name="sw_settings_content">
                    <ct-card-view class="ct-settings-index__card-view">
                        <ct-block name="sw_settings_content_card_view">
                            <div class="ct-settings__card--hero">
                                <ct-block name="sw_settings_content_card_view_header">
                                    <div class="ct-settings__content-header">
                                        <h1 class="ct-settings__content-header-title">
                                            {{ $t('ct-settings.index.title') }}
                                        </h1>

                                        <mt-search
                                            v-model="searchQuery"
                                            class="ct-settings__content-header-search"
                                            :placeholder="$t('ct-settings.index.search.placeholder')"
                                            size="small"
                                        />
                                    </div>
                                </ct-block>

                                <ct-block name="sw_settings_content_card_content_grid">
                                    <div class="ct-settings__content-grid" position-identifier="ct-settings-index-content">
                                        <div
                                            v-for="(settingsItems, settingsGroup) in settingsGroups"
                                            :id="`ct-settings__content-group-${settingsGroup}`"
                                            :key="settingsGroup"
                                            class="ct-settings__content-group"
                                        >
                                            <span class="ct-settings__content-group-title">
                                                <ct-highlight-text
                                                    :text="getGroupLabel(settingsGroup)"
                                                    :search-term="searchQuery"
                                                />
                                            </span>

                                            <ct-settings-item
                                                v-for="settingsItem in settingsItems"
                                                :id="settingsItem.id"
                                                :key="settingsItem.name"
                                                :label="getLabel(settingsItem)"
                                                :to="getRouteConfig(settingsItem)"
                                            >
                                                <template #icon>
                                                    <component
                                                        :is="settingsItem.iconComponent"
                                                        v-if="settingsItem.iconComponent"
                                                    />

                                                    <mt-icon v-else :name="settingsItem.icon" size="16px" />
                                                </template>

                                                <template #label>
                                                    <ct-highlight-text
                                                        :text="getLabel(settingsItem)"
                                                        :search-term="searchQuery"
                                                    />
                                                </template>
                                            </ct-settings-item>
                                        </div>
                                    </div>

                                    <div class="ct-settings-index__empty-state">
                                        <mt-empty-state
                                            v-if="Object.keys(settingsGroups).length === 0"
                                            :description="$t('ct-settings.index.search.noResultsDescription')"
                                            :headline="$t('ct-settings.index.search.noResultsHeadline')"
                                            icon="regular-cog"
                                            centered
                                        />
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { type SettingsItem } from 'src/app/store/settings-item.store';
import './ct-settings-index.scss';
const { hasOwnProperty } = Contena.Utils.object;
type SettingsItemHere = Omit<SettingsItem, 'label'> & {
    label?: string | { label: string; translated: boolean };
} & { privilege?: string };

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const acl = inject('acl');

const searchQuery = ref('');

const settingsGroups = computed(() => {
    // Helpers
    const labelOfSetting = (setting: SettingsItemHere) =>
        typeof setting.label === 'string' ? setting.label : (setting.label?.label ?? '');

    const allSettingsGroups = Contena.Store.get('settingsItems').settingsGroups as Record<string, SettingsItemHere[]>;

    return Object.fromEntries(
        Object.entries(allSettingsGroups)
            .map(
                ([
                    groupName,
                    settings,
                ]) => {
                    const privilegedSettings = settings.filter(
                        (setting) => !setting.privilege || acl.can(setting.privilege),
                    );

                    return [
                        groupName,
                        privilegedSettings,
                    ] as const;
                },
            )
            .map(
                ([
                    groupName,
                    settings,
                ]) => {
                    if (itemIsQueried(getGroupLabel(groupName))) {
                        return [
                            groupName,
                            settings,
                        ] as const;
                    }

                    return [
                        groupName,
                        settings.filter((setting) => itemIsQueried(getLabel(setting))),
                    ] as const;
                },
            )
            .map(
                ([
                    groupName,
                    settings,
                ]) => [
                    groupName,
                    settings.sort((a, b) => {
                        const labelA = labelOfSetting(a);
                        const labelB = labelOfSetting(b);

                        return t(labelA).localeCompare(t(labelB));
                    }),
                ],
            )
            .filter(
                ([
                    ,
                    settings,
                ]) => settings.length > 0,
            ),
    );
});

const hasPluginConfig = () => {
    return hasOwnProperty(settingsGroups.value, 'plugins') && settingsGroups.value.plugins.length > 0;
};
const getRouteConfig = (settingsItem: SettingsItemHere) => {
    if (!hasOwnProperty(settingsItem, 'to')) {
        return {};
    }

    if (typeof settingsItem.to === 'string') {
        return { name: settingsItem.to };
    }

    if (typeof settingsItem.to === 'object') {
        return settingsItem.to;
    }

    return {};
};
const getLabel = (settingsItem: SettingsItemHere) => {
    if (!hasOwnProperty(settingsItem, 'label')) {
        return '';
    }

    if (typeof settingsItem.label === 'string') {
        return t(settingsItem.label);
    }

    if (typeof settingsItem.label !== 'object') {
        return '';
    }

    if (!hasOwnProperty(settingsItem.label, 'translated')) {
        return '';
    }

    if (!hasOwnProperty(settingsItem.label, 'label') || typeof settingsItem.label.label !== 'string') {
        return '';
    }

    if (settingsItem.label.translated) {
        return settingsItem.label.label;
    }

    return t(settingsItem.label.label);
};
const getGroupLabel = (settingsGroup: string) => {
    const upper = settingsGroup.charAt(0).toUpperCase() + settingsGroup.slice(1);
    return t(`ct-settings.index.tab${upper}`);
};
const itemIsQueried = (label: string) => {
    const query = searchQuery.value.trim().toLowerCase();
    const item = label.trim().toLowerCase();
    if (query === '') {
        return true;
    }
    return item.includes(query) || query.includes(item);
};

swDefinePublic({
    acl,
    searchQuery,
    settingsGroups,
    hasPluginConfig,
    getRouteConfig,
    getLabel,
    getGroupLabel,
    itemIsQueried,
});

defineExpose({
    acl,
    searchQuery,
    settingsGroups,
    hasPluginConfig,
    getRouteConfig,
    getLabel,
    getGroupLabel,
    itemIsQueried,
});
</script>
