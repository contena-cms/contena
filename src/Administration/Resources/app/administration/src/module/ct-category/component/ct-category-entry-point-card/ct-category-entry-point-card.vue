<template>
    <ct-block name="ct_category_entry_point_card">
        <mt-card
            class="ct-category-entry-point-card"
            position-identifier="ct-category-entry-point"
            :title="$t('ct-category.base.entry-point-card.cardTitle')"
        >
            <ct-block name="ct_category_entry_point_card_selection">
                <ct-single-select
                    v-model:value="selectedEntryPoint"
                    class="ct-category-entry-point-card__entry-point-selection"
                    :options="entryPoints"
                    :label="$t('ct-category.base.entry-point-card.labelEntryPoint')"
                    :placeholder="$t('ct-category.base.entry-point-card.placeholderEntryPoint')"
                    :help-text="helpText"
                    :disabled="hasExistingNavigation || !acl.can('category.editor')"
                    show-clearable-button
                    @update:value="onEntryPointChange"
                />
            </ct-block>

            <ct-block name="ct_category_entry_point_card_navigation_headline">
                <p v-if="hasExistingNavigation">
                    {{ $t('ct-category.base.entry-point-card.existingNavigationDescription') }}
                </p>
            </ct-block>

            <ct-block name="ct_category_entry_point_card_navigation_list">
                <div v-if="hasExistingNavigation" class="ct-category-entry-point-card__navigation-list">
                    <router-link
                        v-for="channel in initialNavigationChannels"
                        :key="channel.id"
                        :to="{ name: 'ct.channel.detail.base', params: { id: channel.id } }"
                        class="ct-category-entry-point-card__navigation-entry"
                    >
                        {{ channel.translated.name }}
                    </router-link>
                </div>
            </ct-block>

            <ct-block name="ct_category_entry_point_card_channel_selection">
                <ct-category-channel-multi-select
                    v-if="associatedCollection"
                    class="ct-category-entry-point-card__channel-selection"
                    :entity-collection="associatedCollection"
                    :label="channelSelectionLabel"
                    :criteria="channelCriteria"
                    :placeholder="$t('ct-category.base.entry-point-card.placeholderChannels')"
                    :disabled="!selectedEntryPoint || !acl.can('category.editor')"
                    @update:entity-collection="onChannelChange"
                />
            </ct-block>

            <ct-block name="ct_category_entry_point_card_button_configure_home">
                <mt-button
                    v-if="selectedEntryPoint === 'navigationChannels' && category.navigationChannels.length > 0"
                    class="ct-category-entry-point-card__button-configure-home"
                    size="small"
                    variant="secondary"
                    @click="openConfigureHomeModal"
                >
                    {{ $t('ct-category.base.entry-point-card.buttonConfigureHome') }}
                </mt-button>
            </ct-block>

            <ct-block name="ct_category_entry_point_card_configure_home_modal">
                <ct-category-entry-point-modal
                    v-if="configureHomeModalVisible"
                    :category-id="category.id"
                    :channel-collection="category.navigationChannels"
                    @modal-close="closeConfigureHomeModal"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-category-entry-point-card.scss';

const { Context } = Contena;
const { Criteria, EntityCollection } = Contena.Data;

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

const { t } = useI18n();

function getInitialEntryPointFromCategory() {
    if (props.category.navigationChannels?.length > 0) {
        return 'navigationChannels';
    }

    if (props.category.footerChannels?.length > 0) {
        return 'footerChannels';
    }

    if (props.category.serviceChannels?.length > 0) {
        return 'serviceChannels';
    }

    return '';
}

const acl = inject('acl');
const selectedEntryPoint = ref(getInitialEntryPointFromCategory());
const initialNavigationChannels = ref(props.category.navigationChannels);
const addedNavigationChannels = ref(new EntityCollection('/channel', 'channel', Context.api));
const configureHomeModalVisible = ref(false);
const entryPoints = computed(() => [
    {
        value: 'navigationChannels',
        label: t('ct-category.base.entry-point-card.types.labelMainNavigation'),
    },
    {
        value: 'footerChannels',
        label: t('ct-category.base.entry-point-card.types.labelFooterNavigation'),
    },
    {
        value: 'serviceChannels',
        label: t('ct-category.base.entry-point-card.types.labelServiceNavigation'),
    },
]);
const hasExistingNavigation = computed(() => initialNavigationChannels.value.length > 0);
const associatedCollection = computed(() => {
    if (hasExistingNavigation.value) {
        return addedNavigationChannels.value;
    }

    return props.category[selectedEntryPoint.value];
});
const helpText = computed(() => {
    const snippets = {
        navigationChannels: 'ct-category.base.entry-point-card.types.helpTextMainNavigation',
        footerChannels: 'ct-category.base.entry-point-card.types.helpTextFooterNavigation',
        serviceChannels: 'ct-category.base.entry-point-card.types.helpTextServiceNavigation',
    };
    const snippet = snippets[selectedEntryPoint.value];

    return snippet ? t(snippet) : '';
});
const channelSelectionLabel = computed(() => {
    if (hasExistingNavigation.value) {
        return t('ct-category.base.entry-point-card.labelChannelsAdd');
    }

    return t('global.entities.channel', 2);
});
const channelCriteria = computed(() => {
    const criteria = new Criteria(1, 25);

    if (hasExistingNavigation.value) {
        criteria.addFilter(Criteria.not('or', [Criteria.equalsAny('id', initialNavigationChannels.value.getIds())]));
    }

    return criteria;
});

const resetChannelCollections = () => {
    entryPoints.value.forEach(({ value }) => {
        if (value === selectedEntryPoint.value) {
            return;
        }

        props.category[value].getIds().forEach((id) => props.category[value].remove(id));
    });
};
const onEntryPointChange = () => {
    resetChannelCollections();
};
const onChannelChange = (changedEntityCollection) => {
    const entryPoint = selectedEntryPoint.value;

    if (hasExistingNavigation.value) {
        const joinedNavigationCollection = EntityCollection.fromCollection(initialNavigationChannels.value);
        changedEntityCollection.forEach((item) => joinedNavigationCollection.add(item));
        addedNavigationChannels.value = changedEntityCollection;
        changedEntityCollection = joinedNavigationCollection;
    }

    changedEntityCollection.source = props.category[entryPoint].source;
    resetChannelCollections();
    // eslint-disable-next-line vue/no-mutating-props -- Upstream persists the association through the Category aggregate.
    props.category[entryPoint] = changedEntityCollection;
};
const openConfigureHomeModal = () => {
    configureHomeModalVisible.value = true;
};
const closeConfigureHomeModal = () => {
    configureHomeModalVisible.value = false;
};

watch(
    () => props.category,
    (newCategory) => {
        initialNavigationChannels.value = newCategory.navigationChannels;
        addedNavigationChannels.value = new EntityCollection('/channel', 'channel', Context.api);
        selectedEntryPoint.value = getInitialEntryPointFromCategory();
    },
);

ctDefinePublic({
    acl,
    selectedEntryPoint,
    initialNavigationChannels,
    addedNavigationChannels,
    entryPoints,
    associatedCollection,
    helpText,
    hasExistingNavigation,
    channelSelectionLabel,
    channelCriteria,
    configureHomeModalVisible,
    getInitialEntryPointFromCategory,
    onEntryPointChange,
    onChannelChange,
    resetChannelCollections,
    openConfigureHomeModal,
    closeConfigureHomeModal,
});

defineExpose({
    acl,
    selectedEntryPoint,
    initialNavigationChannels,
    addedNavigationChannels,
    entryPoints,
    associatedCollection,
    helpText,
    hasExistingNavigation,
    channelSelectionLabel,
    channelCriteria,
    configureHomeModalVisible,
    getInitialEntryPointFromCategory,
    onEntryPointChange,
    onChannelChange,
    resetChannelCollections,
    openConfigureHomeModal,
    closeConfigureHomeModal,
});
</script>
