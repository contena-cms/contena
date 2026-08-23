<template>
    <ct-block name="sw_blog_visibility_select">
        <mt-entity-select
            class="ct-blog-visibility-select"
            entity="channel"
            label-property="name"
            enable-multi-selection
            :model-value="selectedChannelIds"
            :criteria="channelCriteria"
            :disabled="disabled || undefined"
            :placeholder="placeholder"
            @item-add="addItem"
            @item-remove="removeItem"
            @update:model-value="updateSelectedChannels"
        />
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import type { PropType } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject } from 'vue';

const { Criteria } = Contena.Data;
const props = defineProps({
    entityCollection: {
        type: Object as PropType<EntityCollection<'blog_visibility'>>,
        required: true,
    },
    criteria: {
        type: Object as PropType<InstanceType<typeof Criteria> | null>,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: '',
    },
});
const emit = defineEmits<{
    'item-add': [channel: Entity<'channel'>];
    'update:entity-collection': [visibilities: EntityCollection<'blog_visibility'>];
}>();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const defaultVisibility = 30;
const associationRepository = computed(() => repositoryFactory.create('blog_visibility'));
const channelCriteria = computed(() => {
    if (props.criteria) {
        return props.criteria;
    }

    const criteria = new Criteria(1, 25);
    // Criteria is a local mutable query object, not component state.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    criteria.addSorting(Criteria.sort('name', 'ASC'));

    return criteria;
});
const selectedChannelIds = computed(() => props.entityCollection.map((visibility) => visibility.channelId));
const emitCollection = (): void => {
    emit('update:entity-collection', props.entityCollection);
};
const addItem = (channel: Entity<'channel'>): void => {
    if (!channel?.id || selectedChannelIds.value.includes(channel.id)) {
        return;
    }

    const visibility = associationRepository.value.create(props.entityCollection.context);
    visibility.blogId = blog.value.id;
    visibility.blogVersionId = blog.value.versionId;
    visibility.channelId = channel.id;
    visibility.visibility = defaultVisibility;
    visibility.channel = channel;

    props.entityCollection.add(visibility);
    emit('item-add', channel);
    emitCollection();
};
const removeItem = (channel: Entity<'channel'>): void => {
    const visibility = props.entityCollection.find((item) => item.channelId === channel.id);
    if (!visibility) {
        return;
    }

    props.entityCollection.remove(visibility.id);
    emitCollection();
};
const updateSelectedChannels = (channelIds: string[]): void => {
    if (!Array.isArray(channelIds)) {
        return;
    }

    props.entityCollection
        .filter((visibility) => !channelIds.includes(visibility.channelId))
        .forEach((visibility) => props.entityCollection.remove(visibility.id));
    emitCollection();
};

swDefinePublic({
    blog,
    defaultVisibility,
    associationRepository,
    channelCriteria,
    selectedChannelIds,
    addItem,
    removeItem,
    updateSelectedChannels,
});

defineExpose({
    blog,
    defaultVisibility,
    associationRepository,
    channelCriteria,
    selectedChannelIds,
    addItem,
    removeItem,
    updateSelectedChannels,
});
</script>
